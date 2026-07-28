<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Kept generic so a second client (iOS, kiosk) can reuse the table.
            $table->string('platform', 20)->default('android');

            // Same integer as `versionCode` in the Android build.gradle.
            $table->unsignedInteger('version_code');
            $table->string('version_name', 30);

            // Governance mirrors document_templates: releases are retired by
            // moving to `deprecated`, never by deleting the row.
            $table->enum('status', ['draft', 'published', 'deprecated'])->default('draft');

            // The binary lives on the private disk and is only reachable through
            // the authenticated download endpoint — never under public/.
            $table->string('file_path');
            $table->string('file_name');
            $table->char('file_hash', 64);
            $table->unsignedBigInteger('file_size');

            $table->text('release_notes')->nullable();

            // Devices reporting a version_code below this must update before
            // they are allowed to keep working.
            $table->unsignedInteger('min_supported_version_code')->default(0);

            // Forces the update regardless of min_supported_version_code.
            $table->boolean('is_critical')->default(false);

            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['platform', 'version_code']);
            $table->index(['platform', 'status', 'version_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
