<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class CategoryResource extends Resource
{
    /** Travel: menu toko barang disembunyikan (config travel.menu_toko_barang). */
    public static function shouldRegisterNavigation(): bool
    {
        return config('travel.menu_toko_barang') && parent::shouldRegisterNavigation();
    }

    protected static ?string $model = Category::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTag;

    protected static string | UnitEnum | null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'kategori';

    protected static ?string $pluralModelLabel = 'Kategori';

    protected static ?string $navigationLabel = 'Kategori';

    /** Isian kategori; dipakai juga oleh tombol "buat kategori" di form produk. */
    public static function isian(): array
    {
        return [
            Tabs::make('bahasa')
                ->columnSpanFull()
                ->tabs(collect(config('toko.locales'))
                    ->map(fn (string $label, string $locale) => Tab::make($label)
                        ->key('kategori-'.$locale)
                        ->schema([
                            TextInput::make("nama_terjemahan.{$locale}")
                                ->label('Nama kategori')
                                ->required(in_array($locale, config('toko.required_locales'), true))
                                ->maxLength(80),
                        ]))
                    ->values()->all()),
            TextInput::make('slug')
                ->label('Alamat (slug)')
                ->prefix('/produk?kategori=')
                ->helperText('Kosongkan untuk dibuat otomatis dari nama English.')
                ->maxLength(100)
                ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->validationMessages(['regex' => 'Hanya huruf kecil, angka, dan tanda hubung (-).'])
                ->unique(ignoreRecord: true),
            Toggle::make('is_active')->label('Tampil di toko')->default(true)->inline(false),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::isian());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->reorderable('urutan')
            ->paginated(false)
            ->columns([
                TextColumn::make('nama')->label('Kategori')
                    ->state(fn (Category $c) => $c->nama('id'))
                    ->description(fn (Category $c) => $c->nama('en').' · '.$c->nama('zh_TW')),
                TextColumn::make('slug')->label('Slug')->color('gray'),
                TextColumn::make('products_count')->label('Produk')->counts('products'),
                IconColumn::make('is_active')->label('Tampil')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Category $record) {
                        $data['nama_terjemahan'] = $record->getTranslations('nama_terjemahan');

                        return $data;
                    }),
                DeleteAction::make()
                    ->modalDescription('Produk di kategori ini tidak ikut terhapus, hanya menjadi tanpa kategori.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
