<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ewaste.com'],
            [
                'name' => 'System Admin',
                'email' => 'admin@ewaste.com',
                'password' => Hash::make('Admin@12345'),
                'role' => 'admin',
                'status' => 'active',
                'phone' => '0780000000',
                'address' => 'Kicukiro, Kigali',
                'wallet_balance' => 0,
                'points_balance' => 0,
            ]
        );
    }
}