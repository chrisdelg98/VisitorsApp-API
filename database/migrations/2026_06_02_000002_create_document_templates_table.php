<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_type_id')->constrained('document_types')->cascadeOnDelete();

            // Issue/revision of the physical document, e.g. "2021".
            $table->string('version', 20);
            $table->enum('side', ['front', 'back'])->default('front');
            $table->enum('extraction_method', ['anchor', 'zone', 'mrz', 'pdf417'])->default('anchor');

            // Governance: only `published` rows are served by the tablet sync endpoint.
            $table->enum('status', ['draft', 'testing', 'published', 'deprecated'])->default('draft');

            // { keywords: [], id_regex, aspect_ratio, has_mrz, has_pdf417 }
            $table->json('signature');

            // [ { field, method, anchor:{label,position}, region:{x,y,w,h},
            //     validation:{regex,length,charset} } ] — coordinates normalized 0..1.
            $table->json('fields');

            $table->json('sample_meta')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['document_type_id', 'version', 'side']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
