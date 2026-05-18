<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StationSeeder extends Seeder
{
    public function run(): void
    {
        $pin = '12345678';

        Station::updateOrCreate(
            ['code' => 'EFL-001'],
            [
                'name'       => 'EFL Main Lobby',
                'location'   => 'Entrada Principal',
                'api_key'    => Str::random(64),
                'pin'        => Hash::make($pin),
                'pin_lookup' => Station::makePinLookup($pin),
                'is_active'  => true,
            ]
        );
    }
}
