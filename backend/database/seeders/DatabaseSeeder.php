<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Urutan WAJIB diikuti karena ada dependency antar seeder:
     *
     * 1. RoleSeeder          — Role harus ada sebelum user dibuat
     * 2. MasterDataSeeder    — Kategori, venue, form template, payment methods
     * 3. UserSeeder          — Buat semua akun + assign role
     * 4. OrganizerProfileSeeder — Profile EO (butuh user organizer)
     * 5. EventSeeder         — Event + TicketTier (butuh user, kategori, venue)
     * 6. OrderSeeder         — Order + Ticket (butuh event, user, ticket tier)
     * 7. WalletSeeder        — Wallet + Mutasi (butuh user, order)
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,            // Step 1
            MasterDataSeeder::class,      // Step 2
            PaymentMethodSeeder::class,
            UserSeeder::class,            // Step 3
            OrganizerProfileSeeder::class, // Step 4
            EventSeeder::class,           // Step 5
            OrderSeeder::class,           // Step 6
            WalletSeeder::class,          // Step 7
        ]);

        $this->command->newLine();
        $this->command->info('════════════════════════════════════════════════');
        $this->command->info('  ✅ Database TicketGo siap untuk testing!');
        $this->command->info('════════════════════════════════════════════════');
        $this->command->newLine();
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Super Admin',  'admin@ticketgo.test',      'password'],
                ['Finance',      'finance@ticketgo.test',    'password'],
                ['Organizer 1',  'organizer1@ticketgo.test', 'password'],
                ['Organizer 2',  'organizer2@ticketgo.test', 'password'],
                ['Artist',       'artist@ticketgo.test',     'password'],
                ['Affiliator',   'affiliate@ticketgo.test',  'password'],
                ['Buyer 1',      'buyer1@ticketgo.test',     'password'],
                ['Buyer 2',      'buyer2@ticketgo.test',     'password'],
                ['Buyer 3',      'buyer3@ticketgo.test',     'password'],
            ]
        );
        $this->command->newLine();
        $this->command->info('Data dummy yang tersedia:');
        $this->command->info('  - Event 1: Published + Featured (Konser Dewa 19) — 3 tier tiket');
        $this->command->info('  - Event 2: Published normal (Tech Summit) — 2 tier tiket');
        $this->command->info('  - Event 3: Pending approval (Festival Kuliner)');
        $this->command->info('  - Order: 3 paid, 1 pending, 1 expired, 1 cancelled');
        $this->command->info('  - Tiket valid: 3 tiket (buyer1 punya 3, buyer2 punya 1)');
        $this->command->info('  - Wallet: organizer1, organizer2, artist, affiliator');
    }
}
