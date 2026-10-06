<?php

namespace App\Policies;

use App\Models\ShippingZone;
use App\Models\User;

class ShippingZonePolicy
{
    private function boleh(User $user): bool
    {
        return $user->hasPermissionTo('ongkir.kelola');
    }

    public function viewAny(User $user): bool
    {
        return $this->boleh($user);
    }

    public function create(User $user): bool
    {
        return $this->boleh($user);
    }

    public function update(User $user, ShippingZone $zone): bool
    {
        return $this->boleh($user);
    }

    public function delete(User $user, ShippingZone $zone): bool
    {
        return $this->boleh($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->boleh($user);
    }
}
