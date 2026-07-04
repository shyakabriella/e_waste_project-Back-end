<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => 'admin'],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'System administrator. Can manage users, waste prices, payout reports, audit logs, and system security.',
                'permissions' => json_encode([
                    'manage_users',
                    'manage_roles',
                    'set_waste_prices',
                    'view_payout_reports',
                    'view_audit_logs',
                    'manage_system_security',
                    'manage_waste_listings',
                    'manage_pickups',
                    'manage_wallets',
                    'view_dashboard',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}