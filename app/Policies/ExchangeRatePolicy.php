<?php

namespace App\Policies;

class ExchangeRatePolicy extends IzinPolicy
{
    protected string $izin = 'kurs.kelola';

    protected array $tanpa = ['update'];
}
