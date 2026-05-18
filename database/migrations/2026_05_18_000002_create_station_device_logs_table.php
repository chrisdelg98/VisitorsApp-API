<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_device_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_id')->constrained()->cascadeOnDelete();

            $table->string('device_imei', 20)->nullable();
            $table->string('device_android_id', 64)->nullable();
            $table->string('device_model', 100)->nullable();
            $table->string('registered_ip', 45)->nullable();
            $table->timestamp('registered_at')->nullable();

            // 'device_logout' = la tablet cerró sesión manualmente
            // 'admin_reset'   = el admin desbloqueó la estación desde el panel
            $table->string('unregistered_by', 20);

            $table->timestamps();

            $table->index('station_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_device_logs');
    }
};
