<?php

namespace App\Http\Resources\AdminApi;

use App\Filament\Support\LabelAdmin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Payments\MetodePembayaran;
use Illuminate\Http\Request;

/** @mixin \App\Models\Order */
class PesananDetailResource extends PesananRingkasResource
{
    /** Tombol yang boleh muncul per status (izin order.ubah_status). */
    public const AKSI = [
        Order::STATUS_MENUNGGU_PEMBAYARAN => [
            ['kode' => 'konfirmasi-bayar', 'label' => 'Konfirmasi pembayaran manual', 'wajib' => ['catatan']],
            ['kode' => 'batalkan', 'label' => 'Batalkan', 'wajib' => ['catatan']],
        ],
        Order::STATUS_DIBAYAR => [['kode' => 'proses', 'label' => 'Proses order', 'wajib' => []]],
        Order::STATUS_DIPROSES => [['kode' => 'kirim', 'label' => 'Kirim', 'wajib' => ['resi']]],
        Order::STATUS_DIKIRIM => [['kode' => 'selesai', 'label' => 'Tandai selesai', 'wajib' => []]],
    ];

    /** Nomor lokal (0xxx) diberi kode negara sesuai alamat: Indonesia 62, Taiwan 886. */
    private static function nomorWa(?string $telepon, ?string $negara): ?string
    {
        $angka = preg_replace('/\D+/', '', (string) $telepon);
        if (strlen($angka) < 6) {
            return null;
        }
        if (str_starts_with($angka, '0')) {
            $kode = ['ID' => '62', 'TW' => '886', 'SG' => '65', 'MY' => '60', 'HK' => '852', 'JP' => '81', 'AU' => '61', 'US' => '1'][$negara] ?? null;

            return $kode ? $kode.substr($angka, 1) : null;
        }

        return $angka;
    }

    public function toArray(Request $request): array
    {
        $alamat = $this->alamat_snapshot ?? [];
        $retur = $this->returnRequests->sortByDesc('created_at')->first();

        return parent::toArray($request) + [
            'barang' => $this->items->map(fn (OrderItem $i) => [
                'nama' => $i->variant?->product?->getTranslation('nama_terjemahan', 'id') ?? 'Produk terhapus',
                'sku' => $i->variant?->sku,
                'opsi' => (object) ($i->variant?->opsi ?? []),
                'qty' => $i->qty,
                'harga_idr' => Format::rupiah($i->harga_saat_itu),
                'subtotal_idr' => Format::rupiah($i->qty * (float) $i->harga_saat_itu),
                'foto' => $i->variant?->product?->fotoUntuk($i->variant->opsi[\App\Models\Product::OPSI_WARNA] ?? null)?->thumbUrl(),
                'varian_id' => $i->variant_id,
            ])->values(),
            'alamat' => [
                'nama_penerima' => $alamat['nama_penerima'] ?? null,
                'telepon' => $alamat['telepon'] ?? null,
                'detail' => $alamat['detail_alamat'] ?? null,
                'kota' => $alamat['kota'] ?? null,
                'kode_pos' => $alamat['kode_pos'] ?? null,
                'negara' => $alamat['negara'] ?? null,
                'nama_negara' => config('toko.negara.'.($alamat['negara'] ?? ''), $alamat['negara'] ?? null),
            ],
            'kontak_pembeli' => [
                'telepon' => $alamat['telepon'] ?? null,
                'whatsapp_url' => ($wa = self::nomorWa($alamat['telepon'] ?? null, $alamat['negara'] ?? null))
                    ? "https://wa.me/{$wa}?text=".rawurlencode('Halo, ini dari '.config('toko.nama')." tentang pesanan {$this->nomor}.")
                    : null,
            ],
            'biaya' => [
                'subtotal_idr' => Format::rupiah($this->subtotal_idr),
                'ongkir_idr' => Format::rupiah($this->ongkir_idr),
                'total_idr' => Format::rupiah($this->total_idr),
                'berat_gram' => $this->berat_gram,
            ],
            'pembayaran' => $this->payments->sortByDesc('created_at')->map(fn (Payment $p) => [
                'metode' => MetodePembayaran::label($p->gateway),
                'status' => $p->status,
                'jumlah' => (float) $p->jumlah,
                'mata_uang' => $p->mata_uang,
                'catatan' => $p->catatan,
                'dibuat_pada' => Format::waktu($p->created_at),
                'dibayar_pada' => Format::waktu($p->dibayar_pada),
            ])->values(),
            'riwayat' => $this->statusHistories->sortBy('created_at')->map(fn ($h) => [
                'dari' => $h->dari,
                'ke' => $h->ke,
                'label' => LabelAdmin::STATUS_ORDER[$h->ke] ?? $h->ke,
                'catatan' => $h->catatan,
                'oleh' => $h->user?->nama_lengkap,
                'waktu' => Format::waktu($h->created_at),
            ])->values(),
            'retur' => $retur ? [
                'id' => $retur->id,
                'status' => $retur->status,
                'label_status' => LabelAdmin::STATUS_RETUR[$retur->status] ?? $retur->status,
            ] : null,
            'aksi' => $request->user()?->hasPermissionTo('order.ubah_status')
                ? (self::AKSI[$this->status] ?? [])
                : [],
        ];
    }
}
