<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceArtistController extends Controller
{
    // Transfer Manual dari Kas Platform ke Wallet Artis
    public function transferToArtist(Request $request)
    {
        $request->validate([
            'artist_id'   => 'required|exists:users,id',
            'amount'      => 'required|numeric|min:1',
            'description' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            // Cari atau Buat dompet Artis
            $wallet = Wallet::firstOrCreate(['user_id' => $request->artist_id]);
            
            // Tambahkan Uang
            $wallet->increment('balance', $request->amount);

            // Catat Mutasi
            WalletMutation::create([
                'wallet_id'   => $wallet->id,
                'amount'      => $request->amount,
                'type'        => 'credit',
                'description' => $request->description ?? 'Transfer royalty dari Finance TicketGo.'
            ]);

            // (Opsional) Jika sistem TicketGo memiliki Wallet Platform (SuperAdmin), 
            // kita bisa melakukan decrement balance di sini. Namun untuk sekarang kita fokus ke Wallet Artis.

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfer berhasil sebesar Rp ' . number_format($request->amount, 0, ',', '.') . ' ke dompet Artis.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Transfer gagal.', 'error' => $e->getMessage()], 500);
        }
    }
}