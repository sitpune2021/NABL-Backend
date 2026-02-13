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

        // -----------------------------------------
        // 2️⃣ ADMIN (LEVEL 2)
        // -----------------------------------------
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'      => 'Admin User',
                'username'  => 'adminuser',
                'dial_code' => '+91',
                'phone'     => '8888888888',
                'address'   => 'Regional Office',
                'password'  => Hash::make('admin123'),
                'is_super_admin' => false,
            ]
        );

        $this->assignMasterRoleByLevel($admin, 2);

        $this->command->info('✅ Admin (Level 2) created');

        // -----------------------------------------
        // 3️⃣ MANAGER (LEVEL 3)
        // -----------------------------------------
        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name'      => 'Manager User',
                'username'  => 'manageruser',
                'dial_code' => '+91',
                'phone'     => '7777777777',
                'address'   => 'Branch Office',
                'password'  => Hash::make('manager123'),
            ]
        );

        $this->assignMasterRoleByLevel($manager, 3);

        $this->command->info('✅ Manager (Level 3) created');

        // -----------------------------------------
        // 4️⃣ NORMAL USER (LEVEL 4)
        // -----------------------------------------
        $normalUser = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name'      => 'Normal User',
                'username'  => 'normaluser',
                'dial_code' => '+91',
                'phone'     => '6666666666',
                'address'   => 'Local Office',
                'password'  => Hash::make('user123'),
            ]
        );

        $this->assignMasterRoleByLevel($normalUser, 4);

        $this->command->info('✅ User (Level 4) created');
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
