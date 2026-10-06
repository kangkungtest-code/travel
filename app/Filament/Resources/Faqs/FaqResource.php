<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string | UnitEnum | null $navigationGroup = 'Konten';

    protected static ?string $modelLabel = 'FAQ';

    protected static ?string $pluralModelLabel = 'FAQ';

    protected static ?string $navigationLabel = 'FAQ';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Tabs::make('bahasa')
                    ->columnSpanFull()
                    ->tabs(collect(config('toko.locales'))
                        ->map(fn (string $label, string $locale) => Tab::make($label)
                            ->key('faq-'.$locale)
                            ->schema([
                                TextInput::make("pertanyaan_terjemahan.{$locale}")->label('Pertanyaan')
                                    ->required(in_array($locale, config('toko.required_locales'), true))->maxLength(250),
                                Textarea::make("jawaban_terjemahan.{$locale}")->label('Jawaban')
                                    ->required(in_array($locale, config('toko.required_locales'), true))->rows(4),
                            ]))
                        ->values()->all()),
                TextInput::make('urutan')->integer()->minValue(0)->default(fn () => (int) Faq::max('urutan') + 1)->required(),
                Toggle::make('is_active')->label('Tampil di toko')->default(true)->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->reorderable('urutan')
            ->columns([
                TextColumn::make('urutan')->label('#'),
                TextColumn::make('pertanyaan')->label('Pertanyaan')
                    ->state(fn (Faq $f) => $f->getTranslation('pertanyaan_terjemahan', 'id'))
                    ->description(fn (Faq $f) => $f->getTranslation('pertanyaan_terjemahan', 'en')),
                IconColumn::make('is_active')->label('Tampil')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Faq $record) {
                        foreach ($record->getTranslatableAttributes() as $a) {
                            $data[$a] = $record->getTranslations($a);
                        }

                        return $data;
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFaqs::route('/')];
    }
}
