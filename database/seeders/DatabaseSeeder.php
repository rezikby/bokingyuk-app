<?php

namespace Database\Seeders;

use App\Models\Field;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Super Admin ───────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'rezikobay75@gmail.com'],
            [
                'name'            => 'Super Admin BokinYuk',
                'password'        => Hash::make('230107Rezi'),
                'phone'           => '083842822623',
                'whatsapp_number' => '083842822623',
                'role'            => 'super_admin',
                'is_active'       => true,
            ]
        );

        // ── Admin default ─────────────────────────────────────────────────────
        // User::firstOrCreate(
        //     ['email' => 'admin@bokingyuk.com'],
        //     [
        //         'name'            => 'Admin BokinYuk',
        //         'password'        => Hash::make('Admin@12345'),
        //         'phone'           => '081234567890',
        //         'whatsapp_number' => '081234567890',
        //         'role'            => 'admin',
        //         'is_active'       => true,
        //     ]
        // );

        // ── Customer demo ─────────────────────────────────────────────────────
        // User::firstOrCreate(
        //     ['email' => 'customer@bokingyuk.com'],
        //     [
        //         'name'            => 'Customer Demo',
        //         'password'        => Hash::make('Customer@12345'),
        //         'phone'           => '081298765432',
        //         'whatsapp_number' => '081298765432',
        //         'role'            => 'customer',
        //         'is_active'       => true,
        //     ]
        // );

        // ── Sample fields ─────────────────────────────────────────────────────
        // $fields = [
        //     ['name' => 'Lapangan Futsal A',    'type' => 'futsal',    'description' => 'Lapangan futsal indoor rumput sintetis premium.', 'price_per_hour' => 100000, 'is_active' => true, 'facilities' => ['AC', 'Kamar Mandi', 'Parkir', 'WiFi']],
        //     ['name' => 'Lapangan Futsal B',    'type' => 'futsal',    'description' => 'Lapangan futsal outdoor pencahayaan LED.',         'price_per_hour' => 80000,  'is_active' => true, 'facilities' => ['Kamar Mandi', 'Parkir']],
        //     ['name' => 'Lapangan Badminton 1', 'type' => 'badminton', 'description' => 'Lapangan badminton standar BWF, indoor.',          'price_per_hour' => 60000,  'is_active' => true, 'facilities' => ['AC', 'Kamar Mandi', 'Kantin']],
        //     ['name' => 'Lapangan Badminton 2', 'type' => 'badminton', 'description' => 'Lapangan badminton indoor lampu LED terang.',      'price_per_hour' => 60000,  'is_active' => true, 'facilities' => ['AC', 'Kamar Mandi']],
        // ];

        // foreach ($fields as $data) {
        //     Field::firstOrCreate(['name' => $data['name']], $data);
        // }
    }
}
