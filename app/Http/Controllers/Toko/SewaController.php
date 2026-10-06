<?php

namespace App\Http\Controllers\Toko;

use App\Actions\Sewa\BuatBookingAction;
use App\Exceptions\TokoException;
use App\Http\Controllers\Controller;
use App\Models\FotoKendaraan;
use App\Models\Lokasi;
use App\Models\Tarif;
use App\Models\TipeKendaraan;
use App\Support\HargaSewa;
use App\Support\Ketersediaan;
use App\Support\PencarianSewa;
use App\Support\Seo;
use App\Support\Spesifikasi;
use App\Support\TampilanKendaraan;
use App\Support\TampilanProduk;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SewaController extends Controller
{
    private function query(PencarianSewa $cari, ?string $jenis = null): Builder
    {
        return TipeKendaraan::query()
            ->tampil()
            ->with(['foto', 'tarif'])
            ->whereHas('tarif', fn (Builder $t) => $t->where('mode', $cari->mode)->where('is_active', true))
            ->when($jenis, fn (Builder $q) => $q->where('jenis', $jenis))
            ->when($cari->lokasi, fn (Builder $q, Lokasi $l) => $q->whereHas('unit', fn (Builder $u) => $u->where('lokasi_id', $l->id)->where('status', 'siap')))
            ->when($cari->lengkap(), fn (Builder $q) => $q->withCount([
                'unit as unit_bebas_count' => fn (Builder $u) => Ketersediaan::filterBebas($u, $cari->mulai, $cari->selesai, $cari->lokasi->id),
            ]));
    }

    public function home(Request $request): View
    {
        $cari = PencarianSewa::dari($request->duplicate([]));
        $kendaraan = TipeKendaraan::query()->tampil()->with(['foto', 'tarif'])->take(8)->get();

        return view('toko.home', [
            'cari' => $cari,
            'kendaraan' => $kendaraan->map(fn (TipeKendaraan $t) => TampilanKendaraan::kartu($t)),
            'seo' => ['jsonld' => [Seo::jsonldToko()]],
        ]);
    }

    public function index(Request $request): View
    {
        $cari = PencarianSewa::dari($request);
        $jenis = in_array($request->query('jenis'), array_keys(config('travel.jenis')), true) ? $request->query('jenis') : null;

        $hasil = $this->query($cari, $jenis)->get()
            ->map(fn (TipeKendaraan $t) => TampilanKendaraan::kartu($t, $cari));
        if ($cari->lengkap()) {
            // Yang tersedia di atas.
            $hasil = $hasil->sortBy(fn (array $k) => $k['tersedia'] ? 0 : 1)->values();
        }

        return view('toko.sewa.index', [
            'cari' => $cari,
            'jenis' => $jenis,
            'kendaraan' => $hasil,
            'seo' => ['noindex' => $request->query() !== []],
        ]);
    }

    public function show(Request $request, TipeKendaraan $kendaraan): View
    {
        abort_unless($kendaraan->is_active, 404);
        $kendaraan->load(['foto', 'tarif']);

        $cari = PencarianSewa::dari($request);
        $tarif = $kendaraan->tarifUntuk($cari->mode);
        $rincian = null;
        $tersedia = null;
        if ($cari->lengkap() && $tarif) {
            $rincian = TampilanKendaraan::rincian(HargaSewa::hitung($tarif, $cari->mulai, $cari->selesai));
            $tersedia = Ketersediaan::unitBebas($kendaraan->id, $cari->mulai, $cari->selesai, $cari->lokasi->id)->exists();
        }

        $user = $request->user('web');

        return view('toko.sewa.show', [
            'k' => [
                'id' => $kendaraan->id,
                'slug' => $kendaraan->slug,
                'nama' => $kendaraan->nama(),
                'deskripsi' => $kendaraan->getTranslation('deskripsi_terjemahan', app()->getLocale()),
                'ringkasan' => $kendaraan->ringkasan(),
                'fasilitas' => Spesifikasi::fasilitas($kendaraan->fasilitas),
                'bagasi' => $kendaraan->bagasi,
                'foto' => $kendaraan->foto->map(fn (FotoKendaraan $f) => ['url' => $f->url(), 'thumb' => $f->thumbUrl(), 'warna' => null])->all(),
                'tarif' => $kendaraan->tarif->where('is_active', true)->sortBy('harga_harian')->map(fn (Tarif $t) => [
                    'mode' => Spesifikasi::label('mode', $t->mode),
                    'harian' => TampilanProduk::harga($t->harga_harian),
                    'jam12' => $t->harga_12jam ? TampilanProduk::harga($t->harga_12jam) : null,
                    'minimal' => $t->minimal_jam,
                    'bbm' => $t->termasuk_bbm,
                ])->values()->all(),
            ],
            'cari' => $cari,
            'adaMode' => (bool) $tarif,
            'rincian' => $rincian,
            'tersedia' => $tersedia,
            'perluDokumen' => $cari->mode === Tarif::LEPAS_KUNCI,
            'pembeli' => $user,
            'penyewa' => ['nama' => $user?->nama_lengkap, 'telepon' => null],
            'judul' => $kendaraan->nama(),
            'seo' => ['deskripsi' => $kendaraan->ringkasan(), 'noindex' => $request->query() !== []],
        ]);
    }

    public function pesan(Request $request, TipeKendaraan $kendaraan, BuatBookingAction $buat): RedirectResponse
    {
        $cari = PencarianSewa::dari($request);
        $kembali = route('sewa.show', ['kendaraan' => $kendaraan] + $cari->query());

        if (! $cari->lengkap()) {
            return redirect()->to($kembali)->withErrors($cari->galat ?: ['mulai' => __('Choose location, pick-up and return times first.')]);
        }

        $maksKb = config('travel.booking.maks_dokumen_kb');
        $dokumen = $cari->mode === Tarif::LEPAS_KUNCI ? ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maksKb}"] : ['prohibited'];
        $data = $request->validate([
            'nama_penyewa' => ['required', 'string', 'max:150'],
            'telepon' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'identitas' => $dokumen,
            'sim' => $dokumen,
        ], [
            'telepon.regex' => __('Enter a valid phone number.'),
        ], [
            'nama_penyewa' => __('Renter name'),
            'telepon' => __('Phone / WhatsApp'),
            'identitas' => __('ID card or passport'),
            'sim' => __('Driving licence'),
        ]);

        try {
            $order = $buat->execute(
                $request->user('web'),
                $kendaraan,
                $cari->lokasi,
                $cari->mode,
                $cari->mulai,
                $cari->selesai,
                $data,
                array_filter(['identitas' => $request->file('identitas'), 'sim' => $request->file('sim')]),
                TampilanProduk::mataUang(),
            );
        } catch (TokoException $e) {
            return redirect()->to($kembali)->withErrors(['order' => $e->getMessage()])->withInput($request->except(['identitas', 'sim']));
        }

        return redirect()->route('akun.pesanan.show', $order)
            ->with('status', __('Booking received. Please complete the payment before :time.', ['time' => TampilanKendaraan::waktu($order->kadaluarsa_pada)]));
    }
}
