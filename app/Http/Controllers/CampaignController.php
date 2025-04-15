<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class CampaignController extends Controller
{
    public function create()
    {
        $organizationId = session()->get('current_organization');
        
        // Debug the organization ID
        Log::info('Current Organization ID: ' . $organizationId);
        
        try {
            $events = Event::where('organization_id', $organizationId)
                ->whereNull('deleted_at')
                ->orderBy('event_date', 'desc')
                ->get();
                
            // Debug the events count
            Log::info('Events found: ' . $events->count());
            
            // Debug the first event if exists
            if ($events->count() > 0) {
                Log::info('First event: ' . json_encode($events->first()));
            }
        } catch (\Exception $e) {
            Log::error('Error fetching events: ' . $e->getMessage());
            $events = collect([]);
        }

        return Inertia::render('User/Campaign/Create', [
            'title' => __('Create Campaign'),
            'events' => $events
        ]);
    }
} 