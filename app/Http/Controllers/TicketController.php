<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Event;
use App\Services\QRController;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function show($username)
    {
        $invitation = Invitation::where('user_name', $username)->firstOrFail();
        $event = Event::findOrFail($invitation->event_id);

        $qrCodesDir = public_path('qrcodes');
        if (!is_dir($qrCodesDir)) {
            mkdir($qrCodesDir, 0755, true);
        }

        $qrText = "Ticket ID: " . $invitation->ticket_id;
        $qrImagePath = QRController::generateQRCode($qrText, $qrCodesDir);

        return view('ticket', [
            'userName' => $invitation->user_name,
            'ticketID' => $invitation->ticket_id,
            'eventName' => $event->event_name,
            'location' => $event->location,
            'eventDate' => $event->event_date,
            'eventTime' => $event->event_time,
            'qrImagePath' => $qrImagePath
        ]);
    }
} 