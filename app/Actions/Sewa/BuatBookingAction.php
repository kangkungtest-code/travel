<?php

namespace App\Actions\Sewa;

use App\Exceptions\TokoException;
use App\Models\BookingSewa;
use App\Models\Lokasi;
use App\Models\Order;
use App\Models\TipeKendaraan;
use App\Models\UnitKendaraan;
use App\Models\User;
use App\Notifications\OrderDibuat;
use App\Support\HargaSewa;
use App\Support\Ketersediaan;
use App\Support\Kurs;
use App\Support\NotifikasiAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Booking sewa: order `menunggu_pembayaran` + detail sewa, satu unit dialokasikan.
 *
 * Semua unit tipe itu di lokasi itu dikunci (row lock, urutan tetap) sebelum mencari
 * yang bebas, sehingga dua pembeli yang memesan bersamaan tidak mendapat unit yang sama.
 * Harga dihitung ulang di server (HargaSewa), tidak dipercaya dari form.
 */
class BuatBookingAction
{
    public function __construct(private Kurs $kurs) {}

    /**
     * @param  array{nama_penyewa:string, telepon:string, catatan?:?string}  $penyewa
     * @param  array<string, UploadedFile>  $dokumen  mis. ['identitas' => …, 'sim' => …]
     */
    public function execute(
        User $user,
        TipeKendaraan $tipe,
        Lokasi $lokasi,
        string $mode,
        CarbonImmutable $mulai,
        CarbonImmutable $selesai,
        array $penyewa,
        array $dokumen,
        string $mataUang,
    ): Order {
        $tarif = $tipe->is_active ? $tipe->tarifUntuk($mode) : null;
        if (! $tarif || ! $lokasi->is_active) {
            throw new TokoException(__('This vehicle is not available for the selected option.'));
        }

        // File disimpan dulu (di luar transaksi); dihapus lagi kalau booking gagal.
        $disk = Storage::disk(config('travel.booking.disk_dokumen'));
        $path = [];
        foreach ($dokumen as $jenis => $file) {
            $path[$jenis] = $file->store('dokumen-sewa/'.now()->format('Y/m'), ['disk' => config('travel.booking.disk_dokumen')]);
        }

        try {
            $order = DB::transaction(function () use ($user, $tipe, $lokasi, $mode, $mulai, $selesai, $penyewa, $path, $mataUang, $tarif) {
                $kandidat = UnitKendaraan::query()
                    ->where('tipe_kendaraan_id', $tipe->id)
                    ->where('lokasi_id', $lokasi->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                $unit = Ketersediaan::unitBebas($tipe->id, $mulai, $selesai, $lokasi->id)
                    ->whereIn('id', $kandidat)
                    ->orderBy('plat_nomor')
                    ->first();

                if (! $unit) {
                    throw new TokoException(__('Sorry, this vehicle was just booked for these dates. Please choose other dates or another vehicle.'));
                }

                $rincian = HargaSewa::hitung($tarif, $mulai, $selesai);
                $mataUang = $this->kurs->mataUangEfektif($mataUang);
                $totalIdr = (float) $rincian['total'];
                $total = round($this->kurs->konversi($totalIdr, $mataUang), $this->kurs->desimal($mataUang));

                $order = Order::create([
                    'nomor' => $this->nomorBaru(),
                    'user_id' => $user->id,
                    'address_id' => null,
                    'alamat_snapshot' => [
                        'nama_penerima' => $penyewa['nama_penyewa'],
                        'telepon' => $penyewa['telepon'],
                        'detail_alamat' => $lokasi->nama('id').($lokasi->alamat ? ' — '.$lokasi->alamat : ''),
                        'kota' => $lokasi->kota,
                        'negara' => 'ID',
                    ],
                    'status' => Order::STATUS_MENUNGGU_PEMBAYARAN,
                    'sumber_order' => 'sewa',
                    'mata_uang' => $mataUang,
                    'kurs_terpakai' => $this->kurs->rateEfektif($mataUang),
                    'subtotal' => $total,
                    'ongkir' => 0,
                    'total' => $total,
                    'subtotal_idr' => $totalIdr,
                    'ongkir_idr' => 0,
                    'total_idr' => $totalIdr,
                    'berat_gram' => 0,
                    'kadaluarsa_pada' => now()->addHours(config('toko.order.batas_bayar_jam')),
                ]);

                BookingSewa::create([
                    'order_id' => $order->id,
                    'tipe_kendaraan_id' => $tipe->id,
                    'unit_kendaraan_id' => $unit->id,
                    'lokasi_id' => $lokasi->id,
                    'mode' => $mode,
                    'mulai' => $mulai->utc(),
                    'selesai' => $selesai->utc(),
                    'nama_penyewa' => $penyewa['nama_penyewa'],
                    'telepon' => $penyewa['telepon'],
                    'catatan' => $penyewa['catatan'] ?? null,
                    'rincian' => $rincian + ['nama_kendaraan' => $tipe->getTranslations('nama_terjemahan')],
                    'dokumen' => $path ?: null,
                ]);

                $order->statusHistories()->create(['ke' => Order::STATUS_MENUNGGU_PEMBAYARAN, 'user_id' => $user->id]);

                return $order;
            });
        } catch (\Throwable $e) {
            $disk->delete(array_values($path));
            throw $e;
        }

        $user->notify(new OrderDibuat($order));
        NotifikasiAdmin::pesananBaru($order);

        return $order;
    }

    private function nomorBaru(): string
    {
        do {
            $nomor = config('toko.order.prefix_nomor').'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::query()->where('nomor', $nomor)->exists());

        return $nomor;
    }
}
