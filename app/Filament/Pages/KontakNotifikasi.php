<?php

namespace App\Filament\Pages;

use App\Models\Pengaturan;
use App\Notifications\EmailPemilik as Email;
use App\Support\EmailPemilik;
use App\Support\KontakAdmin;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Notification as Notif;
use UnitEnum;

/**
 * Satu tempat untuk data kontak toko: WhatsApp/LINE/email/telepon (chatbot, FAQ, footer),
 * media sosial (footer), dan email notifikasi untuk pemilik.
 */
class KontakNotifikasi extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedAtSymbol;

    protected static string | UnitEnum | null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Kontak & notifikasi';

    protected static ?string $title = 'Kontak, alamat retur, media sosial & notifikasi email';

    protected static ?string $slug = 'kontak';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasPermissionTo('pengaturan.kelola');
    }

    public function mount(): void
    {
        $this->isiUlang();
    }

    private function isiUlang(): void
    {
        $data = [
            'wa' => ($wa = Pengaturan::ambil('kontak.wa')) ? '+'.$wa : null,
            'line' => Pengaturan::ambil('kontak.line'),
            'email' => Pengaturan::ambil('kontak.email'),
            'telepon' => KontakAdmin::teksTelepon(),
            'email_pemilik' => implode("\n", EmailPemilik::penerima()),
            'alamat_retur' => Pengaturan::ambil('retur.alamat') ?? (config('toko.retur.alamat') ?: null),
            'email_jenis' => array_values(array_filter(array_keys(EmailPemilik::JENIS), fn ($j) => EmailPemilik::aktif($j))),
        ];
        foreach (array_keys(KontakAdmin::MEDSOS) as $k) {
            $data["medsos_{$k}"] = Pengaturan::ambil("medsos.{$k}");
        }
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $medsos = collect(KontakAdmin::MEDSOS)->map(fn ($m, $k) => TextInput::make("medsos_{$k}")
            ->label($m[0])
            ->placeholder($m[1] ? '@akuntoko atau URL lengkap' : 'URL toko, mis. https://shopee.co.id/akuntoko')
            ->maxLength(200)
            ->rule(fn () => function (string $attribute, $value, Closure $fail) use ($k) {
                if (filled($value) && ! KontakAdmin::urlMedsos($k, $value)) {
                    $fail(KontakAdmin::MEDSOS[$k][1] ? 'Isi nama akun (huruf, angka, titik, garis bawah) atau URL lengkap.' : 'Isi URL lengkap yang diawali https://');
                }
            }))->values()->all();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Kontak toko')
                    ->description('Tampil sebagai tombol di chatbot, halaman FAQ, dan footer. Kosongkan yang tidak dipakai.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('wa')->label('Nomor WhatsApp')->tel()->placeholder('0812 3456 7890 atau +886 912 345 678')
                            ->helperText('Nomor yang diawali 0 dianggap nomor Indonesia (+62).'),
                        TextInput::make('line')->label('ID LINE')->placeholder('@kangkung atau idpribadi')
                            ->helperText('Akun resmi LINE diawali @.'),
                        TextInput::make('email')->label('Email layanan pelanggan')->email()->placeholder('halo@tokokamu.com'),
                        TextInput::make('telepon')->label('Telepon')->tel()->placeholder('021 1234 5678 atau +62 21 1234 5678'),
                    ]),
                Section::make('Alamat retur')
                    ->description('Ditampilkan ke pembeli setelah pengajuan retur disetujui, sebagai tujuan kirim balik barang.')
                    ->schema([
                        Textarea::make('alamat_retur')->label('Alamat lengkap')->rows(3)->maxLength(500)
                            ->placeholder("Nama penerima, nomor HP\nJalan, kelurahan, kecamatan\nKota, provinsi, kode pos"),
                    ]),
                Section::make('Media sosial & marketplace')
                    ->description('Tautan tampil di footer semua halaman toko.')
                    ->columns(2)
                    ->schema($medsos),
                Section::make('Email notifikasi untuk pemilik')
                    ->visible(fn () => \App\Support\Fitur::terlihatAdmin('email_pemilik'))
                    ->description('Email rincian pesanan dikirim ke alamat di bawah. Butuh SMTP (secret MAIL_*) di server; sebelum itu email hanya tercatat di log server.')
                    ->columns(2)
                    ->schema([
                        Textarea::make('email_pemilik')->label('Alamat email penerima')->rows(3)
                            ->placeholder("pemilik@gmail.com\ngudang@gmail.com")
                            ->helperText('Satu alamat per baris (atau pisahkan dengan koma).')
                            ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                                $salah = collect(preg_split('/[\s,;]+/', (string) $value))->filter()
                                    ->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
                                if ($salah->isNotEmpty()) {
                                    $fail('Alamat tidak valid: '.$salah->implode(', '));
                                }
                            }),
                        CheckboxList::make('email_jenis')->label('Kirim email saat')
                            ->options(collect(EmailPemilik::JENIS)->map(fn ($j) => $j[0])->all()),
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

        Pengaturan::simpan('kontak.wa', KontakAdmin::normalisasiWa($d['wa'] ?? null));
        Pengaturan::simpan('kontak.line', trim((string) ($d['line'] ?? '')) ?: null);
        Pengaturan::simpan('kontak.email', mb_strtolower(trim((string) ($d['email'] ?? ''))) ?: null);
        Pengaturan::simpan('kontak.telepon', KontakAdmin::normalisasiWa($d['telepon'] ?? null));
        foreach (array_keys(KontakAdmin::MEDSOS) as $k) {
            Pengaturan::simpan("medsos.{$k}", trim((string) ($d["medsos_{$k}"] ?? '')) ?: null);
        }
        Pengaturan::simpan('retur.alamat', trim((string) ($d['alamat_retur'] ?? '')) ?: null);
        // Bagian email tersembunyi kalau fiturnya mati: jangan timpa isian yang tersimpan.
        if (array_key_exists('email_pemilik', $d)) {
            Pengaturan::simpan('email_pemilik.alamat', implode(',', EmailPemilik::pecah($d['email_pemilik'] ?? null)) ?: null);
            foreach (array_keys(EmailPemilik::JENIS) as $j) {
                Pengaturan::simpan("email_pemilik.{$j}", in_array($j, $d['email_jenis'] ?? [], true) ? '1' : '0');
            }
        }

        $this->isiUlang();
        Notification::make()->success()->title('Tersimpan')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ujiEmail')
                ->visible(fn () => \App\Support\Fitur::aktif('email_pemilik'))
                ->label('Kirim email uji')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn () => EmailPemilik::penerima()
                    ? 'Email uji dikirim ke: '.implode(', ', EmailPemilik::penerima()).'. Simpan dulu kalau baru mengubah alamat.'
                    : 'Belum ada alamat penerima yang tersimpan.')
                ->action(function () {
                    if (! EmailPemilik::penerima()) {
                        Notification::make()->warning()->title('Isi & simpan alamat email penerima dulu')->send();

                        return;
                    }
                    Notif::route('mail', EmailPemilik::penerima())->notifyNow(new Email(
                        'uji', 'Email uji', 'Kalau email ini sampai, notifikasi pesanan untuk pemilik sudah siap.',
                    ));
                    $log = config('mail.default') === 'log';
                    Notification::make()->success()
                        ->title($log ? 'Email uji dicatat di log (SMTP belum diisi)' : 'Email uji terkirim')
                        ->body($log ? 'Isi secret MAIL_* di GitHub lalu deploy ulang supaya email benar-benar terkirim.' : 'Cek kotak masuk (dan folder spam).')
                        ->send();
                }),
        ];
    }
}
