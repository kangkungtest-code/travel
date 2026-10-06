<?php

namespace App\Filament\Resources\HalamanKebijakans;

use App\Filament\Resources\HalamanKebijakans\Pages\EditHalamanKebijakan;
use App\Filament\Resources\HalamanKebijakans\Pages\ListHalamanKebijakans;
use App\Models\HalamanKebijakan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class HalamanKebijakanResource extends Resource
{
    protected static ?string $model = HalamanKebijakan::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string | UnitEnum | null $navigationGroup = 'Konten';

    protected static ?string $modelLabel = 'halaman kebijakan';

    protected static ?string $pluralModelLabel = 'Halaman kebijakan';

    protected static ?string $navigationLabel = 'Halaman kebijakan';

    protected static ?string $slug = 'kebijakan';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->description('Isi memakai format Markdown: "## Judul bagian", "- poin", "**tebal**". Bagian bertanda [ISI: ...] adalah draf yang wajib kamu lengkapi sebelum toko dibuka. Draf ini contoh, bukan nasihat hukum.')
                    ->schema([
                        Tabs::make('bahasa')
                            ->tabs(collect(config('toko.locales'))
                                ->map(fn (string $label, string $locale) => Tab::make($label)
                                    ->key('kebijakan-'.$locale)
                                    ->schema([
                                        TextInput::make("judul_terjemahan.{$locale}")->label('Judul')
                                            ->required(in_array($locale, config('toko.required_locales'), true))->maxLength(150),
                                        MarkdownEditor::make("isi_terjemahan.{$locale}")->label('Isi')
                                            ->required(in_array($locale, config('toko.required_locales'), true))
                                            ->toolbarButtons([['heading', 'bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']]),
                                    ]))
                                ->values()->all()),
                        Toggle::make('is_active')->label('Tampil di toko')->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->paginated(false)
            ->columns([
                TextColumn::make('judul')->label('Halaman')
                    ->state(fn (HalamanKebijakan $h) => $h->getTranslation('judul_terjemahan', 'id'))
                    ->description(fn (HalamanKebijakan $h) => '/kebijakan/'.$h->slug),
                TextColumn::make('perlu_diisi')->label('Perlu dilengkapi')
                    ->state(fn (HalamanKebijakan $h) => $h->jumlahPerluDiisi())
                    ->formatStateUsing(fn (int $state) => $state ? "{$state} bagian [ISI: …] per bahasa" : 'Lengkap')
                    ->badge()
                    ->color(fn (int $state) => $state ? 'warning' : 'success'),
                IconColumn::make('is_active')->label('Tampil')->boolean(),
                TextColumn::make('updated_at')->label('Terakhir diubah')
                    ->dateTime('d M Y H:i', timezone: config('toko.zona_waktu')),
            ])
            ->recordActions([
                Action::make('lihat')->label('Lihat')->icon(Heroicon::OutlinedEye)
                    ->url(fn (HalamanKebijakan $h) => route('kebijakan', $h))->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHalamanKebijakans::route('/'),
            'edit' => EditHalamanKebijakan::route('/{record}/edit'),
        ];
    }
}
