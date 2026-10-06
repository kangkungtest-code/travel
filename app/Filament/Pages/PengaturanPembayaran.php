<?php

namespace App\Filament\Pages;

use App\Models\Payment;
use App\Models\Pengaturan;
use App\Payments\MetodePembayaran;
use App\Support\AkunPembayaran;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Akun pembayaran milik toko: kunci Xendit & PayPal (terenkripsi), metode yang ditawarkan,
 * bank VA, dan alamat webhook yang perlu didaftarkan di dashboard gateway.
 */
class PengaturanPembayaran extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string | UnitEnum | null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?string $title = 'Akun & metode pembayaran';

    protected static ?string $slug = 'pembayaran';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasPermissionTo('pembayaran.kelola');
    }

    public function mount(): void
    {
        $this->isiUlang();
    }

    private function isiUlang(): void
    {
        $data = [
            'va_banks' => AkunPembayaran::bankVa(),
            'paypal_mode' => AkunPembayaran::nilai('paypal.mode') ?? 'sandbox',
        ];
        foreach (array_keys(AkunPembayaran::METODE) as $kode) {
            $data["aktif_{$kode}"] = AkunPembayaran::metodeNyala($kode);
        }
        // Isian biasa diisi nilai panelnya; isian rahasia selalu kosong (kosong = tidak diubah).
        foreach (AkunPembayaran::ISIAN as $kunci => [, $rahasia]) {
            if (! $rahasia && $kunci !== 'paypal.mode') {
                $data[self::nama($kunci)] = AkunPembayaran::dariPanel($kunci);
            }
        }
        $this->form->fill($data);
    }

    private static function nama(string $kunci): string
    {
        return str_replace('.', '_', $kunci);
    }

    /** Keterangan di bawah isian: nilai yang sedang dipakai & asalnya. */
    private static function keterangan(string $kunci): string
    {
        $nilai = AkunPembayaran::nilai($kunci);
        $tampil = AkunPembayaran::ISIAN[$kunci][1] ? AkunPembayaran::samarkan($nilai) : $nilai;

        return match (AkunPembayaran::sumber($kunci)) {
            'panel' => "Tersimpan: {$tampil}",
            'server' => "Sekarang memakai nilai dari server ({$tampil}). Isi di sini untuk memakai akun toko sendiri.",
            default => 'Belum diisi.',
        };
    }

    private function isianRahasia(string $kunci, string $placeholder): array
    {
        $nama = self::nama($kunci);

        return [
            TextInput::make($nama)
                ->label(AkunPembayaran::ISIAN[$kunci][0])
                ->password()->revealable()
                ->autocomplete('new-password')
                ->placeholder($placeholder)
                ->maxLength(500)
                ->helperText(fn () => self::keterangan($kunci).(AkunPembayaran::sumber($kunci) === 'panel' ? ' — kosongkan kalau tidak ingin mengganti.' : '')),
            Checkbox::make("hapus_{$nama}")
                ->label('Hapus nilai yang tersimpan di panel')
                ->visible(fn () => AkunPembayaran::sumber($kunci) === 'panel'),
        ];
    }

    private static function statusMetode(string $kode): string
    {
        if (! AkunPembayaran::metodeNyala($kode)) {
            return '⏸ Dimatikan';
        }

        return MetodePembayaran::ditawarkan($kode) ? '✅ Tampil di halaman bayar' : '⚠️ Belum tampil — akun belum lengkap';
    }

    private static function langkah(array $baris): HtmlString
    {
        return new HtmlString('<ol style="list-style:decimal;padding-left:1.25rem;display:grid;gap:.25rem">'
            .collect($baris)->map(fn ($b) => "<li>{$b}</li>")->implode('').'</ol>');
    }

    public function form(Schema $schema): Schema
    {
        $kodeUrl = fn (string $url) => '<code style="user-select:all;word-break:break-all">'.e($url).'</code>';

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Metode yang ditawarkan ke pembeli')
                    ->description('Metode yang dimatikan tidak muncul di halaman bayar. Pesanan lama yang sudah memakai metode itu tetap diproses.')
                    ->schema(collect(AkunPembayaran::METODE)->map(fn ($label, $kode) => Toggle::make("aktif_{$kode}")
                        ->visible(fn () => $kode !== Payment::GATEWAY_PAYPAL || \App\Support\Fitur::terlihatAdmin('paypal'))
                        ->label($label)
                        ->helperText(fn () => self::statusMetode($kode)))->values()->all()),

                Section::make('Xendit — QRIS & Virtual Account')
                    ->description('Uang dari pembeli masuk ke saldo akun Xendit toko, lalu bisa ditarik ke rekening bank.')
                    ->collapsible()
                    ->schema([
                        Html::make(self::langkah([
                            'Daftar / masuk di <a href="https://dashboard.xendit.co" target="_blank" rel="noopener" style="text-decoration:underline">dashboard.xendit.co</a>. Untuk uang sungguhan, akun harus lulus verifikasi usaha (mode Live).',
                            'Settings → API Keys → <b>Generate secret key</b>, beri izin <b>Write</b> untuk "Money-in". Salin kuncinya ke isian di bawah.',
                            'Settings → Webhooks → isi URL untuk event <b>Payment</b> (payment.capture / payment.failure) dengan: '.$kodeUrl(route('webhook.xendit')),
                            'Di halaman Webhooks yang sama, salin <b>Webhook verification token</b> ke isian di bawah.',
                            'Simpan, lalu tekan <b>Tes koneksi Xendit</b> di kanan atas.',
                        ])),
                        ...$this->isianRahasia('xendit.secret_key', 'xnd_development_… atau xnd_production_…'),
                        ...$this->isianRahasia('xendit.callback_token', 'Token dari Settings → Webhooks'),
                        CheckboxList::make('va_banks')
                            ->label('Bank Virtual Account yang ditawarkan')
                            ->options(array_combine(AkunPembayaran::BANK_VA, AkunPembayaran::BANK_VA))
                            ->columns(4)
                            ->helperText('Pilih bank yang sudah aktif di akun Xendit toko (Settings → Payment Channels).'),
                    ]),

                Section::make('PayPal — untuk pembeli luar negeri')
                    ->visible(fn () => \App\Support\Fitur::terlihatAdmin('paypal'))
                    ->description('Tagihan PayPal dalam USD atau TWD (PayPal tidak menerima rupiah).')
                    ->collapsible()
                    ->collapsed(fn () => AkunPembayaran::sumber('paypal.client_id') === null)
                    ->schema([
                        Html::make(self::langkah([
                            'Masuk ke <a href="https://developer.paypal.com/dashboard/applications" target="_blank" rel="noopener" style="text-decoration:underline">developer.paypal.com</a> dengan akun PayPal Business.',
                            'Apps & Credentials → pilih <b>Sandbox</b> (uji coba) atau <b>Live</b> → Create App. Salin Client ID & Secret.',
                            'Di app itu, Add Webhook dengan URL '.$kodeUrl(route('webhook.paypal')).' dan event <b>Payment capture completed</b>. Salin Webhook ID.',
                        ])),
                        Radio::make('paypal_mode')->label('Mode')->inline()
                            ->options(['sandbox' => 'Sandbox (uji coba)', 'live' => 'Live (uang sungguhan)'])
                            ->helperText(app()->isProduction() ? null : 'Server ini server uji coba: mode Live tidak akan aktif di sini.'),
                        TextInput::make('paypal_client_id')->label('Client ID PayPal')->maxLength(200)
                            ->helperText(fn () => AkunPembayaran::sumber('paypal.client_id') === 'server' ? self::keterangan('paypal.client_id') : null),
                        ...$this->isianRahasia('paypal.client_secret', 'Secret dari Apps & Credentials'),
                        TextInput::make('paypal_webhook_id')->label('Webhook ID PayPal')->maxLength(100),
                    ]),
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

        // Isian yang disembunyikan (fitur mati) tidak ada di $d: biarkan nilai tersimpannya.
        foreach (array_keys(AkunPembayaran::METODE) as $kode) {
            if (! array_key_exists("aktif_{$kode}", $d)) {
                continue;
            }
            Pengaturan::simpan("bayar.aktif.{$kode}", ($d["aktif_{$kode}"] ?? false) ? '1' : '0');
        }
        // '-' = sengaja tidak ada bank (nilai kosong berarti "pakai bawaan").
        Pengaturan::simpan('bayar.xendit.va_banks', implode(',', array_intersect(AkunPembayaran::BANK_VA, $d['va_banks'] ?? [])) ?: '-');
        array_key_exists('paypal_mode', $d) && AkunPembayaran::simpan('paypal.mode', in_array($d['paypal_mode'] ?? null, ['sandbox', 'live'], true) ? $d['paypal_mode'] : 'sandbox');

        foreach (AkunPembayaran::ISIAN as $kunci => [, $rahasia]) {
            if ($kunci === 'paypal.mode') {
                continue;
            }
            $nama = self::nama($kunci);
            if (! array_key_exists($nama, $d)) {
                continue;
            }
            if ($rahasia) {
                // Kosong = tidak diubah, kecuali dicentang hapus.
                if ($d["hapus_{$nama}"] ?? false) {
                    AkunPembayaran::simpan($kunci, null);
                } elseif (filled($d[$nama] ?? null)) {
                    AkunPembayaran::simpan($kunci, $d[$nama]);
                }
            } else {
                AkunPembayaran::simpan($kunci, $d[$nama] ?? null);
            }
        }

        $peringatan = str_starts_with((string) AkunPembayaran::nilai('xendit.secret_key'), 'xnd_production') && ! app()->isProduction();

        $this->isiUlang();
        Notification::make()->success()->title('Tersimpan')
            ->body($peringatan ? 'Catatan: kunci Xendit LIVE tidak dipakai di server uji coba ini.' : null)
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tesXendit')
                ->label('Tes koneksi Xendit')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(fn () => $this->kabar(AkunPembayaran::tesXendit(AkunPembayaran::nilai('xendit.secret_key')), 'Xendit')),
            Action::make('tesPayPal')
                ->label('Tes koneksi PayPal')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(fn () => $this->kabar(AkunPembayaran::tesPayPal(
                    AkunPembayaran::nilai('paypal.client_id'),
                    AkunPembayaran::nilai('paypal.client_secret'),
                    AkunPembayaran::nilai('paypal.mode') ?? 'sandbox',
                ), 'PayPal')),
        ];
    }

    /** @param array{ok: bool, pesan: string} $hasil */
    private function kabar(array $hasil, string $nama): void
    {
        Notification::make()
            ->title($hasil['ok'] ? "{$nama} terhubung" : "{$nama} belum terhubung")
            ->body($hasil['pesan'].' (Yang dites adalah nilai yang sudah disimpan.)')
            ->{$hasil['ok'] ? 'success' : 'danger'}()
            ->send();
    }
}
