<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketTier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('event')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $orders]);
    }

    public function show(Request $request, string $id)
    {
        $order = Order::with(['event', 'user'])->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json(['data' => $order]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id'       => 'required|exists:events,id',
            'ticket_tier_id' => 'required|exists:ticket_tiers,id',
            'quantity'       => 'required|integer|min:1|max:5',
            'custom_fields'  => 'nullable|array',
            // TAMBAHAN: Menangkap ID Affiliator jika pembeli pakai link UTM/Ref
            'affiliator_id'  => 'nullable|exists:users,id', 
        ]);

        try {
            DB::beginTransaction();

            $event = Event::where('id', $validated['event_id'])->lockForUpdate()->firstOrFail();
            $tier = TicketTier::where('id', $validated['ticket_tier_id'])->where('event_id', $event->id)->lockForUpdate()->firstOrFail();

            if ($tier->available_seats < $validated['quantity']) {
                $heldTicketsCount = Order::where('ticket_tier_id', $tier->id)
                    ->where('status', 'pending_payment')
                    ->where('created_at', '>=', now()->subMinutes(15))
                    ->sum('quantity');

                if ($heldTicketsCount > 0) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Sisa tiket sedang dalam proses pembayaran oleh pembeli lain. Silakan tunggu beberapa menit jika mereka batal.',
                        'is_held' => true 
                    ], 409);
                }

                DB::rollBack();
                return response()->json([
                    'message' => 'Kapasitas tiket tidak mencukupi atau sudah Habis Terjual (Sold Out).',
                    'is_held' => false
                ], 400);
            }

            $pricePerTicket = $tier->price;
            $subtotal = $pricePerTicket * $validated['quantity'];

            $platformFee = $subtotal * ($event->admin_fee_percentage / 100);
            $localTax = $subtotal * ($event->local_tax_percentage / 100);
            $baseTotal = $subtotal + $platformFee + $localTax;
            
            $isFree = $baseTotal <= 0;

            // ================= LOGIKA AFFILIATE =================
            $affiliatorId = null;
            $affiliateCommission = 0;

            // Cek jika ada request affiliator_id dari frontend, DAN event mengizinkan affiliate, DAN EO sudah bayar fitur ini
            if (!empty($validated['affiliator_id'])) {
                if ($event->is_affiliate_enabled && in_array($event->affiliate_activation_status, ['paid', 'postpaid'])) {
                    $affiliatorId = $validated['affiliator_id'];
                    // Hitung nominal komisi: (Subtotal Harga Tiket * Persentase Komisi) / 100
                    $affiliateCommission = $subtotal * ((float) $event->affiliate_commission_rate / 100);
                }
            }
            // =====================================================

            $tier->decrement('available_seats', $validated['quantity']);
            $event->decrement('available_seats', $validated['quantity']);

            $orderCode = 'ORD-' . strtoupper(Str::random(8));

            $order = Order::create([
                'user_id'          => $request->user()->id,
                'event_id'         => $event->id,
                'ticket_tier_id'   => $tier->id,
                'order_code'       => $orderCode,
                'quantity'         => $validated['quantity'],
                'price_per_ticket' => $pricePerTicket,
                'subtotal'         => $subtotal,
                'admin_fee'        => $platformFee + $localTax,
                'total'            => $baseTotal,
                'status'           => $isFree ? 'confirmed' : 'pending_payment',
                'payment_status'   => $isFree ? 'paid' : 'pending',
                'paid_at'          => $isFree ? now() : null,
                'custom_fields_responses' => $validated['custom_fields'] ?? null,
                
                // SIMPAN DATA KOMISI AFFILIATE KE DATABASE
                'affiliator_id'                => $affiliatorId,
                'affiliate_commission_amount'  => $affiliateCommission,
                'is_affiliate_commission_cashed_out' => false,

                'ticket_details'   => [
                    'tier_name' => $tier->name,
                    'tier_type' => $tier->type,
                ]
            ]);

            if ($isFree) {
                $user = $request->user();
                $ticketsToInsert = [];
                $now = now();

                for ($i = 0; $i < $validated['quantity']; $i++) {
                    $ticketCode = 'BTX-' . strtoupper(Str::random(7));

                    $ticketsToInsert[] = [
                        'id'               => (string) Str::uuid(),
                        'order_id'         => $order->id,
                        'event_id'         => $order->event_id,
                        'user_id'          => $order->user_id,
                        'ticket_tier_id'   => $order->ticket_tier_id,
                        'ticket_code'      => $ticketCode,
                        'attendee_name'    => $user->name ?? 'Guest',
                        'attendee_email'   => $user->email ?? 'no-email',
                        'status'           => 'valid',
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
                \App\Models\Ticket::insert($ticketsToInsert);
            }

            DB::commit();

            return response()->json([
                'message' => $isFree ? 'Tiket berhasil diklaim secara gratis!' : 'Tiket berhasil di-hold (dipesan). Segera selesaikan pembayaran.',
                'data'    => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses pesanan.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request, string $id)
    {
        $order = Order::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        if ($order->status === 'pending_payment') {
            DB::transaction(function () use ($order) {
                $order->update([
                    'status' => 'cancelled',
                    'payment_status' => 'failed'
                ]);

                // KEMBALIKAN KUOTA KARENA DIBATALKAN
                if ($order->ticket_tier_id) {
                    TicketTier::where('id', $order->ticket_tier_id)->increment('available_seats', $order->quantity);
                    Event::where('id', $order->event_id)->increment('available_seats', $order->quantity);
                }
            });

            return response()->json(['message' => 'Pesanan berhasil dibatalkan dan tiket dikembalikan.']);
        }

        return response()->json(['message' => 'Hanya pesanan yang belum dibayar yang dapat dibatalkan.'], 400);
    }
}