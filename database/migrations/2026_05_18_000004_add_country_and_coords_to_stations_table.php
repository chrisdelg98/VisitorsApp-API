<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->foreignUuid('country_id')->nullable()->after('location')
                  ->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable()->after('country_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            $table->index('country_id');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex(['country_id']);
            $table->dropColumn(['country_id', 'latitude', 'longitude']);
        });
    }
};
