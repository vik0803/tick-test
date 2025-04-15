<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    // GET /events
    public function index()
    {
        return Event::all();
    }

    // POST /events
    public function store(Request $request)
    {
        $request->validate([
            'event_name'     => 'required|string|max:191',
            'event_date'     => 'required|date',
            'event_time'     => 'required',
            'location'       => 'required|string|max:191',
            'ticket_prefix'  => 'required|string|max:4',
        ]);

        $event = Event::create($request->all());

        return response()->json($event, 201);
    }

    public function show($uuid)
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        return response()->json($event);
    }
    
    public function update(Request $request, $uuid)
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $event->update($request->all());
    
        return response()->json($event);
    }
    
    public function destroy($uuid)
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();
        $event->delete();
    
        return response()->json(['message' => 'Event deleted successfully']);
    }
    
}
