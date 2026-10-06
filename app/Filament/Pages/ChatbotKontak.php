<?php

namespace App\Filament\Pages;

use App\Models\Pengaturan;
use App\Support\Chatbot\Chatbot;
use App\Support\Chatbot\FormatSalah;
use App\Support\KontakAdmin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Facades\File;
use UnitEnum;

/**
 * Pengaturan chatbot: nomor WhatsApp & ID LINE admin, plus isi file teks balasan.
 */
class ChatbotKontak extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string | UnitEnum | null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Chatbot';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Balasan chatbot';

    protected static ?string $slug = 'chatbot';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return \App\Support\Fitur::terlihatAdmin('chatbot') && (bool) Filament::auth()->user()?->hasPermissionTo('faq.kelola');
    }

    public function mount(): void
    {
        $this->form->fill(['balasan' => Chatbot::isiFile()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('File balasan chatbot')
                    ->description('Chatbot mencocokkan kata kunci di pertanyaan pembeli dengan topik di bawah. Petunjuk format ada di bagian atas file (baris yang diawali #). Tombol WhatsApp/LINE/email/telepon diambil dari Pengaturan → Kontak & notifikasi.')
                    ->schema([
                        Textarea::make('balasan')->hiddenLabel()->rows(28)->required()
                            ->extraInputAttributes(['style' => 'font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; line-height: 1.5;', 'spellcheck' => 'false']),
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
                    Actions::make([
                        Action::make('simpan')->label('Simpan')->submit('simpan'),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function simpan(): void
    {
        $data = $this->form->getState();

        try {
            Chatbot::simpanFile($data['balasan']);
        } catch (FormatSalah $e) {
            Notification::make()->danger()->title('File balasan belum disimpan')->body($e->getMessage())->persistent()->send();

            return;
        }

        $this->form->fill(['balasan' => Chatbot::isiFile()]);

        Notification::make()->success()->title('Tersimpan')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('coba')
                ->label('Coba chatbot')
                ->icon(Heroicon::OutlinedPlay)
                ->color('gray')
                ->modalSubmitActionLabel('Tanya')
                ->schema([
                    TextInput::make('pesan')->label('Pertanyaan')->required(),
                    Select::make('bahasa')->options(config('toko.locales'))->default('id')->required(),
                ])
                ->action(function (array $data) {
                    $j = (new Chatbot)->jawab($data['pesan'], $data['bahasa']);
                    Notification::make()
                        ->title('Topik: '.$j['topik'].($j['admin'] ? ' (+ tombol kontak admin)' : ''))
                        ->body($j['teks'])
                        ->persistent()
                        ->send();
                })
                ->modalDescription('Memakai file yang sudah disimpan.'),

            Action::make('bawaan')
                ->label('Kembalikan ke bawaan')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('File balasan hasil edit akan dihapus dan diganti isi bawaan.')
                ->action(function () {
                    File::delete(Chatbot::pathEdit());
                    $this->data['balasan'] = Chatbot::isiFile();
                    Notification::make()->success()->title('File balasan dikembalikan ke bawaan')->send();
                }),
        ];
    }
}
