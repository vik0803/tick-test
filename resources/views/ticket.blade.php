<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket</title>
    <link rel="icon" type="image/png" sizes="16x16" href="/Tick8/assets/img/tic8-dark.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="/Tick8/public/css/Style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="flex items-center justify-center h-screen bg-gray-600 w-full" style="background-color: black;">
        <div class="container">
            <div class="logo">
                <img src="/Tick8/assets/img/tic8-dark.png" alt="Logo">
            </div>
            <div class="ticket">
                <div class="ticket-content">
                    <div class="ticket-header flex items-center justify-between gap-6">
                        <div class="location">Name
                            <div class="value"><p>{{ $userName }}</p></div>
                        </div>
                        <div class="location">Location
                            <div class="value"><p>{{ $location }}</p></div>
                        </div>
                        <div class="location">Ticket type
                            <div class="value bg-yellow-300 text-white p-1 rounded-2xl">
                                <p class="bg-amber-300 p-1 rounded-md">Regular</p>
                            </div>
                        </div>
                    </div>
                    <div class="ticket-body">
                        <div class="date-time flex items-center justify-between gap-6">
                            <div class="date text-amber-100">Date
                                <div class="value">
                                    <p>{{ date('l, F j, Y', strtotime($eventDate)) }}</p>
                                </div>
                            </div>
                            <div class="time">Time
                                <div class="value">
                                    <p>{{ date('g:i A', strtotime($eventTime)) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="invitationn">Invitation</div>
                    </div>
                    <div class="invitation px-6"></div>
                </div>
                <div class="ticket-footer">
                    <div class="qr-code" id="qr-code">
                        @if(!empty($qrImagePath))
                            <img src="{{ $qrImagePath }}" alt="QR Code">
                        @else
                            No QR code image URL provided.
                        @endif
                    </div>
                </div>
                <div class="green-part"></div>
            </div>
        </div>
    </div>
</body>
</html> 