<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Wallet;
use Illuminate\Http\Request;

class FinanceAnalyticsController extends Controller
{
    public function getOverview()
    {
        // Menghitung Saldo The Vaults (Platform, EO, Artist, Affiliator)
        // Catatan: Di sistem riil, pastikan query ini di-filter berdasarkan Role masing-masing user.
        // Untuk tahap ini, kita jumlahkan berdasarkan tipe entitas jika role sudah tersambung, 
        // atau kita kembalikan total agregat sementara.
        
        $gmv = Order::where('payment_status', 'paid')->sum('total');
        $platformFee = Order::where('payment_status', 'paid')->sum('admin_fee');
        
        // Escrow = Uang pesanan dari event yang belum selesai (end_date > now)
        $escrow = Order::whereHas('event', function($q) {
            $q->where('end_date', '>=', now());
        })->where('payment_status', 'paid')->sum('subtotal');

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => [
                    'gmv' => (float) $gmv,
                    'platform_fee' => (float) $platformFee,
                    'escrow_balance' => (float) $escrow
                ]
            ]
        ]);
    }

    public function getMarketingMetrics()
    {
        $paidOrders = Order::where('payment_status', 'paid');
        $totalPaid = $paidOrders->count();
        $gmv = $paidOrders->sum('total');

        // Average Order Value (Rata-rata belanja per orang)
        $aov = $totalPaid > 0 ? $gmv / $totalPaid : 0;

        $totalOrders = Order::count();
        $dropOffCount = Order::whereIn('payment_status', ['failed', 'expired', 'cancelled'])->count();
        
        $dropOffRate = $totalOrders > 0 ? ($dropOffCount / $totalOrders) * 100 : 0;
        $successRate = $totalOrders > 0 ? ($totalPaid / $totalOrders) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'aov' => (float) $aov,
                'checkout_funnel' => [
                    'success_rate' => round($successRate, 1),
                    'dropoff_rate' => round($dropOffRate, 1)
                ]
            ]
        ]);
    }

    public function getDemographics()
    {
        // Placeholder Data (Sesuai Frontend). 
        // Nanti diganti query Group By berdasarkan kolom 'city' jika tabel users/orders sudah punya kolom kota.
        return response()->json([
            'success' => true,
            'data' => [
                ['city' => 'Jakarta Selatan', 'tickets' => 4500, 'revenue' => 675000000, 'percentage' => 35],
                ['city' => 'Surakarta (Solo)', 'tickets' => 2800, 'revenue' => 320000000, 'percentage' => 22],
                ['city' => 'Bandung', 'tickets' => 1950, 'revenue' => 215000000, 'percentage' => 15],
            ]
        ]);
    }
}