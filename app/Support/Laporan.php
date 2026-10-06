<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka laporan untuk dashboard admin. Penjualan dihitung dari order yang sudah
 * dibayar (dibayar/diproses/dikirim/selesai) berdasarkan waktu bayar, dalam IDR.
 * Dihitung di PHP supaya sama di MySQL & SQLite; cukup untuk skala toko kecil.
 */
class Laporan
{
    public const STATUS_TERBAYAR = [Order::STATUS_DIBAYAR, Order::STATUS_DIPROSES, Order::STATUS_DIKIRIM, Order::STATUS_SELESAI];

    public Carbon $dari;

    public Carbon $sampai;

    public function __construct(?string $dari = null, ?string $sampai = null)
    {
        $tz = config('toko.zona_waktu');
        $this->sampai = ($sampai ? Carbon::parse($sampai, $tz) : now($tz))->endOfDay();
        $this->dari = ($dari ? Carbon::parse($dari, $tz) : $this->sampai->copy()->subDays(29))->startOfDay();
    }

    public static function dariFilter(?array $filters): self
    {
        return new self($filters['dari'] ?? null, $filters['sampai'] ?? null);
    }

    public function orderTerbayar(): Builder
    {
        return Order::query()
            ->whereIn('status', self::STATUS_TERBAYAR)
            ->whereBetween('dibayar_pada', [$this->dari->copy()->utc(), $this->sampai->copy()->utc()]);
    }

    /** @return array{penjualan: float, jumlah: int, rata: float} */
    public function ringkasan(): array
    {
        $q = $this->orderTerbayar();
        $total = (float) (clone $q)->sum('total_idr');
        $jumlah = (clone $q)->count();

        return ['penjualan' => $total, 'jumlah' => $jumlah, 'rata' => $jumlah ? $total / $jumlah : 0];
    }

    /** @return Collection<string, float> tanggal (Y-m-d) => penjualan IDR, semua hari terisi */
    public function penjualanHarian(): Collection
    {
        $tz = config('toko.zona_waktu');
        $perHari = $this->orderTerbayar()->get(['dibayar_pada', 'total_idr'])
            ->groupBy(fn (Order $o) => $o->dibayar_pada->timezone($tz)->format('Y-m-d'))
            ->map(fn ($g) => (float) $g->sum('total_idr'));

        $hasil = collect();
        for ($d = $this->dari->copy(); $d <= $this->sampai; $d->addDay()) {
            $hasil[$d->format('Y-m-d')] = $perHari[$d->format('Y-m-d')] ?? 0.0;
        }

        return $hasil;
    }

    /** Item dari order terbayar di periode ini. */
    private function itemTerbayar(): Collection
    {
        return OrderItem::query()
            ->whereIn('order_id', $this->orderTerbayar()->select('id'))
            ->with('variant.product.category')
            ->get();
    }

    /** @return Collection<string, int> nama produk => qty terjual, terbanyak dulu */
    public function produkTerlaris(int $batas = 10): Collection
    {
        return $this->itemTerbayar()
            ->groupBy(fn (OrderItem $i) => $i->variant?->product?->getTranslation('nama_terjemahan', 'id') ?? 'Produk terhapus')
            ->map(fn ($g) => (int) $g->sum('qty'))
            ->sortDesc()
            ->take($batas);
    }

    /** @return Collection<string, float> kategori => penjualan IDR (harga barang, tanpa ongkir) */
    public function penjualanPerKategori(): Collection
    {
        return $this->itemTerbayar()
            ->groupBy(fn (OrderItem $i) => $i->variant?->product?->category?->nama('id') ?: 'Tanpa kategori')
            ->map(fn ($g) => (float) $g->sum(fn (OrderItem $i) => $i->qty * (float) $i->harga_saat_itu))
            ->sortDesc();
    }

    /** @return Collection<string, int> status => jumlah order yang DIBUAT di periode ini */
    public function statusOrder(): Collection
    {
        return Order::query()
            ->whereBetween('created_at', [$this->dari->copy()->utc(), $this->sampai->copy()->utc()])
            ->get(['status'])
            ->countBy('status');
    }

    /** @return Collection<string, float> metode bayar => penjualan IDR (order terbayar di periode ini) */
    public function perMetodeBayar(): Collection
    {
        return $this->orderTerbayar()
            ->with(['payments' => fn ($q) => $q->whereIn('status', [\App\Models\Payment::BERHASIL, \App\Models\Payment::DIREFUND])])
            ->get(['id', 'total_idr'])
            ->groupBy(fn (Order $o) => ($p = $o->payments->first())
                ? \App\Payments\MetodePembayaran::label($p->gateway)
                : 'Konfirmasi manual')
            ->map(fn ($g) => (float) $g->sum('total_idr'))
            ->sortDesc();
    }
}
