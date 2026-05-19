<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','super_admin','country_manager','viewer') DEFAULT 'admin'");
    }

    public function down(): void
    {
        // Revert to original values — rows with new roles must be updated first
        DB::statement("UPDATE users SET role = 'admin' WHERE role IN ('country_manager','viewer')");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','super_admin') DEFAULT 'admin'");
    }
};
