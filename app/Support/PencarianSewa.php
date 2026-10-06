<?php

namespace App\Support;

use App\Models\Lokasi;
use App\Models\Tarif;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Parameter pencarian sewa dari query string (?lokasi=&mulai=&selesai=&mode=).
 * Waktu diisi pembeli dalam zona waktu toko (input datetime-local), disimpan UTC.
 * Isian salah tidak melempar error: halaman tetap tampil dengan pesan di $galat.
 */
final class PencarianSewa
{
    public const FORMAT = 'Y-m-d\TH:i';

    /** @param array<string, string> $galat */
    private function __construct(
        public readonly ?Lokasi $lokasi,
        public readonly ?CarbonImmutable $mulai,
        public readonly ?CarbonImmutable $selesai,
        public readonly string $mode,
        public readonly array $galat,
    ) {}

    public static function dari(Request $request): self
    {
        $zona = config('toko.zona_waktu');
        $galat = [];

        $lokasi = null;
        if (filled($id = $request->query('lokasi'))) {
            $lokasi = is_string($id) && \Illuminate\Support\Str::isUuid($id) ? Lokasi::query()->where('is_active', true)->find($id) : null;
            if (! $lokasi) {
                $galat['lokasi'] = __('Choose a pick-up location.');
            }
        }

        $mode = in_array($request->query('mode'), array_keys(config('travel.mode')), true)
            ? $request->query('mode')
            : (array_key_first(config('travel.mode')) ?? Tarif::LEPAS_KUNCI);

        $baca = function (string $kunci) use ($request, $zona, &$galat): ?CarbonImmutable {
            $nilai = $request->query($kunci);
            if (! filled($nilai)) {
                return null;
            }
            try {
                $waktu = is_string($nilai) ? CarbonImmutable::createFromFormat(self::FORMAT, $nilai, $zona) : null;
            } catch (\Throwable) {
                $waktu = null;
            }
            if (! $waktu) {
                $galat[$kunci] = __('Enter a valid date and time.');
            }

            return $waktu ?: null;
        };
        $mulai = $baca('mulai');
        $selesai = $baca('selesai');

        if ($mulai && $selesai) {
            $cfg = config('travel.booking');
            if ($mulai->lt(now()->addHours($cfg['minimal_jam_dari_sekarang'])->subMinutes(5))) {
                $galat['mulai'] = __('Pick-up must be at least :hours hours from now.', ['hours' => $cfg['minimal_jam_dari_sekarang']]);
            } elseif ($mulai->gt(now()->addDays($cfg['maks_hari_ke_depan']))) {
                $galat['mulai'] = __('Bookings open up to :days days ahead.', ['days' => $cfg['maks_hari_ke_depan']]);
            }
            if ($selesai->lte($mulai)) {
                $galat['selesai'] = __('Return must be after pick-up.');
            } elseif ($mulai->diffInHours($selesai) > $cfg['maks_hari'] * 24) {
                $galat['selesai'] = __('The longest rental is :days days.', ['days' => $cfg['maks_hari']]);
            }
        } elseif ($mulai xor $selesai) {
            $galat[$mulai ? 'selesai' : 'mulai'] = __('Enter both pick-up and return times.');
        }

        return new self($lokasi, $mulai, $selesai, $mode, $galat);
    }

    /** Lokasi, waktu ambil & kembali terisi dan valid → harga & ketersediaan bisa dihitung. */
    public function lengkap(): bool
    {
        return $this->lokasi && $this->mulai && $this->selesai && $this->galat === [];
    }

    /** @return array<string, string> untuk URL (kosong tidak ikut) */
    public function query(): array
    {
        return array_filter([
            'lokasi' => $this->lokasi?->id,
            'mulai' => $this->mulai?->format(self::FORMAT),
            'selesai' => $this->selesai?->format(self::FORMAT),
            'mode' => $this->mode,
        ]);
    }

    /** Nilai awal input (kalau belum diisi: besok 09.00 – lusa 09.00 di lokasi pertama). */
    public function isian(): array
    {
        $besok = now(config('toko.zona_waktu'))->addDay()->setTime(9, 0);

        return [
            'lokasi' => $this->lokasi?->id ?? request()->query('lokasi') ?? Lokasi::aktif()->value('id'),
            'mulai' => $this->mulai?->format(self::FORMAT) ?? request()->query('mulai') ?? $besok->format(self::FORMAT),
            'selesai' => $this->selesai?->format(self::FORMAT) ?? request()->query('selesai') ?? $besok->addDay()->format(self::FORMAT),
            'mode' => $this->mode,
            'min' => now(config('toko.zona_waktu'))->addHours(config('travel.booking.minimal_jam_dari_sekarang'))->format(self::FORMAT),
        ];
    }
}
