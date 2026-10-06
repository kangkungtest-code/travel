<?php

namespace App\Filament\Resources\Kendaraan;

use App\Filament\Resources\Kendaraan\Pages\CreateKendaraan;
use App\Filament\Resources\Kendaraan\Pages\EditKendaraan;
use App\Filament\Resources\Kendaraan\Pages\ListKendaraan;
use App\Filament\Resources\Kendaraan\RelationManagers\UnitRelationManager;
use App\Models\TipeKendaraan;
use App\Models\UnitKendaraan;
use App\Support\ProductImageStorage;
use App\Support\Spesifikasi;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class KendaraanResource extends Resource
{
    protected static ?string $model = TipeKendaraan::class;

    protected static ?string $slug = 'kendaraan';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string | UnitEnum | null $navigationGroup = 'Armada';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'kendaraan';

    protected static ?string $pluralModelLabel = 'kendaraan';

    protected static ?string $navigationLabel = 'Kendaraan';

    public static function form(Schema $schema): Schema
    {
        $foto = config('toko.product_images');

        return $schema
            ->columns(3)
            ->components([
                Section::make('Nama & deskripsi')
                    ->description('Nama tipe yang dilihat pembeli, mis. "Toyota Avanza". Plat nomor diisi per unit di bawah. Bahasa yang kosong otomatis memakai English.')
                    ->columnSpan(2)
                    ->schema([
                        Tabs::make('bahasa')
                            ->tabs(collect(config('toko.locales'))
                                ->map(fn (string $label, string $locale) => Tab::make($label)
                                    ->key('bahasa-'.$locale)
                                    ->schema([
                                        TextInput::make("nama_terjemahan.{$locale}")
                                            ->label('Nama kendaraan')
                                            ->required(in_array($locale, config('toko.required_locales'), true))
                                            ->maxLength(150),
                                        Textarea::make("deskripsi_terjemahan.{$locale}")
                                            ->label('Deskripsi')
                                            ->rows(5),
                                    ]))
                                ->values()->all()),
                    ]),

                Section::make('Pengaturan')
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Tampil di situs')
                            ->helperText('Tetap tersembunyi selama belum ada unit berstatus "Siap disewa".')
                            ->default(true),
                        TextInput::make('slug')
                            ->label('Alamat halaman')
                            ->prefix('/kendaraan/')
                            ->helperText('Kosongkan untuk dibuat otomatis dari nama English.')
                            ->maxLength(160)
                            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->validationMessages(['regex' => 'Hanya huruf kecil, angka, dan tanda hubung (-).'])
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make('Spesifikasi')
                    ->columnSpanFull()
                    ->columns(5)
                    ->schema([
                        Select::make('jenis')->label('Jenis')->options(Spesifikasi::pilihan('jenis'))->required()->native(false)->default('mobil'),
                        TextInput::make('kursi')->label('Kursi penumpang')->integer()->minValue(1)->maxValue(80)->required(),
                        Select::make('transmisi')->label('Transmisi')->options(Spesifikasi::pilihan('transmisi'))->required()->native(false)->default('otomatis'),
                        Select::make('bbm')->label('Bahan bakar')->options(Spesifikasi::pilihan('bbm'))->required()->native(false)->default('bensin'),
                        TextInput::make('bagasi')->label('Koper besar')->helperText('Perkiraan jumlah koper.')->integer()->minValue(0)->maxValue(99),
                        CheckboxList::make('fasilitas')
                            ->label('Fasilitas')
                            ->options(Spesifikasi::pilihan('fasilitas'))
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Foto')
                    ->description('Maksimal '.config('travel.foto.max_per_tipe').' foto. Otomatis diperkecil & dikonversi ke WebP. Foto pertama jadi foto utama — geser untuk mengubah urutan.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('foto')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('urutan')
                            ->reorderable()
                            ->maxItems(config('travel.foto.max_per_tipe'))
                            ->defaultItems(0)
                            ->addActionLabel('Tambah foto')
                            ->grid(4)
                            ->schema([
                                FileUpload::make('path')
                                    ->hiddenLabel()
                                    ->image()
                                    ->disk($foto['disk'])
                                    ->visibility('public')
                                    ->maxSize($foto['max_upload_kb'])
                                    ->required()
                                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ProductImageStorage::class)->store($file, config('travel.foto.directory'))),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with('foto')->withCount([
                'unit',
                'unit as unit_siap_count' => fn (Builder $u) => $u->where('status', UnitKendaraan::SIAP),
            ]))
            ->defaultSort('urutan')
            ->reorderable('urutan')
            ->columns([
                ImageColumn::make('gambar')
                    ->label('')
                    ->state(fn (TipeKendaraan $t): ?string => $t->foto->first()?->thumbUrl())
                    ->imageHeight(48),
                TextColumn::make('nama')
                    ->label('Kendaraan')
                    ->state(fn (TipeKendaraan $t) => $t->nama('id'))
                    ->description(fn (TipeKendaraan $t) => $t->ringkasan('id'))
                    ->searchable(query: fn (Builder $q, string $cari) => $q->where(fn ($w) => $w
                        ->where('slug', 'like', '%'.str($cari)->slug().'%')
                        ->orWhere('nama_terjemahan', 'like', '%'.$cari.'%'))),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->formatStateUsing(fn (string $state) => Spesifikasi::label('jenis', $state, 'id'))
                    ->badge(),
                TextColumn::make('unit_siap_count')
                    ->label('Unit siap')
                    ->state(fn (TipeKendaraan $t) => "{$t->unit_siap_count} / {$t->unit_count}")
                    ->color(fn (TipeKendaraan $t) => $t->unit_siap_count > 0 ? null : 'danger'),
                IconColumn::make('is_active')->label('Tampil')->boolean(),
            ])
            ->filters([
                SelectFilter::make('jenis')->label('Jenis')->options(Spesifikasi::pilihan('jenis')),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return ['unit' => UnitRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKendaraan::route('/'),
            'create' => CreateKendaraan::route('/create'),
            'edit' => EditKendaraan::route('/{record}/edit'),
        ];
    }
}
