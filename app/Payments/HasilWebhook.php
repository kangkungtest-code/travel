<?php

namespace App\Payments;

/** Hasil membaca notifikasi gateway yang sudah terverifikasi. */
final class HasilWebhook
{
    public function __construct(
        public readonly string $status,             // berhasil / gagal / kadaluarsa / abaikan
        public readonly ?string $paymentId = null,  // id Payment kita (reference_id / custom_id)
        public readonly ?string $transaksiId = null, // id di gateway (payment_request_id / PayPal order id)
        public readonly ?float $jumlah = null,
        public readonly ?string $mataUang = null,
        public readonly ?string $idCapture = null,
        public readonly array $raw = [],
    ) {}

    public static function abaikan(array $raw = []): self
    {
        return new self('abaikan', raw: $raw);
    }
}
