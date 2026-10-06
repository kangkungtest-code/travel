<?php

namespace App\Policies;

class FotoKendaraanPolicy extends IzinPolicy
{
    protected string $izin = 'armada.kelola';
}
