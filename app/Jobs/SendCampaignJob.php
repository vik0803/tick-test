<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\WhatsappService;
use App\Traits\HasUuid;
use App\Traits\TemplateTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TemplateTrait, SerializesModels;

    private $organizationId;
    private $whatsappService;

    public function handle()
    {
        try {
            /*$timezoneQuery = Setting::where('key', 'timezone')->first();
            $timezone = $timezoneQuery ? $timezoneQuery->value : 'UTC';*/

            $campaigns = Campaign::whereIn('status', ['scheduled', 'ongoing'])
                ->with('organization') // Eager load the organization relationship
                ->whereNull('deleted_at')
                ->cursor();

            $campaigns->each(function ($campaign) {
                $organization = $campaign->organization;
                $timezone = 'UTC';

                if ($organization) {
                    $metadata = $organization->metadata;
                    $metadata = isset($metadata) ? json_decode($metadata, true) : null;

                    if ($metadata && isset($metadata['timezone'])) {
                        $timezone = $metadata['timezone'];
                    }
                }

                $scheduledAt = Carbon::parse($campaign->scheduled_at, 'UTC')->timezone($timezone);

                // Compare the scheduled_at time with the current time in the organization's timezone
                if ($scheduledAt->lte(Carbon::now($timezone))) {
                    $this->processCampaign($campaign);
                }
            });
        } catch (\Exception $e) {
            // Handle the exception, log it, or take other actions
            Log::error('Error in campaign job: ' . $e->getMessage());

            // Optionally, rethrow the exception if you want the job to be retried
            throw $e;
        }
    }

    protected function processCampaign(Campaign $campaign)
    {
        if ($campaign->status === 'scheduled') {
            $this->processPendingCampaign($campaign);
        } elseif ($campaign->status === 'ongoing') {
            $this->sendOngoingCampaignMessages($campaign);
        }
    }

    protected function processPendingCampaign(Campaign $campaign)
    {
        $contacts = $this->getContactsForCampaign($campaign);

        if($this->createCampaignLogs($campaign, $contacts)){
            if($this->updateCampaignStatus($campaign, 'ongoing')){
                $campaign = Campaign::find($campaign->id);
                $this->processCampaign($campaign);
            }
        }
    }

    protected function getContactsForCampaign(Campaign $campaign)
    {
        $contactGroup = $campaign->contactGroup;

        return (is_null($campaign->contact_group_id) || empty($campaign->contact_group_id) || $campaign->contact_group_id === '0')
            ? Contact::where('organization_id', $campaign->organization_id)->whereNull('deleted_at')->get()
            : optional($contactGroup)->contacts ?? collect();
    }

    protected function createCampaignLogs(Campaign $campaign, $contacts)
    {
        $campaignLogs = [];
        $contactIds = $contacts->pluck('id');

        // Fetch existing logs
        $existingLogs = CampaignLog::where('campaign_id', $campaign->id)
            ->whereIn('contact_id', $contactIds)
            ->pluck('contact_id')
            ->toArray();

        // Filter out contacts that already have logs
        $newContacts = $contactIds->diff($existingLogs);

        // Prepare new campaign logs
        $campaignLogs = $newContacts->map(function ($contactId) use ($campaign) {
            return [
                'campaign_id' => $campaign->id,
                'contact_id' => $contactId,
                'created_at' => now(),
            ];
        })->toArray();

        // Insert new logs if any
        if (!empty($campaignLogs)) {
            return CampaignLog::insert($campaignLogs);
        }

        return false;
    }

    protected function updateCampaignStatus(Campaign $campaign, $status)
    {
        return Campaign::where('uuid', $campaign->uuid)->update(['status' => $status]);
    }

    protected function sendOngoingCampaignMessages(Campaign $campaign)
    {
        $this->processPendingCampaignLogs($campaign);

        // Check if there are no more pending campaign logs
        if (!$this->hasPendingCampaignLogs($campaign)) {
            $this->updateCampaignStatus($campaign, 'completed');
        }
    }

    protected function processPendingCampaignLogs(Campaign $campaign)
    {
        CampaignLog::with(['campaign', 'contact'])
            ->where('campaign_id', $campaign->id)
            ->where('status', '=', 'pending')
            ->chunk(500, function ($pendingCampaignLogs) {
                foreach ($pendingCampaignLogs as $campaignLog) {
                    // Skip if the log is already being processed or processed
                    if ($campaignLog->status === 'ongoing' || $campaignLog->status === 'success') {
                        continue;
                    }
                    
                    // Skip if campaign or contact is missing
                    if (!$campaignLog->campaign || !$campaignLog->contact) {
                        // Mark as failed
                        $campaignLog->status = 'failed';
                        $campaignLog->metadata = json_encode(['error' => !$campaignLog->campaign ? 'Campaign not found' : 'Contact not found']);
                        $campaignLog->save();
                        
                        Log::error('Campaign log has missing relationships', [
                            'campaign_log_id' => $campaignLog->id,
                            'campaign_missing' => !$campaignLog->campaign,
                            'contact_missing' => !$campaignLog->contact
                        ]);
                        continue;
                    }
                    
                    $this->sendTemplateMessage($campaignLog);
                }
            });
    }

    protected function hasPendingCampaignLogs(Campaign $campaign)
    {
        return CampaignLog::where('status', 'pending')
            ->where('campaign_id', $campaign->id)
            ->exists();
    }

    protected function sendTemplateMessage(CampaignLog $campaignLog)
    {
        DB::transaction(function() use ($campaignLog) {
            //Update log to ongoing, prevents this message from being sent out again
            $log = CampaignLog::where('id', $campaignLog->id)
                              ->where('status', 'pending') // Make sure the log is still pending
                              ->lockForUpdate()
                              ->first();
                   
            if ($log && $campaignLog->contact) {
                $campaign_user_id = Campaign::find($log->campaign_id)?->created_by;    
                $log->status = 'ongoing';
                $log->save();
        
                //Set Organization Id & initialize whatsapp service
                $this->organizationId = $campaignLog->campaign->organization_id;
                $this->initializeWhatsappService();
        
                $template = $this->buildTemplateRequest($campaignLog->campaign_id, $campaignLog->contact);
                $responseObject = $this->whatsappService->sendTemplateMessage($campaignLog->contact->uuid, $template, $campaign_user_id, $campaignLog->campaign_id);
                //Log::info(json_encode($responseObject));
                $this->updateCampaignLogStatus($campaignLog, $responseObject);
            } else if ($log && !$campaignLog->contact) {
                // Update log status to failed if contact is missing
                $log->status = 'failed';
                $log->metadata = json_encode(['error' => 'Contact not found']);
                $log->save();
                
                Log::error('Campaign log contact is null', [
                    'campaign_log_id' => $campaignLog->id,
                    'campaign_id' => $campaignLog->campaign_id
                ]);
            }
        });
    }

    protected function updateCampaignLogStatus(CampaignLog $campaignLog, $responseObject)
    {
        $log = CampaignLog::find($campaignLog->id);

        // Update campaign log status based on the response object
        $log->chat_id = $responseObject->data->chat->id ?? null;
        $log->status = ($responseObject->success === true) ? 'success' : 'failed';
        unset($responseObject->success);
        if (property_exists($responseObject, 'data') && property_exists($responseObject->data, 'chat')) {
            unset($responseObject->data->chat);
        }
        $log->metadata = json_encode($responseObject);
        $log->updated_at = now();
        $log->save();
    }

    private function initializeWhatsappService()
    {
        $config = Organization::where('id', $this->organizationId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = 'v18.0';
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $this->organizationId);
    }
}
