<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocr_failed_documents', function (Blueprint $table) {
            // How confident the app's own template match was (0.000–1.000). Not PII.
            $table->decimal('match_score', 4, 3)->nullable()->after('detected_confidence');

            // The app's structured reading (field => value it resolved). PII: it
            // holds the actual name/number read off the document, so it is hidden
            // from responses and stripped on the text-retention schedule, exactly
            // like ocr_text.
            $table->json('extracted_fields')->nullable()->after('ocr_text');
        });
    }

    public function down(): void
    {
        Schema::table('ocr_failed_documents', function (Blueprint $table) {
            $table->dropColumn(['match_score', 'extracted_fields']);
        });
    }
};
