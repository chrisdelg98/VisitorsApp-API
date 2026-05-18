<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            // Deterministic HMAC-SHA256 of the PIN — used for lookup since bcrypt is not.
            $table->string('pin_lookup', 64)->nullable()->unique()->after('pin');

            $table->string('device_imei', 20)->nullable()->after('pin_lookup');
            $table->string('device_android_id', 64)->nullable()->after('device_imei');
            $table->string('device_model', 100)->nullable()->after('device_android_id');
            $table->string('registered_ip', 45)->nullable()->after('device_model');
            $table->timestamp('registered_at')->nullable()->after('registered_ip');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn([
                'pin_lookup',
                'device_imei',
                'device_android_id',
                'device_model',
                'registered_ip',
                'registered_at',
            ]);
        });
    }
};
