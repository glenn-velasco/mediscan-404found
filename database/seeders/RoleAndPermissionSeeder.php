<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permEnum) {
            Permission::firstOrCreate(['name' => $permEnum->value, 'guard_name' => 'web']);
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);
            $role->syncPermissions(array_map(fn (PermissionEnum $p) => $p->value, $roleEnum->permissions()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
