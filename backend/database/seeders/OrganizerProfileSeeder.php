<?php

namespace Database\Seeders;

use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizerProfileSeeder extends Seeder
{
    public function run(): void
    {
        // Syarat: UserSeeder harus sudah berjalan

        $org1 = User::where('email', 'organizer1@ticketgo.test')->firstOrFail();
        $org2 = User::where('email', 'organizer2@ticketgo.test')->firstOrFail();

        // ── PROFIL ORGANIZER 1 ─────────────────────────────────────────
        OrganizerProfile::firstOrCreate(
            ['user_id' => $org1->id],
            [
                'company_name'        => 'PT Promotor Nusantara',
                'legal_name'          => 'PT Promotor Nusantara Indonesia',
                'tax_id'              => '01.234.567.8-001.000',
                'address'             => 'Jl. Sudirman No. 10, Jakarta Pusat',
                'phone'               => '08111000001',
                'website'             => 'https://promotornusantara.com',
                'bank_name'           => 'BCA',
                'bank_account_name'   => 'PT Promotor Nusantara',
                'bank_account_number' => '1234567890',
                'status'              => 'verified', // Sudah di-approve admin
                'custom_platform_fee' => null,       // Pakai fee default platform
            ]
        );

        // ── PROFIL ORGANIZER 2 ─────────────────────────────────────────
        OrganizerProfile::firstOrCreate(
            ['user_id' => $org2->id],
            [
                'company_name'        => 'Event Jawa Kreatif',
                'legal_name'          => 'CV Event Jawa Kreatif',
                'tax_id'              => '02.345.678.9-002.000',
                'address'             => 'Jl. Slamet Riyadi No. 5, Surakarta',
                'phone'               => '08222000002',
                'website'             => null,
                'bank_name'           => 'Mandiri',
                'bank_account_name'   => 'Event Jawa Kreatif',
                'bank_account_number' => '9876543210',
                'status'              => 'verified',
                'custom_platform_fee' => 3.00, // EO VIP: rate khusus 3%
            ]
        );

        $this->command->info('[4/7] OrganizerProfile selesai: 2 organizer terverifikasi.');
    }
}
