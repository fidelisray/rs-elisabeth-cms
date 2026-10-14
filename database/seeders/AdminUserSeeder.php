<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Akun Super Admin utama
        // must_change_password = false karena Super Admin sudah langsung
        // menetapkan password-nya sendiri sejak awal
        $admin = User::updateOrCreate(
            ['email' => 'admin@rs-elisabeth.com'],
            [
                'name'                 => 'Super Admin',
                'password'             => Hash::make('admin@123456'),
                'role'                 => 'super_admin',
                'must_change_password' => false,
            ]
        );
        if (class_exists(Role::class) && Role::where('name', 'super_admin')->exists()) {
            $admin->syncRoles(['super_admin']);
        }

        // 2. Akun Staf Marketing (menggantikan akun humas@rs-elisabeth.com sebelumnya)
        // Jika akun humas@rs-elisabeth.com masih ada, migrasikan email dan namanya
        $humas = User::where('email', 'humas@rs-elisabeth.com')->first();
        if ($humas) {
            $humas->update([
                'name'                 => 'Staf Marketing',
                'email'                => 'marketing@rs-elisabeth.com',
                'password'             => Hash::make('123456'),
                'role'                 => 'staff',
                'must_change_password' => true,
            ]);
            $marketing = $humas;
        } else {
            $marketing = User::updateOrCreate(
                ['email' => 'marketing@rs-elisabeth.com'],
                [
                    'name'                 => 'Staf Marketing',
                    'password'             => Hash::make('123456'),
                    'role'                 => 'staff',
                    'must_change_password' => true,
                ]
            );
        }
        if (class_exists(Role::class) && Role::where('name', 'humas')->exists()) {
            $marketing->syncRoles(['humas']);
        }

        // 3. Akun Staf IT dengan password default '123456'
        $it = User::updateOrCreate(
            ['email' => 'it@rs-elisabeth.com'],
            [
                'name'                 => 'Staff IT',
                'password'             => Hash::make('123456'),
                'role'                 => 'staff',
                'must_change_password' => true,
            ]
        );
        if (class_exists(Role::class) && Role::where('name', 'Staff IT')->exists()) {
            $it->syncRoles(['Staff IT']);
        }
    }
}
