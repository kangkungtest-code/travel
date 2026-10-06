<?php

namespace App\Support;

/**
 * Verifikasi email baru diwajibkan kalau server benar-benar bisa mengirim email
 * (MAIL_MAILER bukan "log"/"array"). Bisa dipaksa lewat TOKO_WAJIB_VERIFIKASI=true/false.
 */
class VerifikasiEmail
{
    public static function wajib(): bool
    {
        $paksa = config('toko.wajib_verifikasi_email');
        if ($paksa !== null) {
            return (bool) $paksa;
        }

        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }
}
