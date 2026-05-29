<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('countries', 'timezone')) {
            return;
        }

        Schema::table('countries', function (Blueprint $table) {
            $table->string('timezone', 50)->nullable()->after('flag_emoji');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('countries', 'timezone')) {
            return;
        }

        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
