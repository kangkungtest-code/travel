<?php

namespace App\Policies;

class FaqPolicy extends IzinPolicy
{
    protected string $izin = 'faq.kelola';

    protected array $tanpa = [];
}
