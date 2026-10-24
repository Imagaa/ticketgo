<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // Syarat: EventSeeder + UserSeeder sudah berjalan

        $buyer1     = User::where('email', 'buyer1@ticketgo.test')->firstOrFail();
        $buyer2     = User::where('email', 'buyer2@ticketgo.test')->firstOrFail();
        $buyer3     = User::where('email', 'buyer3@ticketgo.test')->firstOrFail();
        $affiliator = User::where('email', 'affiliate@ticketgo.test')->firstOrFail();

        $event1 = Event::where('slug', 'konser-dewa-19-jakarta-2026')->firstOrFail();
        $event2 = Event::where('slug', 'seminar-tech-indonesia-2026')->firstOrFail();

        $tierEarlyBird = TicketTier::where('event_id', $event1->id)->where('name', 'Early Bird')->firstOrFail();
        $tierRegular   = TicketTier::where('event_id', $event1->id)->where('name', 'Regular')->firstOrFail();
        $tierVip       = TicketTier::where('event_id', $event1->id)->where('name', 'VIP')->firstOrFail();
        $tierSeminar   = TicketTier::where('event_id', $event2->id)->where('name', 'Peserta Umum')->firstOrFail();

        // ══════════════════════════════════════════════════════════════
        // ORDER 1: PAID — Buyer1, Event1, 2 tiket Regular (normal purchase)
        // ══════════════════════════════════════════════════════════════
        if (!Order::where('order_code', 'ORD-DEMO-001')->exists()) {
            $qty1        = 2;
            $price1      = 450000;
            $subtotal1   = $qty1 * $price1;
            $adminFee1   = $subtotal1 * 0.05;
            $total1      = $subtotal1 + $adminFee1;

            $order1 = Order::create([
                'user_id'                        => $buyer1->id,
                'event_id'                       => $event1->id,
                'ticket_tier_id'                 => $tierRegular->id,
                'order_code'                     => 'ORD-DEMO-001',
                'quantity'                       => $qty1,
                'price_per_ticket'               => $price1,
                'subtotal'                       => $subtotal1,
                'admin_fee'                      => $adminFee1,
                'total'                          => $total1,
                'status'                         => 'confirmed',
                'payment_status'                 => 'paid',
                'payment_method'                 => 'bca_va',
                'paid_at'                        => now()->subDays(5),
                'expired_at'                     => now()->subDays(5)->addHour(),
                'custom_fields_responses'        => [
                    'Nomor KTP / NIK' => '3374010101900001',
                    'Domisili Kota'   => 'Semarang',
                ],
                'ticket_details'                 => [],
                'affiliator_id'                  => null,
                'affiliate_commission_amount'    => 0,
                'is_affiliate_commission_cashed_out' => false,
            ]);

            // Buat tiket untuk order1
            foreach (range(1, $qty1) as $i) {
                Ticket::create([
                    'order_id'       => $order1->id,
                    'event_id'       => $event1->id,
                    'ticket_tier_id' => $tierRegular->id,
                    'user_id'        => $buyer1->id,
                    'ticket_code'    => 'TIX-DEMO-' . strtoupper(Str::random(8)),
                    'attendee_name'  => $buyer1->name,
                    'attendee_email' => $buyer1->email,
                    'status'         => 'valid',
                ]);
            }
        }

        // ══════════════════════════════════════════════════════════════
        // ORDER 2: PAID — Buyer2, Event1, 1 tiket VIP via AFFILIATOR
        // Skenario: test affiliate commission tracking
        // ══════════════════════════════════════════════════════════════
        if (!Order::where('order_code', 'ORD-DEMO-002')->exists()) {
            $qty2        = 1;
            $price2      = 1200000;
            $subtotal2   = $qty2 * $price2;
            $adminFee2   = $subtotal2 * 0.05;
            $commission2 = $subtotal2 * 0.05; // 5% affiliate commission
            $total2      = $subtotal2 + $adminFee2;

            $order2 = Order::create([
                'user_id'                        => $buyer2->id,
                'event_id'                       => $event1->id,
                'ticket_tier_id'                 => $tierVip->id,
                'order_code'                     => 'ORD-DEMO-002',
                'quantity'                       => $qty2,
                'price_per_ticket'               => $price2,
                'subtotal'                       => $subtotal2,
                'admin_fee'                      => $adminFee2,
                'total'                          => $total2,
                'status'                         => 'confirmed',
                'payment_status'                 => 'paid',
                'payment_method'                 => 'gopay',
                'paid_at'                        => now()->subDays(3),
                'expired_at'                     => now()->subDays(3)->addHour(),
                'custom_fields_responses'        => [
                    'Nomor KTP / NIK' => '3374020202900002',
                    'Domisili Kota'   => 'Yogyakarta',
                ],
                'ticket_details'                 => [],
                'affiliator_id'                  => $affiliator->id,
                'affiliate_commission_amount'    => $commission2,
                'is_affiliate_commission_cashed_out' => false,
            ]);

            Ticket::create([
                'order_id'       => $order2->id,
                'event_id'       => $event1->id,
                'ticket_tier_id' => $tierVip->id,
                'user_id'        => $buyer2->id,
                'ticket_code'    => 'TIX-DEMO-' . strtoupper(Str::random(8)),
                'attendee_name'  => $buyer2->name,
                'attendee_email' => $buyer2->email,
                'status'         => 'valid',
            ]);
        }

        // ══════════════════════════════════════════════════════════════
        // ORDER 3: PAID — Buyer1, Event2 (Seminar)
        // Skenario: buyer yang punya tiket di 2 event berbeda
        // ══════════════════════════════════════════════════════════════
        if (!Order::where('order_code', 'ORD-DEMO-003')->exists()) {
            $qty3      = 1;
            $price3    = 150000;
            $subtotal3 = $qty3 * $price3;
            $adminFee3 = $subtotal3 * 0.03; // EO VIP rate
            $total3    = $subtotal3 + $adminFee3;

            $order3 = Order::create([
                'user_id'                        => $buyer1->id,
                'event_id'                       => $event2->id,
                'ticket_tier_id'                 => $tierSeminar->id,
                'order_code'                     => 'ORD-DEMO-003',
                'quantity'                       => $qty3,
                'price_per_ticket'               => $price3,
                'subtotal'                       => $subtotal3,
                'admin_fee'                      => $adminFee3,
                'total'                          => $total3,
                'status'                         => 'confirmed',
                'payment_status'                 => 'paid',
                'payment_method'                 => 'mandiri_va',
                'paid_at'                        => now()->subDays(2),
                'expired_at'                     => now()->subDays(2)->addHour(),
                'custom_fields_responses'        => ['Nomor Telepon' => '081234567890'],
                'ticket_details'                 => [],
                'affiliator_id'                  => null,
                'affiliate_commission_amount'    => 0,
                'is_affiliate_commission_cashed_out' => false,
            ]);

            Ticket::create([
                'order_id'       => $order3->id,
                'event_id'       => $event2->id,
                'ticket_tier_id' => $tierSeminar->id,
                'user_id'        => $buyer1->id,
                'ticket_code'    => 'TIX-DEMO-' . strtoupper(Str::random(8)),
                'attendee_name'  => $buyer1->name,
                'attendee_email' => $buyer1->email,
                'status'         => 'valid',
            ]);
        }

        // ══════════════════════════════════════════════════════════════
        // ORDER 4: PENDING — Buyer3, Event1 (belum bayar)
        // Skenario: test tampilan "waiting payment" + halaman payment
        // ══════════════════════════════════════════════════════════════
        if (!Order::where('order_code', 'ORD-DEMO-004')->exists()) {
            $qty4      = 2;
            $price4    = 450000;
            $subtotal4 = $qty4 * $price4;
            $adminFee4 = $subtotal4 * 0.05;
            $total4    = $subtotal4 + $adminFee4;

            Order::create([
                'user_id'                        => $buyer3->id,
                'event_id'                       => $event1->id,
                'ticket_tier_id'                 => $tierRegular->id,
                'order_code'                     => 'ORD-DEMO-004',
                'quantity'                       => $qty4,
                'price_per_ticket'               => $price4,
                'subtotal'                       => $subtotal4,
                'admin_fee'                      => $adminFee4,
                'total'                          => $total4,
                'status'                         => 'pending_payment',
                'payment_status'                 => 'pending',
                'payment_method'                 => null,
                'payment_url'                    => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/dummy-token-pending',
                'paid_at'                        => null,
                'expired_at'                     => now()->addMinutes(30), // Masih aktif 30 menit
                'custom_fields_responses'        => [
                    'Nomor KTP / NIK' => '3374030303900003',
                    'Domisili Kota'   => 'Semarang',
                ],
                'ticket_details'                 => [],
                'affiliator_id'                  => null,
                'affiliate_commission_amount'    => 0,
                'is_affiliate_commission_cashed_out' => false,
            ]);
        }

        // ══════════════════════════════════════════════════════════════
        // ORDER 5: EXPIRED — Buyer3, Event1 (waktu bayar habis)
        // Skenario: test edge case tampilan "expired"
        // ══════════════════════════════════════════════════════════════
        if (!Order::where('order_code', 'ORD-DEMO-005')->exists()) {
            $qty5      = 1;
            $price5    = 450000;
            $subtotal5 = $qty5 * $price5;
            $adminFee5 = $subtotal5 * 0.05;
            $total5    = $subtotal5 + $adminFee5;

            Order::create([
                'user_id'                        => $buyer3->id,
                'event_id'                       => $event1->id,
                'ticket_tier_id'                 => $tierRegular->id,
                'order_code'                     => 'ORD-DEMO-005',
                'quantity'                       => $qty5,
                'price_per_ticket'               => $price5,
                'subtotal'                       => $subtotal5,
                'admin_fee'                      => $adminFee5,
                'total'                          => $total5,
                'status'                         => 'cancelled',
                'payment_status'                 => 'expired',
                'payment_method'                 => null,
                'paid_at'                        => null,
                'expired_at'                     => now()->subHours(2), // Sudah lewat
                'custom_fields_responses'        => [],
                'ticket_details'                 => [],
                'affiliator_id'                  => null,
                'affiliate_commission_amount'    => 0,
                'is_affiliate_commission_cashed_out' => false,
            ]);
        }

        // ══════════════════════════════════════════════════════════════
        // ORDER 6: CANCELLED — Buyer2, Event2 (dibatalkan user)
        // Skenario: test tampilan "cancelled" + riwayat transaksi
        // ══════════════════════════════════════════════════════════════
        if (!Order::where('order_code', 'ORD-DEMO-006')->exists()) {
            $qty6      = 2;
            $price6    = 150000;
            $subtotal6 = $qty6 * $price6;
            $adminFee6 = $subtotal6 * 0.03;
            $total6    = $subtotal6 + $adminFee6;

            Order::create([
                'user_id'                        => $buyer2->id,
                'event_id'                       => $event2->id,
                'ticket_tier_id'                 => $tierSeminar->id,
                'order_code'                     => 'ORD-DEMO-006',
                'quantity'                       => $qty6,
                'price_per_ticket'               => $price6,
                'subtotal'                       => $subtotal6,
                'admin_fee'                      => $adminFee6,
                'total'                          => $total6,
                'status'                         => 'cancelled',
                'payment_status'                 => 'cancelled',
                'payment_method'                 => null,
                'paid_at'                        => null,
                'expired_at'                     => now()->subDays(1),
                'cancellation_reason'            => 'Dibatalkan oleh pembeli',
                'custom_fields_responses'        => [],
                'ticket_details'                 => [],
                'affiliator_id'                  => null,
                'affiliate_commission_amount'    => 0,
                'is_affiliate_commission_cashed_out' => false,
            ]);
        }

        $this->command->info('[6/7] Orders selesai: 3 paid (+ tiket), 1 pending, 1 expired, 1 cancelled.');
    }
}
