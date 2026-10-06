<?php

namespace App\Filament\Pages;

use App\Models\Pengaturan;
use App\Support\Fitur;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Khusus Super Admin (pemilik platform): paket & fitur yang menyala di instalasi ini.
 * Mematikan fitur tidak menghapus data — hanya menyembunyikan menu/halaman/API-nya.
 */
class FiturPaket extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string | UnitEnum | null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationLabel = 'Fitur & paket';

    protected static ?string $title = 'Fitur & paket (Super Admin)';

    protected static ?string $slug = 'fitur';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasPermissionTo('fitur.kelola');
    }

    public function mount(): void
    {
        $this->isiUlang();
    }

    private function isiUlang(): void
    {
        $data = [
            'paket' => (string) (Fitur::paket() ?? max(Fitur::daftarPaket())),
            'tambahan' => Fitur::tambahan(),
        ];
        foreach (Fitur::daftarPaket() as $n) {
            $data["nama_paket_{$n}"] = Pengaturan::ambil("fitur.nama_paket.{$n}");
        }
        $this->form->fill($data);
    }

    private static function namaFitur(string $kunci): string
    {
        return Fitur::daftar()[$kunci][0] ?? $kunci;
    }

    private static function ringkasPaket(int $n): string
    {
        $isi = Fitur::isiPaket($n);

        return $isi ? implode(', ', array_map(self::namaFitur(...), $isi)) : 'Fitur inti saja';
    }

    private static function tabelStatus(): HtmlString
    {
        $baris = collect(Fitur::daftar())->map(function ($f, $kunci) {
            $status = ! $f[2] ? '<span style="color:#888">belum tersedia</span>'
                : (Fitur::aktif($kunci) ? '<b style="color:#15803d">menyala</b>' : '<span style="color:#b91c1c">mati</span>');

            return '<tr><td style="padding:.3rem .8rem .3rem 0"><b>'.e($f[0]).'</b><br><span style="color:#666;font-size:.85em">'.e($f[1]).'</span></td>'
                .'<td style="padding:.3rem 0;white-space:nowrap">'.$status.'</td></tr>';
        })->implode('');

        return new HtmlString('<table style="width:100%;border-collapse:collapse">'.$baris.'</table>');
    }

    private static function riwayat(): HtmlString
    {
        $baris = DB::table('riwayat_fitur')->leftJoin('users', 'users.id', '=', 'riwayat_fitur.user_id')
            ->orderByDesc('riwayat_fitur.id')->limit(10)
            ->get(['riwayat_fitur.*', 'users.email'])
            ->map(function ($r) {
                $nyala = array_map(self::namaFitur(...), json_decode($r->dinyalakan, true) ?: []);
                $mati = array_map(self::namaFitur(...), json_decode($r->dimatikan, true) ?: []);
                $paket = ($r->paket_dari ? Fitur::namaPaket((int) $r->paket_dari) : 'semua fitur').' → '.Fitur::namaPaket((int) $r->paket_ke);

                return '<li><b>'.e(\Illuminate\Support\Carbon::parse($r->created_at)->timezone(config('toko.zona_waktu'))->format('d M Y H:i')).'</b> · '.e($paket)
                    .($nyala ? ' · <span style="color:#15803d">+ '.e(implode(', ', $nyala)).'</span>' : '')
                    .($mati ? ' · <span style="color:#b91c1c">− '.e(implode(', ', $mati)).'</span>' : '')
                    .' · '.e($r->email ?? '—').'</li>';
            });

        return new HtmlString($baris->isEmpty() ? '<p style="color:#666">Belum ada perubahan.</p>'
            : '<ul style="display:grid;gap:.35rem">'.$baris->implode('').'</ul>');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Status sekarang')
                    ->description(fn () => Fitur::paket() === null
                        ? 'Paket belum pernah dipilih: semua fitur menyala.'
                        : 'Paket aktif: '.Fitur::namaPaket(Fitur::paket()).(Fitur::tambahan() ? ' + '.count(Fitur::tambahan()).' fitur tambahan' : '').'.')
                    ->collapsible()
                    ->schema([Html::make(fn () => self::tabelStatus())]),

                Section::make('Paket & fitur tambahan')
                    ->description('Fitur yang dimatikan hilang dari panel, toko, dan API, tapi datanya TIDAK dihapus. Menurunkan paket ditolak kalau fitur yang akan mati masih punya transaksi berjalan.')
                    ->schema([
                        Radio::make('paket')->label('Paket')
                            ->options(collect(Fitur::daftarPaket())->mapWithKeys(fn ($n) => [(string) $n => Fitur::namaPaket($n)])->all())
                            ->descriptions(collect(Fitur::daftarPaket())->mapWithKeys(fn ($n) => [(string) $n => self::ringkasPaket($n)])->all())
                            ->required()
                            ->live(),
                        CheckboxList::make('tambahan')->label('Fitur tambahan di luar paket (add-on)')
                            ->options(collect(Fitur::siap())->mapWithKeys(fn ($k) => [$k => self::namaFitur($k)])->all())
                            ->helperText(fn (Get $get) => 'Sudah termasuk '.Fitur::namaPaket((int) $get('paket')).': '.self::ringkasPaket((int) $get('paket')).'. Centang yang termasuk paket diabaikan.')
                            ->columns(3),
                    ]),

                Section::make('Nama paket')
                    ->description('Kosongkan untuk memakai nama bawaan "Paket 1/2/3".')
                    ->collapsed()
                    ->columns(3)
                    ->schema(collect(Fitur::daftarPaket())->map(fn ($n) => TextInput::make("nama_paket_{$n}")
                        ->label("Paket {$n}")->placeholder("Paket {$n}")->maxLength(40))->all()),

                Section::make('Riwayat perubahan')
                    ->collapsed()
                    ->schema([Html::make(fn () => self::riwayat())]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('simpan')
                ->footer([
                    Actions::make([Action::make('simpan')->label('Simpan')->submit('simpan')])->key('form-actions'),
                ]),
        ]);
    }

    public function simpan(): void
    {
        $d = $this->form->getState();

        foreach (Fitur::daftarPaket() as $n) {
            Pengaturan::simpan("fitur.nama_paket.{$n}", trim((string) ($d["nama_paket_{$n}"] ?? '')) ?: null);
        }

        $penghalang = Fitur::terapkan((int) $d['paket'], $d['tambahan'] ?? [], Filament::auth()->user());
        if ($penghalang) {
            Notification::make()->danger()->persistent()
                ->title('Belum bisa disimpan')
                ->body(new HtmlString(collect($penghalang)
                    ->map(fn ($alasan, $k) => '<b>'.e(self::namaFitur($k)).'</b>: '.e(implode('; ', $alasan)))
                    ->implode('<br>').'<br>Selesaikan dulu transaksinya, lalu coba lagi.'))
                ->send();

            return;
        }

        $this->isiUlang();
        Notification::make()->success()->title('Tersimpan')->send();
    }
}
