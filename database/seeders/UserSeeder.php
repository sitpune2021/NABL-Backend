<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserLocationDepartmentRole;
use App\Models\UserCustomPermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // -----------------------------------------
        // 1️⃣ SUPER ADMIN (No ULDR, No location)
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

        $superAdmin->assignRole('Super Admin');

        echo "Super Admin created\n";

        // -----------------------------------------
        // 2️⃣ ADMIN USER (With ULDR)
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

        $admin->assignRole('Admin');

        // Assign ULDR (example: Location 1, Department 1)
        $this->assignULDR($admin->id, 1, 1, Role::where('name', 'Admin')->first()->id);

        echo "Admin created\n";

        // -----------------------------------------
        // 3️⃣ MANAGER USER (With ULDR)
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

        $manager->assignRole('Manager');

        $this->assignULDR($manager->id, 1, 2, Role::where('name', 'Manager')->first()->id);

        echo "Manager created\n";

        // -----------------------------------------
        // 4️⃣ NORMAL USER (With ULDR)
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

        $normalUser->assignRole('User');

        $this->assignULDR($normalUser->id, 2, 3, Role::where('name', 'User')->first()->id);

        echo "Normal user created\n";
    }


    // -------------------------------------------------------
    // Helper: Assign ULDR & attach default permissions
    // -------------------------------------------------------
    private function assignULDR($userId, $locationId, $departmentId, $roleId)
    {
        $uldr = UserLocationDepartmentRole::create([
            'user_id'       => $userId,
            'location_id'   => $locationId,
            'department_id' => $departmentId,
            'role_id'       => $roleId,
            'status'        => 'active',
            'position_type' => 'permanent',
        ]);

        // Get permissions for this role
        $role = Role::find($roleId);

        foreach ($role->permissions as $perm) {
            UserCustomPermission::create([
                'user_location_department_role_id' => $uldr->id,
                'permission_id' => $perm->id,
                'is_allowed' => true,
            ]);
        }

        return $uldr;
    }
}
