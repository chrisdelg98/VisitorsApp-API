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
        Schema::table('visits', function (Blueprint $table) {
            // Re-entry / cross-station traceability.
            // When a visitor scans their badge at a different station, a NEW visit
            // is created here referencing the original. Both records are independent.
            $table->foreignUuid('original_visit_id')
                ->nullable()
                ->after('notes')
                ->constrained('visits')
                ->nullOnDelete();

            $table->foreignUuid('reentry_from_station_id')
                ->nullable()
                ->after('original_visit_id')
                ->constrained('stations')
                ->nullOnDelete();

            $table->index('original_visit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['original_visit_id']);
            $table->dropConstrainedForeignId('original_visit_id');
            $table->dropConstrainedForeignId('reentry_from_station_id');
        });
    }
};
