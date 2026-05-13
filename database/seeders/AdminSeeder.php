<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@visitors.local'],
            [
                'name'      => 'Default Admin',
                'password'  => 'Admin.123456',
                'role'      => 'super_admin',
                'is_active' => true,
            ],
        );
    }
}
