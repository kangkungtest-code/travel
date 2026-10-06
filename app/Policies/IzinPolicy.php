<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy sederhana: semua aksi butuh satu permission (format `modul.aksi`).
 * Subclass mengisi $izin, dan boleh mematikan aksi tertentu lewat $tanpa.
 */
abstract class IzinPolicy
{
    protected string $izin;

    /** @var array<int, string> aksi yang tidak tersedia sama sekali di panel */
    protected array $tanpa = [];

    protected function boleh(User $user, string $aksi): bool
    {
        return ! in_array($aksi, $this->tanpa, true) && $user->hasPermissionTo($this->izin);
    }

    public function viewAny(User $user): bool
    {
        return $this->boleh($user, 'viewAny');
    }

    public function view(User $user, $model): bool
    {
        return $this->boleh($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->boleh($user, 'create');
    }

    public function update(User $user, $model): bool
    {
        return $this->boleh($user, 'update');
    }

    public function delete(User $user, $model): bool
    {
        return $this->boleh($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->boleh($user, 'deleteAny');
    }
}
