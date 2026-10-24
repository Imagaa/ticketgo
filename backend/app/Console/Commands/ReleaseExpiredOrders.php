<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\TicketTier;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:release-expired';

    /**
     * The console command description.
     */
    protected $description = 'Membatalkan order yang pending_payment lebih dari 15 menit dan mengembalikan kuota tiket';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mencari pesanan kadaluarsa...');

        // Cari order yang pending lebih dari 15 menit
        $expiredOrders = Order::where('status', 'pending_payment')
            ->where('created_at', '<=', now()->subMinutes(15))
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('Tidak ada pesanan yang kadaluarsa saat ini.');
            return;
        }

        $count = 0;

        foreach ($expiredOrders as $order) {
            DB::transaction(function () use ($order, &$count) {
                // Batalkan Order
                $order->update([
                    'status'         => 'cancelled',
                    'payment_status' => 'expired',
                ]);

                // Kembalikan Kuota (Auto-Release)
                if ($order->ticket_tier_id) {
                    TicketTier::where('id', $order->ticket_tier_id)->increment('available_seats', $order->quantity);
                    Event::where('id', $order->event_id)->increment('available_seats', $order->quantity);
                }

                $count++;
            });
        }

        $this->info("Berhasil merilis {$count} pesanan kadaluarsa dan mengembalikan kuotanya ke publik.");
        Log::info("Cron Job: Released {$count} expired orders.");
    }
}