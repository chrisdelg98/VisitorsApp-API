<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_failed_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Reporting station. Nullable so purging a station never drops the queue item.
            $table->foreignUuid('station_id')->nullable()->constrained('stations')->nullOnDelete();

            // What the on-device classifier guessed, and how sure it was (0.000–1.000).
            $table->string('detected_type', 50)->nullable();
            $table->decimal('detected_confidence', 4, 3)->nullable();

            // PREFERRED payload: text blocks + normalized boxes, no raw image needed.
            $table->json('ocr_blocks')->nullable();

            // PII — may arrive masked from the device. See the retention policy in config/ocr.php.
            $table->longText('ocr_text')->nullable();

            // Optional sample image, stored on the private `local` disk like visit_images.
            $table->string('image_path', 500)->nullable();

            $table->string('app_version', 30)->nullable();

            $table->enum('status', ['pending', 'in_review', 'converted', 'discarded'])->default('pending');

            $table->foreignUuid('matched_template_id')->nullable()
                ->constrained('document_templates')->nullOnDelete();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('station_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_failed_documents');
    }
};
