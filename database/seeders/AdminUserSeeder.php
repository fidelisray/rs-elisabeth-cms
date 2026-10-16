<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Email akun default yang BARU dibuat pada eksekusi seeder ini.
     * Dipakai RoleAndPermissionSeeder agar role hanya dipasang pada akun baru
     * dan tidak pernah mengembalikan role yang sudah dicabut admin.
     *
     * @var list<string>
     */
    public static array $createdEmails = [];

    /**
     * Run the database seeds.
     *
     * Seeder ini HANYA membuat akun default dan BERSIFAT NON-DESTRUCTIVE:
     * - Memakai `firstOrCreate` sehingga TIDAK pernah menimpa password/atribut
     *   akun yang sudah ada (aman dijalankan ulang setiap deploy).
     * - Penetapan role dilakukan di RoleAndPermissionSeeder, hanya untuk akun baru.
     *
     * PENTING: Jangan mengganti `firstOrCreate` dengan `updateOrCreate`, karena
     * itu akan mereset password pada setiap deploy.
     */
    public function run(): void
    {
        self::$createdEmails = [];

        // 1. Akun Super Admin utama.
        //    must_change_password = false karena Super Admin sudah langsung
        //    menetapkan password-nya sendiri sejak awal.
        $this->firstOrCreateAccount('admin@rs-elisabeth.com', [
            'name' => 'Super Admin',
            'password' => Hash::make('admin@123456'),
            'role' => 'super_admin',
            'must_change_password' => false,
        ]);

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
            $this->firstOrCreateAccount('marketing@rs-elisabeth.com', [
                'name' => 'Staf Marketing',
                'password' => Hash::make('123456'),
                'role' => 'staff',
                'must_change_password' => true,
            ]);
        }

        // 3. Akun Staf IT dengan password default '123456'.
        $this->firstOrCreateAccount('it@rs-elisabeth.com', [
            'name' => 'Staff IT',
            'password' => Hash::make('123456'),
            'role' => 'staff',
            'must_change_password' => true,
        ]);
    }

    /**
     * Buat akun bila belum ada dan catat bila benar-benar baru dibuat.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function firstOrCreateAccount(string $email, array $attributes): User
    {
        $user = User::firstOrCreate(['email' => $email], $attributes);

        if ($user->wasRecentlyCreated) {
            self::$createdEmails[] = $email;
        }

        return $user;
    }
}
