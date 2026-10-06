<?php

namespace App\Policies;

class StockPolicy extends IzinPolicy
{
    protected string $izin = 'stok.edit';

    protected array $tanpa = ['create', 'update', 'delete', 'deleteAny'];
}
