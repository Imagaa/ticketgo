<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Event;
use App\Models\TicketTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * Handle Midtrans payment notification webhook
     */
    public function midtrans(Request $request)
    {
        try {
            $data = $request->all();
            
            // Verify Midtrans signature
            if (! $this->verifySignature($data)) {
                Log::warning('Invalid Midtrans webhook signature', [
                    'ip'                 => $request->ip(),
                    'order_id'           => $data['order_id'] ?? 'unknown',
                    'signature_received' => $data['signature_key'] ?? 'none',
                ]);

                return response()->json([
                    'message' => 'Invalid signature',
                ], 403);
            }

            $orderId = $data['order_id'] ?? null;
            $transactionStatus = $data['transaction_status'] ?? null;
            $fraudStatus = $data['fraud_status'] ?? null;

            Log::info('Midtrans Webhook Received', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus,
                'payment_type' => $data['payment_type'] ?? null,
            ]);

            if (!$orderId || !$transactionStatus) {
                return response()->json(['message' => 'Invalid webhook data'], 400);
            }

            // Pisahkan ORDER_CODE ASLI dari Timestamp Midtrans (misal: ORD-XYZ-1234 -> ORD-XYZ)
            $originalOrderCode = $orderId;
            if (preg_match('/^(ORD-[A-Z0-9]+)-\d+$/', $orderId, $matches)) {
                $originalOrderCode = $matches[1];
            }

            // Gunakan lockForUpdate agar Webhook tidak memproses order yang sama dua kali secara bersamaan
            DB::beginTransaction();
            
            $order = Order::where('order_code', $originalOrderCode)->lockForUpdate()->first();

            if (!$order) {
                DB::rollBack();
                Log::error('Order not found for webhook', ['parsed_id' => $originalOrderCode]);
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Jika order SUDAH berstatus akhir, abaikan webhook ini (Mencegah double processing)
            if (in_array($order->status, ['confirmed', 'cancelled'])) {
                DB::rollBack();
                return response()->json(['message' => 'Order already processed. Ignoring.']);
            }

            // Handle payment status
            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'accept') {
                    $this->updateOrderPaid($order);
                }
            } elseif ($transactionStatus == 'settlement') {
                $this->updateOrderPaid($order);
            } elseif ($transactionStatus == 'pending') {
                $order->update([
                    'payment_status' => 'pending',
                    'status' => 'pending_payment',
                ]);
            } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
                // ========================================================
                // MANAJEMEN REFUND KUOTA OTOMATIS (MENCEGAH TIKET HANGUS)
                // ========================================================
                $order->update([
                    'payment_status' => $transactionStatus == 'expire' ? 'expired' : 'failed',
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Payment ' . $transactionStatus . ' by gateway',
                ]);

                // Kembalikan Kuota Kursi jika order dibatalkan oleh Midtrans
                if ($order->ticket_tier_id) {
                    TicketTier::where('id', $order->ticket_tier_id)->increment('available_seats', $order->quantity);
                    Event::where('id', $order->event_id)->increment('available_seats', $order->quantity);
                    Log::info('Seat quota refunded via webhook', ['order_id' => $order->id, 'quantity' => $order->quantity]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Webhook processed successfully',
                'order_id' => $orderId,
                'status' => $transactionStatus,
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Midtrans Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * =========================================================================
     * PENERBITAN FISIK TIKET (GENERATION) SAAT LUNAS
     * =========================================================================
     */
    private function updateOrderPaid(Order $order)
    {
        $order->update([
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        // CEK APAKAH TIKET SUDAH PERNAH DIGENERATE (Mencegah Duplicate Tickets)
        $existingTickets = Ticket::where('order_id', $order->id)->count();

        if ($existingTickets == 0) {
            $user = $order->user;
            $quantity = $order->quantity;
            $ticketsToInsert = [];
            $now = now();

            // Ekstrak nama dan tipe tiket dari JSON
            $tierName = 'Kategori Tiket';
            if (is_array($order->ticket_details) && isset($order->ticket_details['tier_name'])) {
                $tierName = $order->ticket_details['tier_name'];
            }

            for ($i = 0; $i < $quantity; $i++) {
                // Generate Ticket Code yang Unik (Misal: BTX-A1B2C3D)
                $ticketCode = 'BTX-' . strtoupper(Str::random(7));

                $ticketsToInsert[] = [
                    'id'               => Str::uuid(), // Karena model Ticket pakai HasUuids
                    'order_id'         => $order->id,
                    'event_id'         => $order->event_id,
                    'user_id'          => $order->user_id,
                    'ticket_tier_id'   => $order->ticket_tier_id,
                    'ticket_code'      => $ticketCode,
                    'attendee_name'    => $user->name ?? 'Guest', // Bisa diupdate nanti jika pakai Custom Form
                    'attendee_email'   => $user->email ?? 'no-email',
                    'status'           => 'valid',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            // Insert massal ke tabel tickets
            Ticket::insert($ticketsToInsert);
            Log::info("Generated {$quantity} tickets for order", ['order_id' => $order->id]);
        }

        Log::info('Order marked as paid and tickets distributed', [
            'order_id' => $order->id,
        ]);
    }

    /**
     * Verify Midtrans signature
     */
    private function verifySignature(array $data): bool
    {
        $serverKey = config('services.midtrans.server_key');

        $orderId      = $data['order_id']      ?? '';
        $statusCode   = $data['status_code']   ?? '';
        $grossAmount  = $data['gross_amount']  ?? '';
        $signatureKey = $data['signature_key'] ?? '';

        $expectedSignature = hash(
            'sha512',
            $orderId . $statusCode . $grossAmount . $serverKey
        );

        return hash_equals($expectedSignature, $signatureKey);
    }
}