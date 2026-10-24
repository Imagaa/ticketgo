<?php

namespace Database\Seeders;

use App\Models\BuyerFormTemplate;
use App\Models\Category;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── KATEGORI EVENT ─────────────────────────────────────────────
        $categories = [
            ['name' => 'Konser Musik',     'slug' => 'konser-musik'],
            ['name' => 'Standup Comedy',   'slug' => 'standup-comedy'],
            ['name' => 'Seminar & Tech',   'slug' => 'seminar-tech'],
            ['name' => 'Olahraga',         'slug' => 'olahraga'],
            ['name' => 'Teater',           'slug' => 'teater'],
            ['name' => 'Festival',         'slug' => 'festival'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'is_active' => true]
            );
        }

        // ── VENUE ──────────────────────────────────────────────────────
        $venues = [
            [
                'name'     => 'Stadion Gelora Bung Karno',
                'city'     => 'Jakarta Pusat',
                'province' => 'DKI Jakarta',
                'address'  => 'Jl. Pintu Satu Senayan, Jakarta',
                'capacity' => 77000,
            ],
            [
                'name'     => 'ICE BSD City',
                'city'     => 'Tangerang',
                'province' => 'Banten',
                'address'  => 'Jl. BSD Grand Boulevard No.1',
                'capacity' => 10000,
            ],
            [
                'name'     => 'Tirtonadi Convention Hall',
                'city'     => 'Surakarta',
                'province' => 'Jawa Tengah',
                'address'  => 'Jl. Jend. A. Yani No.261, Solo',
                'capacity' => 3000,
            ],
            [
                'name'     => 'Balai Kartini',
                'city'     => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'address'  => 'Jl. Gatot Subroto Kav. 37',
                'capacity' => 2000,
            ],
        ];

        foreach ($venues as $v) {
            Venue::firstOrCreate(['name' => $v['name']], $v);
        }

        // ── BUYER FORM TEMPLATES ───────────────────────────────────────
        $formTemplates = [
            [
                'field_name' => 'Nomor KTP / NIK',
                'field_type' => 'number',
                'is_required' => true,
                'is_active'   => true,
            ],
            [
                'field_name' => 'Tanggal Lahir',
                'field_type' => 'date',
                'is_required' => true,
                'is_active'   => true,
            ],
            [
                'field_name' => 'Domisili Kota',
                'field_type' => 'text',
                'is_required' => false,
                'is_active'   => true,
            ],
            [
                'field_name' => 'Ukuran Kaos (Merch)',
                'field_type' => 'select',
                'options'     => json_encode(['choices' => ['S', 'M', 'L', 'XL', 'XXL']]),
                'is_required' => false,
                'is_active'   => true,
            ],
            [
                'field_name' => 'Nomor Telepon',
                'field_type' => 'text',
                'is_required' => true,
                'is_active'   => true,
            ],
        ];

        foreach ($formTemplates as $tpl) {
            BuyerFormTemplate::firstOrCreate(
                ['field_name' => $tpl['field_name']],
                $tpl
            );
        }

        $this->command->info('[2/7] Master data selesai (kategori, venue, form template).');
    }
}
