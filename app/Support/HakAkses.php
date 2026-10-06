<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder as R;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Siapa mengelola akun siapa:
 *  - Super Admin (dari secret ADMIN_EMAIL) → membuat & mengatur akun Owner toko.
 *  - Owner → membuat staf dan peran staf (fitur "Akun staf & peran").
 * Akun Super Admin tidak pernah tampil di daftar mana pun & tidak bisa diubah dari panel.
 */
class HakAkses
{
    public static function pengguna(): ?User
    {
        $u = Filament::auth()->user();

        return $u instanceof User ? $u : null;
    }

    public static function kelolaOwner(): bool
    {
        return (bool) self::pengguna()?->hasPermissionTo('akun.owner');
    }

    public static function kelolaStaf(): bool
    {
        return Fitur::aktif('staf') && (bool) self::pengguna()?->hasPermissionTo('staf.kelola');
    }

    /** Peran yang boleh dipilih untuk staf (bukan Owner / Super Admin). */
    public static function queryPeranStaf(): Builder
    {
        return Role::query()->where('guard_name', R::GUARD)->whereNotIn('name', [R::OWNER, R::SUPER_ADMIN]);
    }

    /** Akun yang tampil di halaman Akun admin untuk pengguna saat ini. */
    public static function queryAkun(): Builder
    {
        $q = User::query()->whereHas('roles', fn ($r) => $r->where('guard_name', R::GUARD))
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', R::SUPER_ADMIN));

        if (self::kelolaOwner()) {
            return $q->whereHas('roles', fn ($r) => $r->where('name', R::OWNER));
        }

        return $q->whereDoesntHave('roles', fn ($r) => $r->where('name', R::OWNER))
            ->whereKeyNot(self::pengguna()?->getKey());
    }
}
