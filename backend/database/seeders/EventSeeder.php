<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\TicketTier;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // Syarat: MasterDataSeeder + UserSeeder + OrganizerProfileSeeder sudah berjalan

        $org1      = User::where('email', 'organizer1@ticketgo.test')->firstOrFail();
        $org2      = User::where('email', 'organizer2@ticketgo.test')->firstOrFail();
        $artist    = User::where('email', 'artist@ticketgo.test')->firstOrFail();
        $affiliator = User::where('email', 'affiliate@ticketgo.test')->firstOrFail();

        $catMusik    = Category::where('slug', 'konser-musik')->firstOrFail();
        $catSeminar  = Category::where('slug', 'seminar-tech')->firstOrFail();
        $catFestival = Category::where('slug', 'festival')->firstOrFail();

        $venueGBK     = Venue::where('name', 'Stadion Gelora Bung Karno')->firstOrFail();
        $venueTirto   = Venue::where('name', 'Tirtonadi Convention Hall')->firstOrFail();
        $venueBalai   = Venue::where('name', 'Balai Kartini')->firstOrFail();

        // ══════════════════════════════════════════════════════════════
        // EVENT 1: Published + Featured + Ada Artist + Affiliate Aktif
        // Skenario: event utama, sudah published, bisa dibeli user
        // ══════════════════════════════════════════════════════════════
        $event1 = Event::firstOrCreate(
            ['slug' => 'konser-dewa-19-jakarta-2026'],
            [
                'organizer_id'               => $org1->id,
                'artist_id'                  => $artist->id,
                'category_id'                => $catMusik->id,
                'venue_id'                   => $venueGBK->id,
                'title'                      => 'Konser Dewa 19 — Reunion Jakarta 2026',
                'description'                => 'Konser reuni legendaris Dewa 19 di GBK. Hadirkan lagu-lagu hits sepanjang masa.',
                'start_date'                 => now()->addDays(45),
                'end_date'                   => now()->addDays(45)->addHours(4),
                'sales_start_date'           => now()->subDays(7),
                'sales_end_date'             => now()->addDays(44),
                'event_end_date'             => now()->addDays(45)->addHours(4),
                'status'                     => 'published',
                'is_featured'                => true,
                'is_free_event'              => false,
                'is_ppn_included'            => true,
                'admin_fee_percentage'       => 5.00,
                'ppn_percentage'             => 11.00,
                'local_tax_percentage'       => 0,
                'max_tickets_per_order'      => 4,
                'total_seats'                => 500,
                'available_seats'            => 320,
                'price'                      => 350000,
                'poster_path'               => null,
                'venue_name'                 => 'Stadion Gelora Bung Karno',
                'venue_address'              => 'Jl. Pintu Satu Senayan, Jakarta',
                'gmaps_url'                  => 'https://maps.google.com/?q=GBK',
                'custom_fields'             => [
                    ['field_name' => 'Nomor KTP / NIK', 'field_type' => 'number', 'is_required' => true],
                    ['field_name' => 'Domisili Kota',   'field_type' => 'text',   'is_required' => false],
                ],
                'social_links'               => ['instagram' => 'https://instagram.com/dewa19official'],
                // Affiliate AKTIF (paid) — affiliator bisa promosi event ini
                'is_affiliate_enabled'           => true,
                'affiliate_activation_status'    => 'paid',
                'affiliate_commission_rate'      => 5.00, // 5% komisi per tiket
            ]
        );

        // Ticket Tiers untuk Event 1 (3 tier dengan auto-open cascade)
        $tierEarlyBird = TicketTier::firstOrCreate(
            ['event_id' => $event1->id, 'name' => 'Early Bird'],
            [
                'type'                   => 'paid',
                'price'                  => 350000,
                'capacity'               => 100,
                'available_seats'        => 0,  // SOLD OUT → trigger tier berikutnya
                'description'            => 'Harga spesial pembelian awal.',
                'close_type'             => 'quota',
                'is_visible_before_sales' => true,
                'is_active'              => true,
            ]
        );

        $tierRegular = TicketTier::firstOrCreate(
            ['event_id' => $event1->id, 'name' => 'Regular'],
            [
                'type'                      => 'paid',
                'price'                     => 450000,
                'capacity'                  => 250,
                'available_seats'           => 180,
                'description'               => 'Tiket reguler. Buka setelah Early Bird habis.',
                'close_type'                => 'quota',
                'auto_open_after_tier_id'   => $tierEarlyBird->id, // Buka setelah EarlyBird sold out
                'is_visible_before_sales'   => true,
                'is_active'                 => true,
            ]
        );

        TicketTier::firstOrCreate(
            ['event_id' => $event1->id, 'name' => 'VIP'],
            [
                'type'                      => 'paid',
                'price'                     => 1200000,
                'capacity'                  => 50,
                'available_seats'           => 38,
                'description'               => 'Akses VIP: area premium + meet & greet.',
                'close_type'                => 'both',
                'end_sales_at'              => now()->addDays(40), // Tutup 5 hari sebelum event
                'auto_open_after_tier_id'   => null,
                'is_visible_before_sales'   => true,
                'is_active'                 => true,
            ]
        );

        // ══════════════════════════════════════════════════════════════
        // EVENT 2: Published, Normal (tanpa artist, tanpa affiliate)
        // Skenario: event biasa yang aktif dijual, dipakai untuk test order
        // ══════════════════════════════════════════════════════════════
        $event2 = Event::firstOrCreate(
            ['slug' => 'seminar-tech-indonesia-2026'],
            [
                'organizer_id'               => $org2->id,
                'artist_id'                  => null,
                'category_id'                => $catSeminar->id,
                'venue_id'                   => $venueTirto->id,
                'title'                      => 'Indonesia Tech Summit 2026',
                'description'                => 'Konferensi teknologi terbesar di Jawa Tengah. Pembicara dari Google, Gojek, dan startup lokal.',
                'start_date'                 => now()->addDays(20),
                'end_date'                   => now()->addDays(21),
                'sales_start_date'           => now()->subDays(14),
                'sales_end_date'             => now()->addDays(19),
                'event_end_date'             => now()->addDays(21),
                'status'                     => 'published',
                'is_featured'                => false,
                'is_free_event'              => false,
                'is_ppn_included'            => false,
                'admin_fee_percentage'       => 3.00, // EO VIP rate
                'ppn_percentage'             => 0,
                'local_tax_percentage'       => 0,
                'max_tickets_per_order'      => 5,
                'total_seats'                => 300,
                'available_seats'            => 210,
                'price'                      => 150000,
                'venue_name'                 => 'Tirtonadi Convention Hall',
                'venue_address'              => 'Jl. Jend. A. Yani No.261, Solo',
                'gmaps_url'                  => null,
                'custom_fields'             => [
                    ['field_name' => 'Nomor Telepon', 'field_type' => 'text', 'is_required' => true],
                    ['field_name' => 'Ukuran Kaos (Merch)', 'field_type' => 'select', 'is_required' => false],
                ],
                'social_links'               => [],
                'is_affiliate_enabled'       => false,
                'affiliate_activation_status' => 'unpaid',
                'affiliate_commission_rate'  => 0,
            ]
        );

        TicketTier::firstOrCreate(
            ['event_id' => $event2->id, 'name' => 'Peserta Umum'],
            [
                'type'            => 'paid',
                'price'           => 150000,
                'capacity'        => 250,
                'available_seats' => 210,
                'description'     => 'Tiket reguler untuk seminar 2 hari.',
                'close_type'      => 'quota',
                'is_active'       => true,
            ]
        );

        TicketTier::firstOrCreate(
            ['event_id' => $event2->id, 'name' => 'Workshop Add-on'],
            [
                'type'            => 'paid',
                'price'           => 300000,
                'capacity'        => 50,
                'available_seats' => 32,
                'description'     => 'Tiket peserta + workshop hands-on eksklusif.',
                'close_type'      => 'quota',
                'is_active'       => true,
            ]
        );

        // ══════════════════════════════════════════════════════════════
        // EVENT 3: Pending (menunggu approval admin)
        // Skenario: test alur request publish → approve/reject admin
        // ══════════════════════════════════════════════════════════════
        $event3 = Event::firstOrCreate(
            ['slug' => 'festival-kuliner-solo-2026'],
            [
                'organizer_id'               => $org2->id,
                'artist_id'                  => null,
                'category_id'                => $catFestival->id,
                'venue_id'                   => $venueBalai->id,
                'title'                      => 'Festival Kuliner Solo 2026',
                'description'                => 'Festival kuliner tahunan dengan 100+ stand makanan khas Jawa Tengah.',
                'start_date'                 => now()->addDays(60),
                'end_date'                   => now()->addDays(62),
                'sales_start_date'           => now()->addDays(7),
                'sales_end_date'             => now()->addDays(59),
                'event_end_date'             => now()->addDays(62),
                'status'                     => 'pending', // Menunggu approval
                'is_featured'                => false,
                'is_free_event'              => false,
                'is_ppn_included'            => false,
                'admin_fee_percentage'       => 3.00,
                'ppn_percentage'             => 0,
                'local_tax_percentage'       => 0,
                'max_tickets_per_order'      => 10,
                'total_seats'                => 1000,
                'available_seats'            => 1000,
                'price'                      => 50000,
                'venue_name'                 => 'Balai Kartini',
                'venue_address'              => 'Jl. Gatot Subroto Kav. 37, Jakarta',
                'custom_fields'             => [],
                'social_links'               => [],
                'is_affiliate_enabled'       => false,
                'affiliate_activation_status' => 'unpaid',
                'affiliate_commission_rate'  => 0,
            ]
        );

        TicketTier::firstOrCreate(
            ['event_id' => $event3->id, 'name' => 'Tiket Masuk'],
            [
                'type'            => 'paid',
                'price'           => 50000,
                'capacity'        => 1000,
                'available_seats' => 1000,
                'description'     => 'Tiket masuk harian (berlaku 1 hari).',
                'close_type'      => 'quota',
                'is_active'       => true,
            ]
        );

        $this->command->info('[5/7] Events selesai: 2 published (1 featured), 1 pending, total 6 ticket tier.');
    }
}
