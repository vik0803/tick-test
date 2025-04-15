<?php

namespace App\Services;

use Carbon\Carbon;
use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Template;
use App\Services\WhatsappService;
use App\Traits\TemplateTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Validator;

class CampaignService
{
    use TemplateTrait;

    public function store(object $request){
        $organizationId = session()->get('current_organization');

        $timezone = Setting::where('key', 'timezone')->value('value');
        $organization = Organization::find($organizationId);
        $organizationMetadata = json_decode($organization->metadata ?? '{}', true);
        $timezone = $organizationMetadata['timezone'] ?? $timezone;

        $contactGroup = ContactGroup::where('uuid', $request->contacts)->first();
        $event = $request->event_id ? Event::where('event_id', $request->event_id)->first() : null;

        DB::transaction(function () use ($request, $organizationId, $timezone, $contactGroup, $event) {
            $contacts = $request->contacts === 'all' 
                ? Contact::all()
                : $contactGroup->contacts;

            // Generate tickets for contacts if this is an event campaign
            if ($event) {
                foreach ($contacts as $contact) {
                    $ticketId = $event->ticket_prefix . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
                    
                    Invitation::create([
                        'event_id' => $event->event_id,
                        'user_name' => $contact->first_name,
                        'phone_number' => $contact->phone,
                        'ticket_id' => $ticketId
                    ]);
                }
            }

            $metadata = [
                'header' => $request->header,
                'body' => $request->body,
                'footer' => $request->footer,
                'buttons' => $request->buttons,
                'media' => null,
                'event_id' => $event ? $event->event_id : null
            ];

            // Convert $request->time from organization's timezone to UTC
            $scheduledAt = $request->skip_schedule ? Carbon::now('UTC') : Carbon::parse($request->time, $timezone)->setTimezone('UTC');

            //Create campaign
            $campaign = new Campaign;
            $campaign['organization_id'] = $organizationId;
            $campaign['name'] = $request->name;
            
            // Get template by UUID and use its numeric ID
            $template = Template::where('uuid', $request->template)->first();
            $campaign['template_id'] = $template ? $template->id : null;
            
            $campaign['contact_group_id'] = $request->contacts === 'all' ? 0 : $contactGroup->id;
            $campaign['metadata'] = json_encode($metadata);
            $campaign['created_by'] = auth()->user()->id;
            $campaign['status'] = $request->skip_schedule ? 'ongoing' : 'scheduled';
            $campaign['scheduled_at'] = $scheduledAt;
            $campaign['event_id'] = $event ? $event->event_id : null;
            $campaign->save();

            // Create campaign logs for each contact
            foreach ($contacts as $contact) {
                $invitation = $event ? Invitation::where('event_id', $event->event_id)
                    ->where('phone_number', $contact->phone)
                    ->first() : null;

               // $ticketUrl = $invitation ? route('ticket.show', ['username' => $invitation->user_name]) : null;

                $campaignLog = new CampaignLog;
                $campaignLog['campaign_id'] = $campaign->id;
                $campaignLog['contact_id'] = $contact->id;
                $campaignLog['status'] = 'pending';
                $campaignLog['metadata'] = json_encode([
                    //'ticket_url' => $ticketUrl,
                    'ticket_id' => $invitation ? $invitation->ticket_id : null
                ]);
                $campaignLog->save();
            }

            // Dispatch job to send campaign
            if ($request->skip_schedule) {
                SendCampaignJob::dispatch($campaign->id);
            }
        });
    }

    private function getMediaInfo($path)
    {
        $fullPath = storage_path('app/public/' . $path);

        return [
            'name' => pathinfo($fullPath, PATHINFO_FILENAME),
            'type' => File::extension($fullPath),
            'size' => Storage::size($path), // Size in bytes
        ];
    }

    public function sendCampaign(){
        //Laravel jobs implementation
        SendCampaignJob::dispatch();
    }

    public function destroy($uuid){
        $campaign = Campaign::where('uuid', $uuid)->first();
        if($campaign){
            $campaign->delete();
        }
    }

    private function getContentTypeFromUrl($url) {
        try {
            // Make a HEAD request to fetch headers only
            $response = Http::head($url);
    
            // Check if the Content-Type header is present
            if ($response->hasHeader('Content-Type')) {
                return $response->header('Content-Type');
            }
    
            return null;
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error fetching headers: ' . $e->getMessage());
            return null;
        }
    }

    private function getMediaSizeInBytesFromUrl($url) {
        $url = ltrim($url, '/');
        $imageContent = file_get_contents($url);
    
        if ($imageContent !== false) {
            return strlen($imageContent);
        }
    
        return null;
    }
}