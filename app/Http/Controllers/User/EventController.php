<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Models\Organization;
use Illuminate\Support\Facades\Session;

class EventController extends BaseController
{
    private $campaignService;

    public function __construct(CampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    public function index(Request $request)
    {
        $searchTerm = $request->query('search');
        $events = (new Event())->getAll($searchTerm);

        // Debugging - log the first event's structure
        if (count($events) > 0) {
            Log::info('First event structure:', ['event' => $events[0]]);
        }

        return Inertia::render('User/Event/Index', [
            'title' => __('Events'),
            'events' => $events,
            'filters' => request()->all(['search']),
            'can' => [
                'create' => true
            ]
        ]);
    }

    public function create()
    {
        return inertia('User/Event/Create', [
            'title' => __('Create Event'),
            'settings' => [
                'can' => [
                    'create' => true
                ]
            ]
        ]);
    }

    public function show($eventId)
    {
        $event = (new Event())->getRow($eventId);
        if (!$event) {
            return back()->withError(__('Event not found'));
        }

        return Inertia::render('User/Event/Show', [
            'title' => __('Event Details'),
            'event' => $event
        ]);
    }

    /**
     * Get event as JSON for API requests
     */
    public function getEvent($eventId)
    {
        $event = (new Event())->getRow($eventId);
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        
        return response()->json($event);
    }

    public function store(Request $request)
    {
        $request->validate([
            'event_name' => 'required|string|max:191',
            'event_date' => 'required|date',
            'event_time' => 'required',
            'location' => 'required|string|max:191',
            'ticket_prefix' => 'required|string|max:4'
        ]);

        try {
            $event = new Event();
            $event->event_name = $request->event_name;
            $event->event_date = $request->event_date;
            $event->event_time = $request->event_time;
            $event->location = $request->location;
            $event->ticket_prefix = $request->ticket_prefix;
            $event->save();

            return Redirect::route('events')->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Event created successfully!')
                ]
            );
        } catch (\Exception $e) {
            return back()->withError($e->getMessage());
        }
    }

    public function edit($eventId)
    {
        $event = (new Event())->getRow($eventId);
        if (!$event) {
            return back()->withError(__('Event not found'));
        }

        return Inertia::render('User/Event/Edit', [
            'title' => __('Edit Event'),
            'event' => $event
        ]);
    }

    public function update(Request $request, $eventId)
    {
        $request->validate([
            'event_name' => 'required|string|max:191',
            'event_date' => 'required|date',
            'event_time' => 'required',
            'location' => 'required|string|max:191',
            'ticket_prefix' => 'required|string|max:4'
        ]);

        try {
            $event = (new Event())->getRow($eventId);
            if (!$event) {
                return back()->withError(__('Event not found'));
            }

            $event->update($request->all());

            return Redirect::route('events')->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Event updated successfully!')
                ]
            );
        } catch (\Exception $e) {
            return back()->withError($e->getMessage());
        }
    }

    public function delete($eventId)
    {
        Log::info('Delete event request received', [
            'event_id' => $eventId,
            'method' => request()->method(),
            'ajax' => request()->ajax(),
            'wants_json' => request()->wantsJson(),
            'headers' => request()->headers->all()
        ]);
        
        try {
            $event = (new Event())->getRow($eventId);
            Log::info('Event found status', ['found' => (bool)$event, 'id' => $eventId]);
            
            if (!$event) {
                Log::warning('Event not found for deletion', ['event_id' => $eventId]);
                if (request()->ajax() || request()->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Event not found'], 404);
                }
                return back()->withError(__('Event not found'));
            }

            try {
                DB::beginTransaction();
                $result = $event->delete();
                DB::commit();
                Log::info('Event deletion result', ['result' => $result, 'event_id' => $eventId]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Database error deleting event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('Event deleted successfully!')
                ]);
            }
            
            // For the debug route, redirect to the events page
            if (request()->route()->getName() === 'debug.events.delete') {
                return redirect('/events')->with(
                    'status', [
                        'type' => 'success',
                        'message' => __('Event deleted successfully!')
                    ]
                );
            }

            return Redirect::route('events')->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Event deleted successfully!')
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error deleting event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            
            return back()->withError($e->getMessage());
        }
    }

    public function createCampaign($eventId)
    {
        $event = (new Event())->getRow($eventId);
        if (!$event) {
            return back()->withError(__('Event not found'));
        }
        $organizationId = session()->get('current_organization');

        $data = [
            'event' => $event,
            'contactGroups' => ContactGroup::where('organization_id', $organizationId)
                ->where('deleted_at', null)
                ->get(),
            'title' => __('Create Event Campaign')
        ];

        return Inertia::render('User/Events/CreateCampaign', $data);
    }

    public function storeCampaign(Request $request, $eventId)
    {
        $event = (new Event())->getRow($eventId);
        if (!$event) {
            return back()->withError(__('Event not found'));
        }
        $request->merge(['event_id' => $event->event_id]);

        $this->campaignService->store($request);

        return Redirect::route('events.show', $event->event_id)->with(
            'status', [
                'type' => 'success',
                'message' => __('Campaign created successfully!')
            ]
        );
    }

    public function generateTickets($eventId, $contactGroupId)
    {
        $event = (new Event())->getRow($eventId);
        if (!$event) {
            return back()->withError(__('Event not found'));
        }
        $organizationId = session()->get('current_organization');

        $contacts = $contactGroupId === 'all'
            ? Contact::where('organization_id', $organizationId)->get()
            : ContactGroup::find($contactGroupId)->contacts;

        DB::transaction(function () use ($event, $contacts) {
            foreach ($contacts as $contact) {
                $ticketId = $event->ticket_prefix . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);

                Invitation::create([
                    'event_id' => $event->event_id,
                    'user_name' => $contact->first_name,
                    'phone_number' => $contact->phone,
                    'ticket_id' => $ticketId
                ]);
            }
        });

        return Redirect::back()->with(
            'status', [
                'type' => 'success',
                'message' => __('Tickets generated successfully!')
            ]
        );
    }
} 