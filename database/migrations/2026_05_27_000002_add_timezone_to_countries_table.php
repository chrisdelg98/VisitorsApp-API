<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-country IANA timezone. Used to interpret station-local times
     * consistently across stations in the same country.
     */
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            if (! Schema::hasColumn('countries', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('flag_emoji');
            }
        });

        $map = [
            'SV' => 'America/El_Salvador',
            'GT' => 'America/Guatemala',
            'HN' => 'America/Tegucigalpa',
            'NI' => 'America/Managua',
            'CR' => 'America/Costa_Rica',
            'PA' => 'America/Panama',
            'US' => 'America/New_York', // adjust later if US has stations across multiple zones
        ];

        foreach ($map as $code => $tz) {
            DB::table('countries')->where('code', $code)->update(['timezone' => $tz]);
        }
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            if (Schema::hasColumn('countries', 'timezone')) {
                $table->dropColumn('timezone');
            }
        });
    }
};
