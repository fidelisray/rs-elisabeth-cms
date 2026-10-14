<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeder ini HANYA membuat akun-akun default dan BERSIFAT NON-DESTRUCTIVE:
     * - Memakai `firstOrCreate` sehingga TIDAK pernah menimpa password/atribut
     *   akun yang sudah ada (aman dijalankan ulang setiap kali boot/deploy).
     * - Penetapan role Spatie (super_admin, marketing, Staff IT) sepenuhnya
     *   dilakukan di RoleAndPermissionSeeder agar logika role terpusat.
     *
     * PENTING: Jangan mengganti `firstOrCreate` dengan `updateOrCreate` di sini,
     * karena itu akan mereset password admin kembali ke default pada setiap
     * restart container (menghapus perubahan password yang sudah dilakukan user).
     */
    public function run(): void
    {
        // 1. Akun Super Admin utama.
        //    must_change_password = false karena Super Admin sudah langsung
        //    menetapkan password-nya sendiri sejak awal.
        User::firstOrCreate(
            ['email' => 'admin@rs-elisabeth.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin@123456'),
                'role' => 'super_admin',
                'must_change_password' => false,
            ]
        );

        // 2. Akun Staf Marketing (menggantikan akun humas@rs-elisabeth.com).
        //    Migrasi hanya dilakukan jika marketing belum ada, dan TANPA
        //    menyentuh password/atribut lain yang sudah dimiliki user.
        $marketingExists = User::where('email', 'marketing@rs-elisabeth.com')->exists();
        $legacyHumas = User::where('email', 'humas@rs-elisabeth.com')->first();

        if ($legacyHumas && ! $marketingExists) {
            $legacyHumas->update([
                'name' => 'Staf Marketing',
                'email' => 'marketing@rs-elisabeth.com',
                'role' => 'staff',
            ]);
        } elseif (! $marketingExists) {
            User::firstOrCreate(
                ['email' => 'marketing@rs-elisabeth.com'],
                [
                    'name' => 'Staf Marketing',
                    'password' => Hash::make('123456'),
                    'role' => 'staff',
                    'must_change_password' => true,
                ]
            );
        }

        // 3. Akun Staf IT dengan password default '123456'.
        User::firstOrCreate(
            ['email' => 'it@rs-elisabeth.com'],
            [
                'name' => 'Staff IT',
                'password' => Hash::make('123456'),
                'role' => 'staff',
                'must_change_password' => true,
            ]
        );
    }
}
