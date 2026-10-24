<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Transaction; 
use App\Models\Order;

class PaymentService
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createCharge(Order $order, string $paymentMethod, array $options = [])
    {
        $midtransOrderId = $order->order_code . '-' . time();    
        
        // 1. Ambil harga tiket asli (tidak boleh diutak-atik)
        $pricePerTicket = (int) round($order->price_per_ticket);
        $ticketQuantity = (int) $order->quantity;
        
        // 2. Total harga murni tiket (Qty * Harga per Tiket)
        $pureTicketsTotal = $pricePerTicket * $ticketQuantity;

        // 3. Ambil gross_amount (Total Tagihan Final) dari database yang sudah dibulatkan
        $grossAmount = (int) round($order->total);

        // 4. THE MAGIC: Hitung Admin Fee dari SISA gross_amount dikurangi pureTicketsTotal
        // Ini memastikan berapapun perbedaan pembulatannya, akan 'diserap' oleh Admin Fee.
        $calculatedAdminFee = $grossAmount - $pureTicketsTotal;

        $params = [
            'payment_type' => $paymentMethod,
            'transaction_details' => [
                'order_id'     => $midtransOrderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => substr($order->user->name, 0, 50),
                'email'      => $order->user->email,
            ],
            'item_details' => [
                [
                    'id'       => (string) $order->event->id,
                    'price'    => $pricePerTicket,
                    'quantity' => $ticketQuantity,
                    'name'     => substr($order->event->title, 0, 50),
                ]
            ],
        ];

        // 5. Masukkan Admin Fee (yang sudah menyerap sisa selisih) jika ada
        if ($calculatedAdminFee > 0) {
            $params['item_details'][] = [
                'id'       => 'admin_fee',
                'price'    => $calculatedAdminFee,
                'quantity' => 1,
                'name'     => 'Pajak & Biaya Admin',
            ];
        } elseif ($calculatedAdminFee < 0) {
            // Jaga-jaga jika ada sistem Kupon/Diskon di masa depan
             $params['item_details'][] = [
                'id'       => 'discount',
                'price'    => $calculatedAdminFee, // Nilainya negatif
                'quantity' => 1,
                'name'     => 'Diskon',
            ];
        }

        // ================= PAYLOAD SPESIFIK METODE PEMBAYARAN =================
        if ($paymentMethod === 'bank_transfer') {
            $params['bank_transfer'] = ['bank' => $options['bank'] ?? 'bca'];
        } elseif ($paymentMethod === 'echannel') {
            $params['echannel'] = [
                'bill_info1' => 'Pembayaran:',
                'bill_info2' => substr($order->event->title, 0, 50),
            ];
        } elseif ($paymentMethod === 'gopay') {
            $params['gopay'] = [
                'enable_callback' => true,
                'callback_url'    => config('app.frontend_url') . '/payment/success',
            ];
        } elseif ($paymentMethod === 'qris') {
            $params['qris'] = ['acquirer' => 'gopay'];
        } elseif ($paymentMethod === 'shopeepay') {
            $params['shopeepay'] = [
                'callback_url' => config('app.frontend_url') . '/payment/success',
            ];
        }

        try {
            return CoreApi::charge($params);
        } catch (\Exception $e) {
            throw new \Exception('Payment creation failed: ' . $e->getMessage());
        }
    }

    public function checkStatus(string $orderCode)
    {
        try {
            return Transaction::status($orderCode); // <--- 2. UBAH CoreApi MENJADI Transaction
        } catch (\Exception $e) {
            throw new \Exception('Status check failed: ' . $e->getMessage());
        }
    }

    public function cancel(string $orderCode)
    {
        try {
            return Transaction::cancel($orderCode); // <--- 3. UBAH CoreApi MENJADI Transaction
        } catch (\Exception $e) {
            throw new \Exception('Cancel payment failed: ' . $e->getMessage());
        }
    }
}