<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'rezikobay75@gmail.com'],
            [
                'name'     => 'Super Admin BokingYuk',
                'password' => Hash::make('230107Rezi'),
                'role'     => 'super_admin',
                'phone'    => '083842822623',
                'is_active'=> true,
            ]
        );

        $this->command->info('Super admin seeded: rezikobay75@gmail.com / 230107Rezi!');
    }
}
