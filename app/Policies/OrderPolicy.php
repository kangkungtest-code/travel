<?php

namespace App\Policies;

class OrderPolicy extends IzinPolicy
{
    protected string $izin = 'order.lihat';

    protected array $tanpa = ['create', 'update', 'delete', 'deleteAny'];
}
