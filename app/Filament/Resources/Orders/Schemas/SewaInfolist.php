<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Support\LabelAdmin;
use App\Models\Order;
use App\Support\Spesifikasi;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

/** Bagian "Sewa" di halaman detail booking (panel admin). */
class SewaInfolist
{
    private static function waktu($w): string
    {
        return $w ? \Carbon\CarbonImmutable::parse($w)->timezone(config('toko.zona_waktu'))->translatedFormat('D, d M Y H:i') : '—';
    }

    public static function section(): Section
    {
        return Section::make('Sewa')
            ->visible(fn (Order $o) => (bool) $o->bookingSewa)
            ->columnSpan(2)
            ->columns(3)
            ->schema([
                TextEntry::make('sewa_kendaraan')->label('Kendaraan')
                    ->state(fn (Order $o) => $o->bookingSewa->tipe?->nama('id'))
                    ->helperText(fn (Order $o) => Spesifikasi::label('mode', $o->bookingSewa->mode, 'id')),
                TextEntry::make('sewa_unit')->label('Unit')
                    ->state(fn (Order $o) => $o->bookingSewa->unit?->plat_nomor)
                    ->placeholder('Belum ada unit')
                    ->weight('bold'),
                TextEntry::make('sewa_lokasi')->label('Lokasi')
                    ->state(fn (Order $o) => $o->bookingSewa->lokasi?->nama('id')),
                TextEntry::make('sewa_mulai')->label('Ambil')->state(fn (Order $o) => self::waktu($o->bookingSewa->mulai)),
                TextEntry::make('sewa_selesai')->label('Kembali')->state(fn (Order $o) => self::waktu($o->bookingSewa->selesai)),
                TextEntry::make('sewa_durasi')->label('Ditagih')
                    ->state(fn (Order $o) => ($o->bookingSewa->rincian['jam_ditagih'] ?? 0).' jam'),
                TextEntry::make('sewa_penyewa')->label('Penyewa')
                    ->state(fn (Order $o) => $o->bookingSewa->nama_penyewa)
                    ->helperText(fn (Order $o) => $o->bookingSewa->telepon),
                TextEntry::make('sewa_wa')->label('Hubungi')
                    ->state(function (Order $o) {
                        $nomor = preg_replace('/\D+/', '', (string) $o->bookingSewa->telepon);
                        $nomor = str_starts_with($nomor, '0') ? '62'.substr($nomor, 1) : $nomor;

                        return $nomor ? new HtmlString('<a class="underline" target="_blank" rel="noopener" href="https://wa.me/'.e($nomor).'">WhatsApp '.e($o->bookingSewa->telepon).'</a>') : null;
                    })
                    ->placeholder('—'),
                TextEntry::make('sewa_dokumen')->label('Dokumen')
                    ->state(function (Order $o) {
                        $dok = $o->bookingSewa->dokumen ?? [];
                        if (! $dok) {
                            return null;
                        }

                        return new HtmlString(collect(['identitas' => 'KTP/paspor', 'sim' => 'SIM'])
                            ->filter(fn ($label, $jenis) => isset($dok[$jenis]))
                            ->map(fn ($label, $jenis) => '<a class="underline" target="_blank" href="'.e(route('admin.dokumen-sewa', [$o->bookingSewa, $jenis])).'">'.$label.'</a>')
                            ->implode(' · '));
                    })
                    ->placeholder('Tidak perlu (dengan sopir)'),
                TextEntry::make('sewa_sopir')->label('Sopir')
                    ->visible(fn (Order $o) => $o->bookingSewa->mode === 'sopir')
                    ->state(fn (Order $o) => $o->bookingSewa->sopir ? trim(($o->bookingSewa->sopir['nama'] ?? '').' '.($o->bookingSewa->sopir['telepon'] ?? '')) : null)
                    ->placeholder('Belum ditugaskan'),
                TextEntry::make('sewa_catatan')->label('Catatan penyewa')
                    ->state(fn (Order $o) => $o->bookingSewa->catatan)
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('sewa_serah')->label('Serah terima')
                    ->visible(fn (Order $o) => (bool) $o->bookingSewa->serah_terima)
                    ->state(function (Order $o) {
                        $s = $o->bookingSewa->serah_terima;

                        return self::waktu($s['waktu'] ?? null).' · km '.number_format((int) $s['km'], 0, ',', '.').' · BBM '.($s['bbm'] ?? '—')
                            .(filled($s['catatan'] ?? null) ? "\n".$s['catatan'] : '').(filled($s['oleh'] ?? null) ? "\noleh ".$s['oleh'] : '');
                    })
                    ->extraAttributes(['style' => 'white-space: pre-line'])
                    ->columnSpanFull(),
                TextEntry::make('sewa_kembali')->label('Pengembalian')
                    ->visible(fn (Order $o) => (bool) $o->bookingSewa->pengembalian)
                    ->state(function (Order $o) {
                        $p = $o->bookingSewa->pengembalian;

                        return self::waktu($p['waktu'] ?? null).' · km '.number_format((int) $p['km'], 0, ',', '.').' ('.number_format((int) ($p['jarak_km'] ?? 0), 0, ',', '.').' km) · BBM '.($p['bbm'] ?? '—')
                            ."\nTerlambat ".($p['telat_jam'] ?? 0).' jam · denda '.LabelAdmin::rupiah($p['denda_telat'] ?? 0).' · biaya lain '.LabelAdmin::rupiah($p['biaya_lain'] ?? 0)
                            .(filled($p['catatan'] ?? null) ? "\n".$p['catatan'] : '').(filled($p['oleh'] ?? null) ? "\noleh ".$p['oleh'] : '');
                    })
                    ->extraAttributes(['style' => 'white-space: pre-line'])
                    ->columnSpanFull(),
            ]);
    }
}
