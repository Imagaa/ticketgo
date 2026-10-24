<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [

            // ── VIRTUAL ACCOUNT ──────────────────────────────────────
            [
                'name'         => 'BCA Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank'         => 'bca',
                'flat_fee'     => 4000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'BNI Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank'         => 'bni',
                'flat_fee'     => 4000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'BRI Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank'         => 'bri',
                'flat_fee'     => 4000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'Mandiri Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank'         => 'mandiri',
                'flat_fee'     => 4000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'CIMB Niaga Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank'         => 'cimb',
                'flat_fee'     => 4000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'Permata Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank'         => 'permata',
                'flat_fee'     => 4000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'BSI Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank'         => 'bsi',
                'flat_fee'     => 4000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'SeaBank Virtual Account',
                'payment_type' => 'bank_transfer',
                'bank'         => 'seabank',
                'flat_fee'     => 4000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],

            // ── MANDIRI BILL (ECHANNEL) ───────────────────────────────
            [
                'name'         => 'Mandiri Bill Payment',
                'payment_type' => 'echannel',
                'bank'         => null,
                'flat_fee'     => 4000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],

            // ── E-WALLET ─────────────────────────────────────────────
            [
                'name'         => 'GoPay',
                'payment_type' => 'gopay',
                'bank'         => null,
                'flat_fee'     => 0,
                'percent_fee'  => 2,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'ShopeePay',
                'payment_type' => 'shopeepay',
                'bank'         => null,
                'flat_fee'     => 0,
                'percent_fee'  => 2,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'OVO',
                'payment_type' => 'other_qris',
                'bank'         => 'ovo',
                'flat_fee'     => 0,
                'percent_fee'  => 2,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'Dana',
                'payment_type' => 'other_qris',
                'bank'         => 'dana',
                'flat_fee'     => 0,
                'percent_fee'  => 2,
                'is_active'    => true,
                'icon_url'     => null,
            ],

            // ── QRIS ─────────────────────────────────────────────────
            [
                'name'         => 'QRIS',
                'payment_type' => 'qris',
                'bank'         => null,
                'flat_fee'     => 0,
                'percent_fee'  => 0.7,
                'is_active'    => true,
                'icon_url'     => null,
            ],

            // ── KARTU KREDIT / DEBIT ──────────────────────────────────
            [
                'name'         => 'Kartu Kredit / Debit',
                'payment_type' => 'credit_card',
                'bank'         => null,
                'flat_fee'     => 0,
                'percent_fee'  => 2.9,
                'is_active'    => true,
                'icon_url'     => null,
            ],

            // ── GERAI TUNAI ───────────────────────────────────────────
            [
                'name'         => 'Alfamart',
                'payment_type' => 'cstore',
                'bank'         => 'alfamart',
                'flat_fee'     => 5000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'Indomaret',
                'payment_type' => 'cstore',
                'bank'         => 'indomaret',
                'flat_fee'     => 5000,
                'percent_fee'  => 0,
                'is_active'    => true,
                'icon_url'     => null,
            ],

            // ── PAYLATER ─────────────────────────────────────────────
            [
                'name'         => 'Akulaku PayLater',
                'payment_type' => 'akulaku',
                'bank'         => null,
                'flat_fee'     => 0,
                'percent_fee'  => 2,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'Kredivo',
                'payment_type' => 'kredivo',
                'bank'         => null,
                'flat_fee'     => 0,
                'percent_fee'  => 2,
                'is_active'    => true,
                'icon_url'     => null,
            ],
            [
                'name'         => 'Shopee PayLater',
                'payment_type' => 'shopeepaylater',
                'bank'         => null,
                'flat_fee'     => 0,
                'percent_fee'  => 2,
                'is_active'    => true,
                'icon_url'     => null,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                [
                    'payment_type' => $method['payment_type'],
                    'bank'         => $method['bank'],
                ],
                $method
            );
        }
    }
}
