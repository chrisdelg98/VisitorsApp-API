<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'SV', 'name' => 'El Salvador',    'flag_emoji' => '🇸🇻'],
            ['code' => 'GT', 'name' => 'Guatemala',       'flag_emoji' => '🇬🇹'],
            ['code' => 'HN', 'name' => 'Honduras',        'flag_emoji' => '🇭🇳'],
            ['code' => 'NI', 'name' => 'Nicaragua',       'flag_emoji' => '🇳🇮'],
            ['code' => 'CR', 'name' => 'Costa Rica',      'flag_emoji' => '🇨🇷'],
            ['code' => 'PA', 'name' => 'Panamá',          'flag_emoji' => '🇵🇦'],
            ['code' => 'US', 'name' => 'United States',   'flag_emoji' => '🇺🇸'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['code' => $country['code']],
                array_merge($country, ['is_active' => true])
            );
        }
    }
}
