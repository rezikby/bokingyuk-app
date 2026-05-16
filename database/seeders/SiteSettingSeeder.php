<?php

namespace Database\Seeders;

use App\Services\SiteSettingService;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        (new SiteSettingService())->seedDefaults();
        $this->command->info('Site settings seeded successfully.');
    }
}
