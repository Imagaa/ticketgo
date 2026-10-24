<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\Request;

class AffiliatorController extends Controller
{
    public function getMarketplace()
    {
        // Hanya ambil event yang open affiliate DAN status pembayarannya sudah 'paid' atau 'postpaid' dari Finance
        $events = Event::with('organizer')
            ->where('is_affiliate_enabled', true)
            ->whereIn('affiliate_activation_status', ['paid', 'postpaid'])
            ->where('status', 'published')
            ->where('sales_end_date', '>=', now())
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'date' => $event->start_date,
                    'eo_name' => $event->organizer->name ?? 'Organizer',
                    'commission_rate' => $event->affiliate_commission_rate,
                    'min_ticket_price' => $event->price,
                    'poster_url' => $event->poster_path ? asset('storage/' . $event->poster_path) : null,
                ];
            });

        return response()->json(['success' => true, 'data' => $events]);
    }

    public function getPerformance(Request $request)
    {
        $affiliatorId = $request->user()->id;

        // Ambil semua order sukses yang memakai link affiliate user ini
        $orders = Order::with('event')
            ->where('affiliator_id', $affiliatorId)
            ->where('payment_status', 'paid')
            ->get();

        // Kelompokkan data berdasarkan Event untuk ditampilkan di tabel frontend
        $performance = $orders->groupBy('event_id')->map(function ($group) {
            $event = $group->first()->event;
            return [
                'event_id' => $event->id,
                'event_name' => $event->title,
                'conversions' => $group->sum('quantity'),
                'total_commission' => $group->sum('affiliate_commission_amount'),
                'status' => $event->end_date < now() ? 'ended' : 'active'
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $performance]);
    }
}