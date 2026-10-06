<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun Super Admin awal (pemilik platform). Akun Owner toko dibuat Super Admin lewat panel.
 *
 * Kredensial diambil dari environment variable ADMIN_EMAIL & ADMIN_PASSWORD
 * (di server: dari GitHub secret lewat workflow super-admin.yml, tidak disimpan di .env).
 * Hanya dibuat kalau belum ada — password akun yang sudah ada tidak ditimpa.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL') ?: 'admin@toko.test';
        $password = env('ADMIN_PASSWORD');

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            if (! $password) {
                $this->command?->warn("ADMIN_PASSWORD belum diset, akun Super Admin {$email} tidak dibuat.");

                return;
            }

            $user = User::create([
                'nama_lengkap' => 'Super Admin',
                'email' => $email,
                'password' => $password,
                'bahasa_preferensi' => 'id',
                'mata_uang_preferensi' => 'IDR',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->command?->info("Akun admin {$email} dibuat.");
        }

        // ADMIN_EMAIL = akun Super Admin (pemilik platform). Akun Owner toko dibuat Super Admin di panel.
        if (! $user->hasRole(RoleAndPermissionSeeder::SUPER_ADMIN)) {
            $user->assignRole(RoleAndPermissionSeeder::SUPER_ADMIN);
        }
    }
}
