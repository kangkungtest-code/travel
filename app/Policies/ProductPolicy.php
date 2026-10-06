<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    private function boleh(User $user): bool
    {
        return $user->hasPermissionTo('produk.kelola');
    }

    public function viewAny(User $user): bool
    {
        return $this->boleh($user);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->boleh($user);
    }

    public function create(User $user): bool
    {
        return $this->boleh($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->boleh($user);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->boleh($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->boleh($user);
    }
}
