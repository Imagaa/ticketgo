<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
    public function getWallet(Request $request)
    {
        // Ambil dompet artis beserta 10 riwayat mutasi transfer terakhir
        $wallet = Wallet::with(['mutations' => function($q) {
            $q->orderBy('created_at', 'desc')->take(10);
        }])->firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $wallet->balance,
                'recent_mutations' => $wallet->mutations
            ]
        ]);
    }

    public function getDashboardStats(Request $request)
    {
        $artistId = $request->user()->id;
        
        $activeEvents = Event::where('artist_id', $artistId)
            ->where('end_date', '>=', now())
            ->count();

        // Total tiket global yang terjual dari semua event artis ini
        $ticketsSold = Order::whereHas('event', function($q) use ($artistId) {
            $q->where('artist_id', $artistId);
        })->where('payment_status', 'paid')->sum('quantity');

        return response()->json([
            'success' => true,
            'data' => [
                'active_events' => $activeEvents,
                'tickets_sold' => $ticketsSold
            ]
        ]);
    }
}