<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── ADMIN ──────────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@ticketgo.test'],
            [
                'name'               => 'Super Admin',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'is_active'          => true,
            ]
        );
        $admin->syncRoles(['admin']);

        // ── FINANCE ────────────────────────────────────────────────────
        $finance = User::firstOrCreate(
            ['email' => 'finance@ticketgo.test'],
            [
                'name'               => 'Finance Manager',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'is_active'          => true,
            ]
        );
        $finance->syncRoles(['finance']);

        // ── ORGANIZER 1 (Aktif, Sudah Verified) ───────────────────────
        $organizer1 = User::firstOrCreate(
            ['email' => 'organizer1@ticketgo.test'],
            [
                'name'               => 'Promotor Nusantara',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'is_active'          => true,
            ]
        );
        $organizer1->syncRoles(['organizer']);

        // ── ORGANIZER 2 (Aktif, Sudah Verified, punya event berbeda) ──
        $organizer2 = User::firstOrCreate(
            ['email' => 'organizer2@ticketgo.test'],
            [
                'name'               => 'Event Jawa Kreatif',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'is_active'          => true,
            ]
        );
        $organizer2->syncRoles(['organizer']);

        // ── ARTIST ─────────────────────────────────────────────────────
        $artist = User::firstOrCreate(
            ['email' => 'artist@ticketgo.test'],
            [
                'name'               => 'Dewa 19 Management',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'is_active'          => true,
            ]
        );
        $artist->syncRoles(['artist']);

        // ── AFFILIATOR ─────────────────────────────────────────────────
        $affiliator = User::firstOrCreate(
            ['email' => 'affiliate@ticketgo.test'],
            [
                'name'               => 'Reza Promo',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'is_active'          => true,
            ]
        );
        $affiliator->syncRoles(['affiliator']);

        // ── BUYER 1 (Punya tiket paid) ─────────────────────────────────
        $buyer1 = User::firstOrCreate(
            ['email' => 'buyer1@ticketgo.test'],
            [
                'name'               => 'Budi Santoso',
                'nickname'           => 'Budi',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'is_active'          => true,
            ]
        );
        $buyer1->syncRoles(['user']);

        // ── BUYER 2 (Punya tiket via affiliator) ──────────────────────
        $buyer2 = User::firstOrCreate(
            ['email' => 'buyer2@ticketgo.test'],
            [
                'name'               => 'Siti Rahayu',
                'nickname'           => 'Siti',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'is_active'          => true,
            ]
        );
        $buyer2->syncRoles(['user']);

        // ── BUYER 3 (Order pending/expired untuk test edge case) ───────
        $buyer3 = User::firstOrCreate(
            ['email' => 'buyer3@ticketgo.test'],
            [
                'name'               => 'Andi Wijaya',
                'nickname'           => 'Andi',
                'password'           => Hash::make('password'),
                'email_verified_at'  => now(),
                'is_active'          => true,
            ]
        );
        $buyer3->syncRoles(['user']);

        $this->command->info('[3/7] Users selesai: admin, finance, 2 organizer, artist, affiliator, 3 buyer.');
    }
}
