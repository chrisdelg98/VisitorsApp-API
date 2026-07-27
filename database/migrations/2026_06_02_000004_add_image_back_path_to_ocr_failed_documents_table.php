<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ocr_failed_documents', function (Blueprint $table) {
            // A single report now carries both sides. `image_path` keeps the
            // front sample; this holds the optional back sample.
            $table->string('image_back_path', 500)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('ocr_failed_documents', function (Blueprint $table) {
            $table->dropColumn('image_back_path');
        });
    }
};
