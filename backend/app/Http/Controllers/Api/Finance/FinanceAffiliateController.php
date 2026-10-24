<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceAffiliateController extends Controller
{
    // 1. Tombol Sakti: Izinkan EO potong saldo affiliate di akhir (Postpaid)
    public function togglePostpaid($eventId)
    {
        $event = Event::findOrFail($eventId);
        $event->update(['affiliate_activation_status' => 'postpaid']);
        
        return response()->json([
            'success' => true,
            'message' => 'Status aktivasi affiliate diubah menjadi Postpaid. Event ini sekarang muncul di Affiliate Marketplace.'
        ]);
    }

    // 2. Tentukan Persentase Komisi Affiliate untuk Event ini
    public function setCommissionRate(Request $request, $eventId)
    {
        $request->validate([
            'rate' => 'required|numeric|min:0|max:100'
        ]);

        $event = Event::findOrFail($eventId);
        $event->update(['affiliate_commission_rate' => $request->rate]);

        return response()->json([
            'success' => true,
            'message' => 'Persentase komisi affiliate berhasil diatur menjadi ' . $request->rate . '%.'
        ]);
    }

    // 3. Eksekusi Pencairan Dana (Payout) ke Wallet Affiliator
    public function payoutAffiliator(Request $request)
    {
        $request->validate([
            'affiliator_id' => 'required|exists:users,id'
        ]);

        $affiliatorId = $request->affiliator_id;

        try {
            DB::beginTransaction();

            // Cari semua order dari affiliator ini yang sukses dibayar tapi komisinya belum dicairkan
            $orders = Order::where('affiliator_id', $affiliatorId)
                           ->where('payment_status', 'paid')
                           ->where('is_affiliate_commission_cashed_out', false)
                           ->lockForUpdate() // Kunci row agar tidak ada double-payout (Race Condition)
                           ->get();

            if ($orders->isEmpty()) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak ada komisi baru yang bisa dicairkan.'], 400);
            }

            // Hitung total uangnya
            $totalCommission = $orders->sum('affiliate_commission_amount');

            // Cari dompet affiliator (Jika belum ada, buatkan otomatis)
            $wallet = Wallet::firstOrCreate(['user_id' => $affiliatorId]);
            
            // Tambah Saldo
            $wallet->increment('balance', $totalCommission);

            // Catat Mutasi sebagai Bukti Pemasukan
            WalletMutation::create([
                'wallet_id'   => $wallet->id,
                'amount'      => $totalCommission,
                'type'        => 'credit',
                'description' => 'Pencairan komisi affiliate dari ' . $orders->count() . ' tiket.'
            ]);

            // Tandai order-order tersebut bahwa komisinya sudah lunas dibayarkan
            Order::whereIn('id', $orders->pluck('id'))->update(['is_affiliate_commission_cashed_out' => true]);

            DB::commit();

            return response()->json([
                'success' => true, 
                'message' => 'Pencairan berhasil! Dana telah masuk ke Wallet Affiliator.', 
                'data' => [
                    'total_payout' => $totalCommission,
                    'tickets_sold' => $orders->sum('quantity')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.', 'error' => $e->getMessage()], 500);
        }
    }
}