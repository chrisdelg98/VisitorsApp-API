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
        Schema::create('visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignUuid('visitor_id')->constrained('visitors')->restrictOnDelete();
            $table->string('visitor_type', 50);
            $table->string('visit_reason', 100);
            $table->string('visit_reason_custom', 255)->nullable();
            $table->string('visiting_person', 150);
            $table->timestamp('check_in');
            $table->timestamp('check_out')->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->boolean('badge_printed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['station_id', 'status']);
            $table->index('check_in');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
