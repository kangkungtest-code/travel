<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Console\Command;

/**
 * Buat / perbarui akun Super Admin (pemilik platform) di instalasi ini.
 * Login boleh berupa email atau username (huruf kecil, angka, titik, garis bawah, minus).
 * Password diambil dari env SUPERADMIN_PASSWORD supaya tidak tercatat di riwayat shell.
 *
 *   SUPERADMIN_PASSWORD=rahasia php artisan toko:super-admin frendi
 */
class BuatSuperAdmin extends Command
{
    protected $signature = 'toko:super-admin {email : Email atau username akun Super Admin} {--cabut : Cabut peran Super Admin dari akun ini (akunnya tidak dihapus)} {--murni : Lepas peran lain (mis. Owner) sehingga akun ini HANYA Super Admin}';

    protected $description = 'Buat atau perbarui akun Super Admin (pengatur fitur & paket)';

    public function handle(): int
    {
        $login = mb_strtolower(trim((string) $this->argument('email')));
        $pakaiEmail = str_contains($login, '@');
        if ($pakaiEmail ? ! filter_var($login, FILTER_VALIDATE_EMAIL) : ! preg_match('/^[a-z0-9._-]{3,30}$/', $login)) {
            $this->error($pakaiEmail ? 'Email tidak valid.' : 'Username 3–30 karakter: huruf kecil, angka, titik, garis bawah, minus.');

            return self::FAILURE;
        }
        $kolom = $pakaiEmail ? 'email' : 'username';
        $email = $login; // untuk pesan

        $this->callSilently('db:seed', ['--class' => RoleAndPermissionSeeder::class, '--force' => true]);

        if ($this->option('cabut')) {
            $user = User::query()->where($kolom, $login)->first();
            if ($user?->hasRole(RoleAndPermissionSeeder::SUPER_ADMIN)) {
                $user->removeRole(RoleAndPermissionSeeder::SUPER_ADMIN);
                $this->info("Peran Super Admin dicabut dari {$email}.");
            } else {
                $this->info("{$email} bukan Super Admin.");
            }

            return self::SUCCESS;
        }

        $password = (string) env('SUPERADMIN_PASSWORD', '');
        $user = User::query()->where($kolom, $login)->first();

        if (! $user) {
            if (mb_strlen($password) < 8) {
                $this->error('Akun belum ada: isi SUPERADMIN_PASSWORD (minimal 8 karakter).');

                return self::FAILURE;
            }
            $user = User::create([
                'nama_lengkap' => 'Super Admin',
                // Akun username tetap butuh email unik: alamat .invalid (tidak pernah bisa menerima email).
                'email' => $pakaiEmail ? $login : $login.'@super-admin.invalid',
                'password' => $password,
                'bahasa_preferensi' => 'id',
                'mata_uang_preferensi' => 'IDR',
            ]);
            $user->forceFill(['email_verified_at' => now(), 'username' => $pakaiEmail ? null : $login])->save();
            $this->info("Akun {$email} dibuat.");
        } elseif (mb_strlen($password) >= 8) {
            $user->forceFill(['password' => $password])->save();
            $this->info("Password {$email} diperbarui.");
        }

        if ($this->option('murni')) {
            $user->syncRoles([RoleAndPermissionSeeder::SUPER_ADMIN]);
        } elseif (! $user->hasRole(RoleAndPermissionSeeder::SUPER_ADMIN)) {
            $user->assignRole(RoleAndPermissionSeeder::SUPER_ADMIN);
        }
        $user->forceFill(['nonaktif_pada' => null])->save();
        $this->info("{$email} sekarang Super Admin.");

        return self::SUCCESS;
    }
}
