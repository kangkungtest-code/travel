<?php

namespace App\Actions\Sewa;

use App\Actions\Order\UbahStatusOrderAction;
use App\Exceptions\TokoException;
use App\Models\BookingSewa;
use App\Models\Order;
use App\Models\Tarif;
use App\Models\UnitKendaraan;
use App\Models\User;
use App\Support\Ketersediaan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Langkah operasional booking oleh admin (panel & nanti API):
 *   dibayar  → diproses (konfirmasi: unit final, sopir)
 *   diproses → dikirim  (serah terima: km & BBM awal)
 *   dikirim  → selesai  (pengembalian: km & BBM akhir, keterlambatan, denda)
 * Status order tetap lewat UbahStatusOrderAction (state machine & riwayat).
 */
class OperasionalBookingAction
{
    public function __construct(private UbahStatusOrderAction $status) {}

    private function booking(Order $order): BookingSewa
    {
        return $order->bookingSewa ?? throw new TokoException('Order ini bukan booking sewa.');
    }

    /** Pindahkan booking ke unit lain dari tipe yang sama (harus bebas di jadwal itu). */
    public function gantiUnit(Order $order, string $unitId): void
    {
        DB::transaction(function () use ($order, $unitId) {
            $b = BookingSewa::query()->lockForUpdate()->findOrFail($this->booking($order)->id);
            if ($b->unit_kendaraan_id === $unitId) {
                return;
            }

            $unit = UnitKendaraan::query()->where('tipe_kendaraan_id', $b->tipe_kendaraan_id)->lockForUpdate()->find($unitId)
                ?? throw new TokoException('Unit tidak ditemukan untuk kendaraan ini.');

            $bebas = Ketersediaan::filterBebas(UnitKendaraan::query()->whereKey($unit->id), $b->mulai, $b->selesai, null, $order->id)->exists();
            if (! $bebas) {
                throw new TokoException("Unit {$unit->plat_nomor} tidak siap atau sudah dipakai booking lain di jadwal ini.");
            }

            $lama = $b->unit?->plat_nomor ?? '—';
            $b->update(['unit_kendaraan_id' => $unit->id]);
            $order->statusHistories()->create([
                'ke' => $order->status,
                'user_id' => auth('admin')->id(),
                'catatan' => "Unit diganti: {$lama} → {$unit->plat_nomor}",
            ]);
        });
    }

    /** @param array{unit_kendaraan_id?:?string, sopir_nama?:?string, sopir_telepon?:?string, catatan?:?string} $data */
    public function konfirmasi(Order $order, ?User $oleh, array $data = []): void
    {
        $b = $this->booking($order);
        DB::transaction(function () use ($order, $oleh, $data, $b) {
            if (! empty($data['unit_kendaraan_id'])) {
                $this->gantiUnit($order, $data['unit_kendaraan_id']);
            }
            if (! $b->fresh()->unit_kendaraan_id) {
                throw new TokoException('Pilih unit kendaraan dulu.');
            }
            if ($b->mode === Tarif::SOPIR) {
                if (blank($data['sopir_nama'] ?? null)) {
                    throw new TokoException('Isi nama sopir untuk sewa dengan sopir.');
                }
                $b->update(['sopir' => ['nama' => trim($data['sopir_nama']), 'telepon' => trim((string) ($data['sopir_telepon'] ?? ''))]]);
            }
            $this->status->execute($order, Order::STATUS_DIPROSES, $oleh, ['catatan' => $data['catatan'] ?? null]);
        });
    }

    /** @param array{km:int, bbm:string, catatan?:?string} $data */
    public function serahTerima(Order $order, ?User $oleh, array $data): void
    {
        $b = $this->booking($order);
        DB::transaction(function () use ($order, $oleh, $data, $b) {
            $b->update(['serah_terima' => [
                'waktu' => now()->toIso8601String(),
                'km' => (int) $data['km'],
                'bbm' => $data['bbm'],
                'catatan' => $data['catatan'] ?? null,
                'oleh' => $oleh?->nama_lengkap,
            ]]);
            $this->status->execute($order, Order::STATUS_DIKIRIM, $oleh, ['catatan' => 'Serah terima: km '.$data['km'].', BBM '.$data['bbm']]);
        });
    }

    /**
     * Keterlambatan (jam, dibulatkan ke atas setelah toleransi) & saran denda dari tarif per jam
     * (atau 1/10 harga harian per jam kalau tarif per jam kosong), maksimal harga harian per 24 jam.
     *
     * @return array{jam:int, denda:float}
     */
    public static function keterlambatan(BookingSewa $b, ?CarbonImmutable $kembali = null): array
    {
        $kembali ??= CarbonImmutable::now();
        $menit = $b->selesai->diffInMinutes($kembali, false) - (int) config('travel.booking.toleransi_telat_menit', 30);
        if ($menit <= 0) {
            return ['jam' => 0, 'denda' => 0.0];
        }
        $jam = (int) ceil(($menit + (int) config('travel.booking.toleransi_telat_menit', 30)) / 60);

        $tarif = $b->tipe?->tarif()->where('mode', $b->mode)->first();
        $harian = (float) ($tarif?->harga_harian ?? 0);
        $perJam = (float) ($tarif?->harga_per_jam ?: $harian / 10);
        $denda = intdiv($jam, 24) * $harian + min(($jam % 24) * $perJam, $harian);

        return ['jam' => $jam, 'denda' => round($denda)];
    }

    /** @param array{km:int, bbm:string, denda_telat?:?float, biaya_lain?:?float, catatan?:?string} $data */
    public function pengembalian(Order $order, ?User $oleh, array $data): void
    {
        $b = $this->booking($order);
        $awal = (int) ($b->serah_terima['km'] ?? 0);
        if ((int) $data['km'] < $awal) {
            throw new TokoException("Km akhir tidak boleh lebih kecil dari km awal ({$awal}).");
        }

        DB::transaction(function () use ($order, $oleh, $data, $b, $awal) {
            $telat = self::keterlambatan($b);
            $b->update(['pengembalian' => [
                'waktu' => now()->toIso8601String(),
                'km' => (int) $data['km'],
                'jarak_km' => (int) $data['km'] - $awal,
                'bbm' => $data['bbm'],
                'telat_jam' => $telat['jam'],
                'denda_telat' => (float) ($data['denda_telat'] ?? 0),
                'biaya_lain' => (float) ($data['biaya_lain'] ?? 0),
                'catatan' => $data['catatan'] ?? null,
                'oleh' => $oleh?->nama_lengkap,
            ]]);
            $this->status->execute($order, Order::STATUS_SELESAI, $oleh, ['catatan' => 'Kendaraan kembali: km '.$data['km'].', BBM '.$data['bbm'].($telat['jam'] ? ", telat {$telat['jam']} jam" : '')]);
        });
    }
}
