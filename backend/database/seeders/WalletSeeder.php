<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletMutation;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        // Syarat: UserSeeder + OrderSeeder sudah berjalan

        $org1       = User::where('email', 'organizer1@ticketgo.test')->firstOrFail();
        $org2       = User::where('email', 'organizer2@ticketgo.test')->firstOrFail();
        $artist     = User::where('email', 'artist@ticketgo.test')->firstOrFail();
        $affiliator = User::where('email', 'affiliate@ticketgo.test')->firstOrFail();

        // ── WALLET ORGANIZER 1 ─────────────────────────────────────────
        // Saldo: hasil dari 3 order paid (masuk), minus simulasi payout
        $wallet1 = Wallet::firstOrCreate(
            ['user_id' => $org1->id],
            ['balance' => 1_638_000] // Saldo bersih setelah komisi platform
        );

        WalletMutation::firstOrCreate(
            ['wallet_id' => $wallet1->id, 'description' => 'Penjualan Tiket ORD-DEMO-001'],
            ['amount' => 855_000, 'type' => 'credit']
        );
        WalletMutation::firstOrCreate(
            ['wallet_id' => $wallet1->id, 'description' => 'Penjualan Tiket ORD-DEMO-002 (VIP)'],
            ['amount' => 1_080_000, 'type' => 'credit']
        );
        WalletMutation::firstOrCreate(
            ['wallet_id' => $wallet1->id, 'description' => 'Komisi Artist Dewa 19 (ORD-DEMO-002)'],
            ['amount' => 120_000, 'type' => 'debit']
        );
        WalletMutation::firstOrCreate(
            ['wallet_id' => $wallet1->id, 'description' => 'Komisi Affiliator Reza Promo (ORD-DEMO-002)'],
            ['amount' => 60_000, 'type' => 'debit']
        );
        WalletMutation::firstOrCreate(
            ['wallet_id' => $wallet1->id, 'description' => 'Simulasi Withdrawal — Rekening BCA'],
            ['amount' => 117_000, 'type' => 'debit']
        );

        // ── WALLET ORGANIZER 2 ─────────────────────────────────────────
        $wallet2 = Wallet::firstOrCreate(
            ['user_id' => $org2->id],
            ['balance' => 145_500]
        );

        WalletMutation::firstOrCreate(
            ['wallet_id' => $wallet2->id, 'description' => 'Penjualan Tiket ORD-DEMO-003 (Seminar)'],
            ['amount' => 150_000, 'type' => 'credit']
        );
        WalletMutation::firstOrCreate(
            ['wallet_id' => $wallet2->id, 'description' => 'Platform Fee ORD-DEMO-003 (3%)'],
            ['amount' => 4_500, 'type' => 'debit']
        );

        // ── WALLET ARTIST ──────────────────────────────────────────────
        $walletArtist = Wallet::firstOrCreate(
            ['user_id' => $artist->id],
            ['balance' => 120_000]
        );

        WalletMutation::firstOrCreate(
            ['wallet_id' => $walletArtist->id, 'description' => 'Pembagian dari Konser Dewa 19 (ORD-DEMO-002)'],
            ['amount' => 120_000, 'type' => 'credit']
        );

        // ── WALLET AFFILIATOR ──────────────────────────────────────────
        $walletAffiliate = Wallet::firstOrCreate(
            ['user_id' => $affiliator->id],
            ['balance' => 60_000]
        );

        WalletMutation::firstOrCreate(
            ['wallet_id' => $walletAffiliate->id, 'description' => 'Komisi Referral ORD-DEMO-002 (5% dari VIP)'],
            ['amount' => 60_000, 'type' => 'credit']
        );

        $this->command->info('[7/7] Wallets selesai: org1, org2, artist, affiliator beserta mutasi.');
    }
}
