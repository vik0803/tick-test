<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Organization;
use App\Services\WhatsappService;
use App\Traits\TemplateTrait;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSingleCampaignLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TemplateTrait, Batchable;

    private $campaignLog;
    private $organizationId;
    private $whatsappService;
    
    public $timeout = 300; // 5 minutes timeout for single message
    public $tries = 3;

    public function __construct(CampaignLog $campaignLog)
    {
        $this->campaignLog = $campaignLog;
    }

    public function handle()
    {
        try {
            DB::transaction(function() {
                // Lock the log for update to prevent duplicate processing
                $lockedLog = CampaignLog::where('id', $this->campaignLog->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();
                       
                if ($lockedLog) {
                    $campaign_user_id = Campaign::find($this->campaignLog->campaign_id)?->created_by;
                    $lockedLog->status = 'ongoing';
                    $lockedLog->save();
            
                    $this->organizationId = $this->campaignLog->campaign->organization_id;
                    $this->initializeWhatsappService();
            
                    $template = $this->buildTemplateRequest($this->campaignLog->campaign_id, $this->campaignLog->contact);
                    $responseObject = $this->whatsappService->sendTemplateMessage(
                        $this->campaignLog->contact->uuid, 
                        $template, 
                        $campaign_user_id, 
                        $this->campaignLog->campaign_id
                    );

                    $this->updateLogStatus($lockedLog, $responseObject);
                }
            });
        } catch (\Exception $e) {
            Log::error('Error processing campaign log ' . $this->campaignLog->id . ': ' . $e->getMessage());
            throw $e;
        }
    }

    protected function updateLogStatus(CampaignLog $log, $responseObject)
    {
        $log->chat_id = $responseObject->data->chat->id ?? null;
        $log->status = ($responseObject->success === true) ? 'success' : 'failed';
        
        // Clean up response object
        unset($responseObject->success);
        if (property_exists($responseObject, 'data') && property_exists($responseObject->data, 'chat')) {
            unset($responseObject->data->chat);
        }
        
        $log->metadata = json_encode($responseObject);
        $log->updated_at = now();
        $log->save();

        // Check if campaign is completed
        $this->checkAndUpdateCampaignStatus($log->campaign_id);
    }

    protected function checkAndUpdateCampaignStatus($campaignId)
    {
        $pendingCount = CampaignLog::where('campaign_id', $campaignId)
            ->whereIn('status', ['pending', 'ongoing'])
            ->count();

        if ($pendingCount === 0) {
            Campaign::where('id', $campaignId)->update(['status' => 'completed']);
        }
    }

    private function initializeWhatsappService()
    {
        $config = cache()->remember("organization.{$this->organizationId}.metadata", 3600, function() {
            return Organization::find($this->organizationId)->metadata ?? [];
        });

        $config = Organization::where('id', $this->organizationId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = 'v18.0';
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService(
            $accessToken, 
            $apiVersion, 
            $appId, 
            $phoneNumberId, 
            $wabaId, 
            $this->organizationId
        );
    }

    /*private function initializeWhatsappService()
    {
        $config = Organization::where('id', $this->organizationId)->first()->metadata;
        $config = $config ? json_decode($config, true) : [];

        $accessToken = $config['whatsapp']['access_token'] ?? null;
        $apiVersion = 'v18.0';
        $appId = $config['whatsapp']['app_id'] ?? null;
        $phoneNumberId = $config['whatsapp']['phone_number_id'] ?? null;
        $wabaId = $config['whatsapp']['waba_id'] ?? null;

        $this->whatsappService = new WhatsappService(
            $accessToken, 
            $apiVersion, 
            $appId, 
            $phoneNumberId, 
            $wabaId, 
            $this->organizationId
        );
    }*/
}