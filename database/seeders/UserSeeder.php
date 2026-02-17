<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // -----------------------------------------
        // 1️⃣ SUPER ADMIN (LEVEL 1)
        // -----------------------------------------
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name'      => 'Super Admin',
                'username'  => 'superadmin',
                'dial_code' => '+91',
                'phone'     => '9999999999',
                'address'   => 'Head Office',
                'password'  => Hash::make('superadmin123'),
                'is_super_admin' => true,
            ]
        );

        $this->assignMasterRoleByLevel($superAdmin, 1);

        $this->command->info('✅ Super Admin (Level 1) created');
    }

    /* -------------------------------------------------------------
     | ASSIGN MASTER ROLE BY LEVEL
     |-------------------------------------------------------------*/
    private function assignMasterRoleByLevel(User $user, int $level): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0); // MASTER

        $role = Role::where('level', $level)
            ->where('lab_id', 0)
            ->firstOrFail();

        $user->assignRole($role);
        $permissions = $role->permissions->pluck('name')->toArray();
        $user->syncPermissions($permissions); // <-- THIS populates model_has_permissions
    }
}
