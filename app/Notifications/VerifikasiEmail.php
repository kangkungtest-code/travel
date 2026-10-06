<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;

/** Email verifikasi bawaan Laravel, dikirim lewat queue dalam bahasa pembeli. */
class VerifikasiEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
