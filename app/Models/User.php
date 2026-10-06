<?php

namespace App\Models;

use Database\Seeders\RoleAndPermissionSeeder;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Admin dan customer satu tabel. Pembedanya role (Spatie Permission):
 * user tanpa role = customer biasa, user dengan role guard `admin` = staff.
 */
#[Fillable(['nama_lengkap', 'email', 'password', 'bahasa_preferensi', 'mata_uang_preferensi'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference, HasName, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    /** Role & permission staff dicek di guard `admin`. */
    protected string $guard_name = 'admin';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'dihapus_pada' => 'datetime',
            'nonaktif_pada' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifikasiEmail);
    }

    /** Email & notifikasi dikirim dalam bahasa pilihan pembeli. */
    public function preferredLocale(): string
    {
        return $this->bahasa_preferensi ?: config('app.locale');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->adalahAdmin();
    }

    public function getFilamentName(): string
    {
        return $this->nama_lengkap;
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function perangkatAdmin(): HasMany
    {
        return $this->hasMany(PerangkatAdmin::class);
    }

    /** Staff = punya role di guard admin (dipakai panel web & API aplikasi admin). */
    /**
     * Boleh masuk panel / aplikasi admin: punya peran admin, tidak dinonaktifkan, dan
     * (untuk staf) fitur "Akun staf & peran" sedang menyala.
     */
    public function adalahAdmin(): bool
    {
        if ($this->nonaktif_pada) {
            return false;
        }

        $peran = $this->roles()->where('guard_name', 'admin')->pluck('name');
        if ($peran->isEmpty()) {
            return false;
        }

        return $peran->intersect([RoleAndPermissionSeeder::OWNER, RoleAndPermissionSeeder::SUPER_ADMIN])->isNotEmpty()
            || \App\Support\Fitur::aktif('staf');
    }

    public function adalahSuperAdmin(): bool
    {
        return $this->hasRole(RoleAndPermissionSeeder::SUPER_ADMIN, 'admin');
    }

    /** Token FCM untuk channel notifikasi push. */
    public function routeNotificationForFcm(): array
    {
        return $this->perangkatAdmin()->pluck('fcm_token')->all();
    }
}
