<?php

namespace App\Policies;

class ReturnRequestPolicy extends IzinPolicy
{
    protected string $izin = 'retur.kelola';

    protected array $tanpa = ['create', 'update', 'delete', 'deleteAny'];
}
