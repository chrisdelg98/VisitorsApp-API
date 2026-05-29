<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('station_device_logs', function (Blueprint $table) {
            $table->timestamp('unregistered_at')->nullable()->after('unregistered_by');
        });
    }

    public function down(): void
    {
        Schema::table('station_device_logs', function (Blueprint $table) {
            $table->dropColumn('unregistered_at');
        });
    }
};
