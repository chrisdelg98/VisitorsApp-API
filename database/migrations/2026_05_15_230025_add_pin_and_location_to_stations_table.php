<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            // Nullable so existing rows aren't broken — stations without a pin
            // cannot authenticate until an admin sets one via the admin API.
            $table->string('pin')->nullable()->after('api_key');
            $table->string('location', 100)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn(['pin', 'location']);
        });
    }
};
