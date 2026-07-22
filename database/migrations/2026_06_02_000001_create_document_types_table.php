<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable: international documents (passports) belong to no country row.
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();

            // Stable slug the tablet keys on: SV_DUI, GT_DPI, US_DL_TX...
            $table->string('code', 50)->unique();
            $table->string('name', 150);

            // id_card | driver_license | passport | residence_card | voter_card | other
            $table->string('document_kind', 30);

            // State/province for subdivision-issued documents (e.g. TX).
            $table->string('subdivision', 50)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['country_id', 'is_active']);
            $table->index('document_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
