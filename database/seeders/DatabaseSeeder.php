<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\Role;
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
            ['email' => 'test@example.com'],
            ['name' => 'Test Admin', 'password' => 'password'],
        );

        if (! $admin->hasRole(Role::Admin->value)) {
            $admin->assignRole(Role::Admin->value);
        }

        $admin->medicalInformation()->updateOrCreate(['user_id' => $admin->id], [
            'first_name'    => 'Test',
            'last_name'     => 'Admin',
            'date_of_birth' => '1990-01-15',
            'gender'        => Gender::Male,
            'email'         => 'test@example.com',
            'phone_country_code' => 'PH',
            'phone'              => '9928727279',
            'blood_type'    => 'O+',
            'religion'      => 'Catholic',
            'address'       => '123 Admin St, Manila, Philippines',
            'no_blood_transfusion' => false,
        ]);

        $admin->medicalInformation->emergencyContacts()->updateOrCreate(
            ['is_primary' => true],
            [
                'name'               => 'Jane Admin',
                'relationship'       => 'Spouse',
                'phone_country_code' => 'PH',
                'phone'              => '9928727279',
                'is_primary'         => true,
            ],
        );
    }
}
