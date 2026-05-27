<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only enum widening. SQLite (used by the test suite) stores enums
        // as plain text, so the column already accepts the new values — skip.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','super_admin','country_manager','viewer') DEFAULT 'admin'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Revert to original values — rows with new roles must be updated first
        DB::statement("UPDATE users SET role = 'admin' WHERE role IN ('country_manager','viewer')");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','super_admin') DEFAULT 'admin'");
    }
};
