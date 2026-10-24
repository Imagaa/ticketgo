<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketTier;
use App\Models\Event;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create payment (Custom payment page - get payment instructions)
     */
    public function create(Request $request, string $orderId)
    {
        $request->validate([
            'payment_method' => 'required|string',
            'bank'           => 'nullable|string',
        ]);

        $order = Order::with(['event', 'user'])->where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // [PENGAMANAN]: Pastikan HANYA status pending_payment yang bisa buat tagihan
        if ($order->status !== 'pending_payment') {
            return response()->json(['message' => 'Order is not in pending state'], 400);
        }

        // 1. Ambil data payment method dari Database
        $paymentMethod = \App\Models\PaymentMethod::where('payment_type', $request->payment_method)
            ->where(function($query) use ($request) {
                if ($request->bank) {
                    $query->where('bank', $request->bank);
                } else {
                    $query->whereNull('bank');
                }
            })->first();

        if (!$paymentMethod) {
            return response()->json(['message' => 'Metode pembayaran tidak valid atau sedang dinonaktifkan'], 400);
        }

        // 2. Kalkulasi Ulang Biaya Secara Aman
        $platformFee = $order->subtotal * ($order->event->admin_fee_percentage / 100);
        $localTax = $order->subtotal * ($order->event->local_tax_percentage / 100);
        $baseTotal = $order->subtotal + $platformFee + $localTax;

        // Hitung Biaya Midtrans (Gateway Fee)
        $gatewayFee = $paymentMethod->flat_fee + ($baseTotal * ($paymentMethod->percent_fee / 100));

        // 3. Update Order dengan Total Akhir (GUNAKAN ROUND)
        $finalAdminFee = round($platformFee + $localTax + $gatewayFee);
        $finalTotal = round($order->subtotal + $finalAdminFee);

        $order->update([
            'admin_fee' => $finalAdminFee,
            'total'     => $finalTotal,
        ]);

        try {
            // Daftarkan Tagihan ke Midtrans
            $response = $this->paymentService->createCharge(
                $order,
                $request->payment_method,
                ['bank' => $request->bank]
            );

            // Ubah response menjadi array paksa agar aman dibaca
            $resData = json_decode(json_encode($response), true);

            // Simpan data respon dari Midtrans
            $order->update([
                'payment_token'   => $resData['transaction_id'] ?? null,
                'payment_url'     => $resData['actions'][0]['url'] ?? null, 
                'payment_details' => [
                    'va_numbers' => $resData['va_numbers'] ?? null,
                    'qr_string'  => $resData['qr_string'] ?? null,
                    'actions'    => $resData['actions'] ?? null,
                    'bill_key'   => $resData['bill_key'] ?? null,
                    'biller_code'=> $resData['biller_code'] ?? null,
                ]
            ]);

            return response()->json(['order' => $order]);

        } catch (\Exception $e) {
            return response()->json([
                'order'  => $order,
                'status' => 'pending', 
                'debug'  => $e->getMessage() // Opsional untuk log developer
            ]);
        }
    }

    /**
     * Check payment status
     */
    public function status(Request $request, string $orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // =========================================================================
        // [PENGAMANAN STATE MACHINE]: Jika status sudah final, JANGAN hit Midtrans!
        // =========================================================================
        if (in_array($order->status, ['confirmed', 'cancelled'])) {
            $simulatedStatus = $order->payment_status === 'paid' ? 'settlement' : 'cancel';
            return response()->json([
                'order'  => $order,
                'status' => $simulatedStatus, 
            ]);
        }

        try {
            $midtransSearchId = $order->payment_token ?: $order->order_code;
            $status = $this->paymentService->checkStatus($midtransSearchId);
            
            $transactionStatus = $status->transaction_status ?? 'pending';

            // Jika lunas, update status DAN Cetak Tiket
            if (in_array($transactionStatus, ['capture', 'settlement'])) {
                $order->update([
                    'status'         => 'confirmed', 
                    'payment_status' => 'paid',      
                    'paid_at'        => now(),
                ]);

                // ============================================================
                // GENERATE TIKET JIKA FRONTEND MENGECEK STATUS LUNAS
                // ============================================================
                $existingTickets = Ticket::where('order_id', $order->id)->count();

                if ($existingTickets == 0) {
                    $user = $request->user();
                    $quantity = $order->quantity;
                    $ticketsToInsert = [];
                    $now = now();

                    for ($i = 0; $i < $quantity; $i++) {
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
                    Ticket::insert($ticketsToInsert);
                }

            } else if (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                $order->update([
                    'status'         => 'cancelled',
                    'payment_status' => 'failed',
                ]);

                // ============================================================
                // REFUND KUOTA KURSI JIKA BATAL
                // ============================================================
                if ($order->ticket_tier_id) {
                    TicketTier::where('id', $order->ticket_tier_id)->increment('available_seats', $order->quantity);
                    Event::where('id', $order->event_id)->increment('available_seats', $order->quantity);
                }
            }

            return response()->json([
                'order'  => $order,
                'status' => $transactionStatus, 
            ]);

        } catch (\Exception $e) {
            $errorMsg = strtolower($e->getMessage());
            // Jika transaksi belum dibayar di Midtrans (404)
            if (str_contains($errorMsg, '404') || str_contains($errorMsg, 'not found')) {
                return response()->json([
                    'order'  => $order,
                    'status' => 'pending', 
                ]);
            }

            return response()->json([
                'message' => 'Failed to check status',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format payment instructions for custom UI
     */
    private function formatInstructions($charge)
    {
        $instructions = [
            'payment_type' => $charge->payment_type,
            'transaction_id' => $charge->transaction_id,
        ];

        // Bank Transfer (BCA, BNI, BRI, Permata)
        if ($charge->payment_type === 'bank_transfer') {
            $vaNumbers = $charge->va_numbers ?? [];
            if (!empty($vaNumbers)) {
                $instructions['bank'] = $vaNumbers[0]->bank;
                $instructions['va_number'] = $vaNumbers[0]->va_number;
                
                // Add bank-specific instructions
                $instructions['how_to_pay'] = $this->getBankInstructions($vaNumbers[0]->bank);
            }
        }

        // Mandiri Bill Payment (echannel)
        if ($charge->payment_type === 'echannel') {
            $instructions['bank'] = 'mandiri';
            $instructions['bill_key'] = $charge->bill_key;
            $instructions['biller_code'] = $charge->biller_code;
            $instructions['how_to_pay'] = [
                'ATM' => [
                    '1. Pilih menu Bayar/Beli',
                    '2. Pilih menu Multi Payment',
                    '3. Input Biller Code: ' . $charge->biller_code,
                    '4. Input Bill Key: ' . $charge->bill_key,
                    '5. Konfirmasi pembayaran',
                ],
            ];
        }

        // GoPay
        if ($charge->payment_type === 'gopay') {
            $actions = $charge->actions ?? [];
            foreach ($actions as $action) {
                if ($action->name === 'generate-qr-code') {
                    $instructions['qr_code'] = $action->url;
                }
                if ($action->name === 'deeplink-redirect') {
                    $instructions['deeplink'] = $action->url;
                }
            }
        }

        // QRIS
        if ($charge->payment_type === 'qris') {
            $actions = $charge->actions ?? [];
            foreach ($actions as $action) {
                if ($action->name === 'generate-qr-code') {
                    $instructions['qr_code'] = $action->url;
                }
            }
        }

        // ShopeePay
        if ($charge->payment_type === 'shopeepay') {
            $actions = $charge->actions ?? [];
            foreach ($actions as $action) {
                if ($action->name === 'deeplink-redirect') {
                    $instructions['deeplink'] = $action->url;
                }
            }
        }

        return $instructions;
    }

    /**
     * Get bank-specific payment instructions
     */
    private function getBankInstructions($bank)
    {
        $instructions = [
            'bca' => [
                'ATM' => [
                    '1. Masukkan kartu ATM dan PIN',
                    '2. Pilih menu Transaksi Lainnya',
                    '3. Pilih menu Transfer',
                    '4. Pilih ke Rekening BCA Virtual Account',
                    '5. Input nomor Virtual Account',
                    '6. Konfirmasi pembayaran',
                ],
                'Mobile' => [
                    '1. Login ke BCA Mobile',
                    '2. Pilih menu m-BCA',
                    '3. Pilih menu m-Transfer',
                    '4. Pilih BCA Virtual Account',
                    '5. Input nomor Virtual Account',
                    '6. Konfirmasi pembayaran',
                ],
            ],
            'bni' => [
                'ATM' => [
                    '1. Masukkan kartu ATM dan PIN',
                    '2. Pilih menu Lainnya',
                    '3. Pilih menu Transfer',
                    '4. Pilih Rekening Tabungan',
                    '5. Input nomor Virtual Account',
                    '6. Konfirmasi pembayaran',
                ],
            ],
            'bri' => [
                'ATM' => [
                    '1. Masukkan kartu ATM dan PIN',
                    '2. Pilih menu Transaksi Lain',
                    '3. Pilih menu Pembayaran',
                    '4. Pilih menu Lainnya',
                    '5. Pilih menu BRIVA',
                    '6. Input nomor Virtual Account',
                    '7. Konfirmasi pembayaran',
                ],
            ],
            'permata' => [
                'ATM' => [
                    '1. Masukkan kartu ATM dan PIN',
                    '2. Pilih menu Transaksi Lainnya',
                    '3. Pilih menu Pembayaran',
                    '4. Pilih menu Pembayaran Lainnya',
                    '5. Pilih menu Virtual Account',
                    '6. Input nomor Virtual Account',
                    '7. Konfirmasi pembayaran',
                ],
            ],
        ];

        return $instructions[$bank] ?? [];
    }
}