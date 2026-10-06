<?php

namespace App\Policies;

class HalamanKebijakanPolicy extends IzinPolicy
{
    protected string $izin = 'kebijakan.kelola';

    /** Halaman tetap 4 buah: tidak dibuat/dihapus dari panel, cukup diedit atau disembunyikan. */
    protected array $tanpa = ['create', 'delete', 'deleteAny'];
}
