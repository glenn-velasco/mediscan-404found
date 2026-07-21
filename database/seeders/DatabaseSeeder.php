<?php

namespace Database\Seeders;

use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\Role;
use App\Models\MedicalInformation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@mediscan.cloud'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Admin',
                'dob' => '1990-01-15',
                'gender' => Gender::Male,
                'address' => '123 Admin Street, Admin',
                'phone_number' => '+639171234567',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        if (! $admin->hasRole(Role::Admin->value)) {
            $admin->assignRole(Role::Admin->value);
        }

        if ($admin->medical_information_id === null) {
            $medicalInformation = MedicalInformation::factory()->create([
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'dob' => $admin->dob,

                'gender' => Gender::Male->value,
                'blood_type' => BloodType::O_POSITIVE->value,
            ]);

            $admin->forceFill(['medical_information_id' => $medicalInformation->id])->save();
        }
    }
}
