<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(NavigationSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(UnitSeeder::class);
        $this->call(ZoneClusterLocationSeeder::class);
        $this->call(InstrumentSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(UserSeeder::class);
    }
}
