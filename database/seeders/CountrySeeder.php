<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'SV', 'name' => 'El Salvador',   'flag_emoji' => '🇸🇻', 'timezone' => 'America/El_Salvador'],
            ['code' => 'GT', 'name' => 'Guatemala',      'flag_emoji' => '🇬🇹', 'timezone' => 'America/Guatemala'],
            ['code' => 'HN', 'name' => 'Honduras',       'flag_emoji' => '🇭🇳', 'timezone' => 'America/Tegucigalpa'],
            ['code' => 'NI', 'name' => 'Nicaragua',      'flag_emoji' => '🇳🇮', 'timezone' => 'America/Managua'],
            ['code' => 'CR', 'name' => 'Costa Rica',     'flag_emoji' => '🇨🇷', 'timezone' => 'America/Costa_Rica'],
            ['code' => 'PA', 'name' => 'Panamá',         'flag_emoji' => '🇵🇦', 'timezone' => 'America/Panama'],
            ['code' => 'US', 'name' => 'United States',  'flag_emoji' => '🇺🇸', 'timezone' => 'America/New_York'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['code' => $country['code']],
                array_merge($country, ['is_active' => true])
            );
        }
    }
}
