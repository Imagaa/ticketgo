<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentMethodController extends Controller
{
    public function index()
    {
        // Menggunakan all() agar lebih aman dari error sorting
        $methods = PaymentMethod::all();
        return response()->json($methods);
    }

    public function toggleActive(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['is_active' => !$paymentMethod->is_active]);
        return response()->json([
            'message' => $paymentMethod->is_active ? 'Metode pembayaran diaktifkan.' : 'Metode pembayaran dimatikan.',
        ]);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $request->validate([
            'flat_fee' => 'numeric',
            'percent_fee' => 'numeric',
        ]);

        $paymentMethod->update($validated);
        return response()->json(['message' => 'Biaya admin berhasil diperbarui.']);
    }

    /**
     * HEALTH CHECK: Simulasi tembak ke Midtrans
     */
    public function healthCheck(PaymentMethod $paymentMethod)
    {
        $serverKey = config('services.midtrans.server_key');
        $isProduction = config('services.midtrans.is_production');
        $baseUrl = $isProduction ? 'https://api.midtrans.com/v2/charge' : 'https://api.sandbox.midtrans.com/v2/charge';

        $orderId = 'PING-' . time() . '-' . rand(100, 999);

        // Payload dasar super lengkap untuk mengakomodasi PayLater
        $payload = [
            'payment_type' => $paymentMethod->payment_type,
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => 10000
            ],
            'item_details' => [
                [
                    'id' => 'PING-ITEM',
                    'price' => 10000,
                    'quantity' => 1,
                    'name' => 'Ping Check Midtrans'
                ]
            ],
            'customer_details' => [
                'first_name' => 'Health',
                'last_name' => 'Check',
                'email' => 'ping@ticketgo.test',
                'phone' => '081234567890',
                'shipping_address' => [
                    'first_name' => 'Health',
                    'last_name' => 'Check',
                    'address' => 'Jl. Jendral Sudirman',
                    'city' => 'Jakarta',
                    'postal_code' => '12190',
                    'phone' => '081234567890',
                    'country_code' => 'IDN'
                ]
            ]
        ];

        // --- INJEKSI PARAMETER KHUSUS WAJIB MIDTRANS ---
        if ($paymentMethod->payment_type === 'bank_transfer') {
            $payload['bank_transfer'] = ['bank' => $paymentMethod->bank];
        } elseif ($paymentMethod->payment_type === 'echannel') {
            $payload['echannel'] = ['bill_info1' => 'Ping', 'bill_info2' => 'Check'];
        } elseif ($paymentMethod->payment_type === 'shopeepay') {
            $payload['shopeepay'] = ['callback_url' => 'https://example.com/ping-callback'];
        } elseif ($paymentMethod->payment_type === 'cstore') {
            $payload['cstore'] = [
                'store' => $paymentMethod->bank,
                'message' => 'Ping Check'
            ];
        }

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($baseUrl, $payload);

            $result = $response->json();
            $statusCode = $result['status_code'] ?? null;
            $statusMessage = $result['status_message'] ?? 'Unknown Error';

            // Khusus Kartu Kredit, akan ditolak (400) karena kita tidak mengirim Token Kartu Asli.
            // Tapi jika Midtrans menjawab "token is required", itu berarti koneksi normal!
            if (in_array($statusCode, ['200', '201']) || ($paymentMethod->payment_type === 'credit_card' && $statusCode === '400')) {
                return response()->json([
                    'status' => 'healthy',
                    'message' => '✅ Aktif & Normal! Midtrans menerima koneksi ini.',
                    'raw' => $statusMessage
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => '❌ Ditolak: ' . $statusMessage,
                    'raw' => $statusMessage
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghubungi Midtrans: ' . $e->getMessage()
            ], 500);
        }
    }
}