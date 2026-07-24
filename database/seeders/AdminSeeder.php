<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Env;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = Env::get('ADMIN_EMAIL', 'admin@mediscan.cloud');
        $password = Env::get('ADMIN_PASSWORD');

        if (! $password) {
            $this->command?->error('ADMIN_PASSWORD env var is not set — skipping admin seed.');

            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Admin',
                'last_name' => 'Admin',
                'email_verified_at' => now(),
                'password' => $password,
            ],
        );

        if (! $admin->hasRole(Role::Admin->value)) {
            $admin->assignRole(Role::Admin->value);
        }
    }
}
