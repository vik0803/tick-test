<?php

namespace App\Http\Controllers\User;

use App\Exports\CampaignDetailsExport;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreCampaign;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\CampaignLogResource;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\ContactGroup;
use App\Models\Organization;
use App\Models\Template;
use App\Models\Event;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Setting;

class CampaignController extends BaseController
{
    private $campaignService;

    public function __construct(CampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    public function index(Request $request, $uuid = null){
        $organizationId = session()->get('current_organization');
        if($uuid == null){
            $searchTerm = $request->query('search');
            $settings = Organization::where('id', $organizationId)->first();
            $rows = CampaignResource::collection(
                Campaign::with(['template', 'campaignLogs'])
                    ->where('organization_id', $organizationId)
                    ->where('deleted_at', null)
                    ->where(function ($query) use ($searchTerm) {
                        $query->where('name', 'like', '%' . $searchTerm . '%')
                              ->orWhereHas('template', function ($templateQuery) use ($searchTerm) {
                                  $templateQuery->where('name', 'like', '%' . $searchTerm . '%');
                              });
                    })
                    ->latest()
                    ->paginate(10)
            );

            return Inertia::render('User/Campaign/Index', [ 'title'=> __('Campaigns'), 'allowCreate' => true, 'rows' => $rows, 'filters' => request()->all(['search']), 'settings' => $settings ]);
        } else if($uuid == 'create'){
            $data['settings'] = Organization::where('id', $organizationId)->first();
            $data['templates'] = Template::where('organization_id', $organizationId)
                ->where('deleted_at', null)
                ->where('status', 'APPROVED')
                ->get();

            $data['contactGroups'] = ContactGroup::where('organization_id', $organizationId)
                ->where('deleted_at', null)
                ->get();

            try {
                $data['events'] = Event::whereNull('deleted_at')
                    ->orderBy('event_date', 'desc')
                    ->get()
                    ->map(function ($event) {
                        return [
                            'uuid' => $event->event_id,
                            'name' => $event->event_name,
                            'event_name' => $event->event_name,
                            'event_date' => $event->event_date,
                            'event_time' => $event->event_time,
                            'location' => $event->location,
                            'ticket_prefix' => $event->ticket_prefix
                        ];
                    })
                    ->toArray();
                
                // Log events data before passing to the view
                Log::info('Events data for campaign create index method:', [
                    'count' => count($data['events']),
                    'first_event' => count($data['events']) > 0 ? $data['events'][0] : null,
                    'route' => 'campaigns/create'
                ]);
            } catch (\Exception $e) {
                Log::error('Error fetching events for campaign create index:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'route' => 'campaigns/create'
                ]);
                $data['events'] = [];
            }

            $data['title'] = __('Create campaign');

            return Inertia::render('User/Campaign/Create', $data);
        } else {
            $data['campaign'] = Campaign::with('contactGroup', 'template')->where('uuid', $uuid)->first();
            if ($data['campaign']) {
                $counts = $data['campaign']->getCounts();
                $data['campaign']['total_message_count'] = $counts->total_message_count ?? 0;
                $data['campaign']['total_sent_count'] = $counts->total_sent_count ?? 0;
                $data['campaign']['total_delivered_count'] = $counts->total_delivered_count ?? 0;
                $data['campaign']['total_failed_count'] = $counts->total_failed_count ?? 0;
                $data['campaign']['total_read_count'] = $counts->total_read_count ?? 0;
                
                $data['filters'] = request()->all(['search']);

                $searchTerm = $request->query('search');
                $data['rows'] = CampaignLogResource::collection(
                    CampaignLog::with('contact', 'chat.logs')
                        ->where('campaign_id', $data['campaign']->id)
                        ->where(function ($query) use ($searchTerm) {
                            $query->whereHas('contact', function ($contactQuery) use ($searchTerm) {
                                $contactQuery->where('first_name', 'like', '%' . $searchTerm . '%')
                                             ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                                             ->orWhere('phone', 'like', '%' . $searchTerm . '%');
                            });
                        })
                        ->orderBy('id')
                        ->paginate(10)
                );
                $data['title'] = __('View campaign');

                return Inertia::render('User/Campaign/View', $data);
            } else {
                return redirect()->route('campaigns')->with('error', __('Campaign not found'));
            }
        }
    }

    public function store(StoreCampaign $request){
        $this->campaignService->store($request);

        return Redirect::route('campaigns')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Campaign created successfully!')
            ]
        );
    }

    public function export($uuid = null){
        return Excel::download(new CampaignDetailsExport($uuid), 'campaign.csv');
    }

    public function delete($uuid){
        $this->campaignService->destroy($uuid);

        return Redirect::back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('Row deleted successfully!')
            ]
        );
    }

    public function create()
    {
        try {
            $organizationId = session()->get('current_organization');
            $templates = Template::where('organization_id', $organizationId)
                ->where('deleted_at', null)
                ->where('status', 'APPROVED')
                ->get();
            $contactGroups = ContactGroup::where('organization_id', $organizationId)
                ->where('deleted_at', null)
                ->get();
            $settings = Organization::where('id', $organizationId)->first();
            $events = Event::whereNull('deleted_at')
                ->orderBy('event_date', 'desc')
                ->get()
                ->map(function ($event) {
                    return [
                        'uuid' => $event->event_id,
                        'name' => $event->event_name,
                        'event_name' => $event->event_name,
                        'event_date' => $event->event_date,
                        'event_time' => $event->event_time,
                        'location' => $event->location,
                        'ticket_prefix' => $event->ticket_prefix
                    ];
                });
            
            // Log events data before passing to the view
            Log::info('Events data for campaign create method:', [
                'count' => count($events),
                'first_event' => count($events) > 0 ? $events[0] : null,
                'events_type' => gettype($events),
                'is_array' => is_array($events),
                'is_collection' => $events instanceof \Illuminate\Support\Collection
            ]);

            return Inertia::render('User/Campaign/Create', [
                'templates' => $templates,
                'contactGroups' => $contactGroups,
                'settings' => $settings,
                'events' => $events,
                'title' => __('Create campaign')
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}