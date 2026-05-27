<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Same-day re-entry support: the tablet reopens an already-completed visit
     * locally (lunch break, etc.). These columns let that state — and the final
     * afternoon checkout — propagate back to the API.
     *
     * `checkout_type` is also consumed by a DB-resident auto-close job; it lives
     * here so the schema stays versioned in this repo.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Guarded per-column: `checkout_type` may already exist on the shared
            // DB (added out-of-band), so adding it again would error. Keeping each
            // column conditional makes this migration safe to run anywhere.
            if (! Schema::hasColumn('visits', 'reentry_count')) {
                $table->unsignedInteger('reentry_count')
                    ->default(0)
                    ->after('reentry_from_station_id');
            }

            if (! Schema::hasColumn('visits', 'last_reentry_at')) {
                $table->timestamp('last_reentry_at')
                    ->nullable()
                    ->after('reentry_count');
            }

            if (! Schema::hasColumn('visits', 'checkout_type')) {
                $table->enum('checkout_type', ['visitor', 'auto', 'admin', 'reentry'])
                    ->nullable()
                    ->default(null)
                    ->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['reentry_count', 'last_reentry_at', 'checkout_type'],
                fn (string $column) => Schema::hasColumn('visits', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
