<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@sundarbazar.test'],
            [
                'name' => 'Survey Administrator',
                'password' => 'ChangeMe-Admin-2026',
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'researcher@sundarbazar.com'],
            [
                'name' => 'Field Researcher',
                'password' => 'Research#2026',
                'role' => 'researcher',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
