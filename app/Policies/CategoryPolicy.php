<?php

namespace App\Policies;

class CategoryPolicy extends IzinPolicy
{
    protected string $izin = 'produk.kelola';
}
