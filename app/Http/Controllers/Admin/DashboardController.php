<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\HandoverRequest;
use App\Models\RoomInventory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesCurrentVenue;

    /** How far ahead the utilisation figure looks. */
    private const UTILISATION_DAYS = 30;

    /** How far ahead confirmed programmes are listed. */
    private const UPCOMING_DAYS = 7;

    /** A pending request older than this counts as an organiser left waiting. */
    private const WAITING_HOURS = 24;

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $today = now($venue->timezone)->startOfDay();

        $utilisation = RoomInventory::whereIn('room_id', $venue->rooms()->where('is_active', true)->select('id'))
            ->whereDate('event_date', '>=', $today->toDateString())
            ->whereDate('event_date', '<=', $today->copy()->addDays(self::UTILISATION_DAYS - 1)->toDateString())
            ->selectRaw('SUM(total_rooms) as total, SUM(booked_rooms) as booked')
            ->first();

        $upcomingQuery = fn () => $venue->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereDate('start_date', '>=', $today->toDateString())
            ->whereDate('start_date', '<=', $today->copy()->addDays(self::UPCOMING_DAYS - 1)->toDateString());

        $stats = [
            'rooms' => $venue->rooms()->count(),
            'knowledge_items' => $venue->knowledgeItems()->count(),
            'pending_bookings' => $venue->bookings()->where('status', Booking::STATUS_PENDING)->count(),
            'waiting_bookings' => $venue->bookings()
                ->where('status', Booking::STATUS_PENDING)
                ->where('created_at', '<', now()->subHours(self::WAITING_HOURS))
                ->count(),
            'upcoming' => $upcomingQuery()->count(),
            'utilisation_percent' => $utilisation?->total > 0 ? (int) round($utilisation->booked / $utilisation->total * 100) : null,
            'open_handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('venue_id', $venue->id))
                ->where('status', HandoverRequest::STATUS_OPEN)
                ->count(),
        ];

        $upcoming = $upcomingQuery()
            ->with('room')
            ->orderBy('start_date')
            ->get();

        $recentBookings = $venue->bookings()->latest()->take(5)->with('room')->get();

        $openHandovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('venue_id', $venue->id))
            ->where('status', HandoverRequest::STATUS_OPEN)
            ->latest()
            ->take(5)
            ->with('conversation')
            ->get();

        return view('admin.dashboard', [
            'venue' => $venue,
            'stats' => $stats,
            'upcoming' => $upcoming,
            'recentBookings' => $recentBookings,
            'utilisationDays' => self::UTILISATION_DAYS,
            'upcomingDays' => self::UPCOMING_DAYS,
            'waitingHours' => self::WAITING_HOURS,
            'openHandovers' => $openHandovers,
        ]);
    }
}
