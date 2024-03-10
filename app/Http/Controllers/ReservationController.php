<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reservations = Reservation::simplePaginate(6);
        return view('reservations', compact('reservations'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(int $id)
    {
        $event = Event::findOrFail($id);
        $user = auth()->user();

        if ($user->reservations()->where('event_id', $id)->exists()) {
            return redirect()->back()->with('status', 'You have already reserved this event!');
        }

        if ($event->availableSeats > 0) {
            if ($event->acceptance === 'auto') {
                $reservation = Reservation::create([
                    'event_id' => $id,
                    'user_id' => $user->id,
                    'reservation_status' => 'accepted',
                    'reference' => Str::random(22),
                ]);

                $event->decrement('availableSeats');

                $data = [
                    'event' => $event,
                    'reservation' => $reservation,
                ];


                $pdf = Pdf::loadView('tickets.ticket', $data);
                return $pdf->download('ticket.pdf');

            } else {

                Reservation::create([
                    'event_id' => $id,
                    'user_id' => $user->id,
                    'reservation_status' => 'pending',
                    'reference' => Str::random(22),
                ]);


                $event->decrement('availableSeats');
            }
        } else {
            return redirect()->back()->with('status', 'Out of stock');
        }

        return redirect()->back()->with('status', 'Event not found!');
    }

    public function usersAcceptance()
    {
        // Get the authenticated user
        $user = auth()->user();

        // Get the events created by the authenticated user
        $eventsCreatedByUser = Event::where('user_id', $user->id)->pluck('id');

        // Get the reservations for events created by the user
        $reservations = Reservation::whereIn('event_id', $eventsCreatedByUser)->paginate(6);

        // Create an array to store unique event IDs
        $processedEvents = [];

        foreach ($reservations as $reservation) {
            // Check if the reservation is accepted
            if ($reservation->reservation_status === 'accepted') {
                $eventId = $reservation->event_id;

                // Check if the event has already been processed
                if (!in_array($eventId, $processedEvents)) {
                    // Decrement available seats for the associated event
                    $event = $reservation->event;
                    $event->decrement('availableSeats');

                    // Add the event ID to the processedEvents array
                    $processedEvents[] = $eventId;
                }
            }
        }

        return view('dashboard.users.usersAcceptance', compact('reservations'));
    }




    public function approve(int $id)
    {
        $reservation = Reservation::findOrFail($id);

        $reservation->update([
            'reservation_status' => 'accepted',
        ]);

        return redirect()->route('users.usersAcceptance')->with('status', 'Reservation approved successfully');
    }

    public function refuse(int $id)
    {
        $reservation = Reservation::findOrFail($id);

        $reservation->update([
            'reservation_status' => 'refused',
        ]);

        return redirect()->route('users.usersAcceptance')->with('status', 'Reservation rejected successfully');
    }
}
