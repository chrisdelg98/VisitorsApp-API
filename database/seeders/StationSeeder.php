<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StationSeeder extends Seeder
{
    public function run(): void
    {
        Station::updateOrCreate(
            ['code' => 'EFL-001'],
            [
                'name' => 'EFL Main Lobby',
                'api_key' => Str::random(64),
                'is_active' => true,
            ]
        );
    }
}
