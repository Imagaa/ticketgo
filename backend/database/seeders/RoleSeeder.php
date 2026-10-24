<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // KRITIS: guard_name HARUS 'api' karena kita pakai Sanctum (bukan 'web')
        $roles = [
            'admin',
            'finance',
            'organizer',
            'artist',
            'affiliator',
            'user',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name'       => $role,
                'guard_name' => 'api',
            ]);
        }

        $this->command->info('[1/7] Roles selesai: ' . implode(', ', $roles));
    }
}
