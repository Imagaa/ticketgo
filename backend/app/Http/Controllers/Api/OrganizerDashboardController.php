<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Order;
use Carbon\Carbon;

class OrganizerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // 1. Ambil semua Event milik Organizer ini
        $organizerEvents = Event::where('organizer_id', $userId)->pluck('id');

        // 2. Hitung Event Aktif (Event yang belum berlalu)
        $activeEventsCount = Event::where('organizer_id', $userId)
            ->where('start_date', '>=', now())
            ->count();

        // 3. Ambil semua Order yang sukses untuk event-event milik EO ini
        $successfulOrders = Order::whereIn('event_id', $organizerEvents)
            ->whereIn('status', ['success', 'settlement', 'paid']) // Sesuaikan dengan status payment gateway
            ->get();

        // 4. Kalkulasi Pendapatan dan Tiket Terjual
        $totalRevenue = $successfulOrders->sum('total_amount'); 
        $ticketsSold = $successfulOrders->sum('quantity'); 

        // 5. Ambil 5 Transaksi Terbaru
        $recentOrders = Order::with(['event', 'user']) // Pastikan relasi event dan user ada di model Order
            ->whereIn('event_id', $organizerEvents)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => 'ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                    'event' => $order->event->title ?? 'Event Dihapus',
                    'buyer' => $order->user->name ?? 'Guest',
                    'amount' => 'Rp ' . number_format($order->total_amount, 0, ',', '.'),
                    'status' => ucfirst($order->status),
                    'time' => $order->created_at->diffForHumans(),
                ];
            });

        // Format response agar cocok 100% dengan kebutuhan UI Frontend kita
        return response()->json([
            'stats' => [
                [
                    'label' => 'Total Pendapatan',
                    'value' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                    'trend' => '+0%', // Bisa dikembangkan nanti dengan perbandingan bulan lalu
                    'isPositive' => true,
                    'icon' => 'Wallet'
                ],
                [
                    'label' => 'Tiket Terjual',
                    'value' => (string) $ticketsSold,
                    'trend' => '+0%',
                    'isPositive' => true,
                    'icon' => 'Ticket'
                ],
                [
                    'label' => 'Event Aktif',
                    'value' => (string) $activeEventsCount,
                    'trend' => 'Stabil',
                    'isPositive' => true,
                    'icon' => 'CalendarDays'
                ],
                [
                    'label' => 'Tingkat Konversi',
                    'value' => '0%', // Belum ada fitur page views, set 0% dulu
                    'trend' => '-',
                    'isPositive' => true,
                    'icon' => 'TrendingUp'
                ]
            ],
            'recent_orders' => $recentOrders
        ]);
    }
}