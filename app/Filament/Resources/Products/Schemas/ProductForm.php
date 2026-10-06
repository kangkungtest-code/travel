<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Support\ProductImageStorage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        $foto = config('toko.product_images');

        return $schema
            ->columns(3)
            ->components([
                Section::make('Nama & deskripsi')
                    ->description('Isi minimal English dan Bahasa Indonesia. Bahasa yang kosong otomatis memakai English.')
                    ->columnSpan(2)
                    ->schema([
                        Tabs::make('bahasa')
                            ->tabs(collect(config('toko.locales'))
                                ->map(fn (string $label, string $locale) => Tab::make($label)
                                    ->key('bahasa-'.$locale)
                                    ->schema([
                                        TextInput::make("nama_terjemahan.{$locale}")
                                            ->label('Nama produk')
                                            ->required(in_array($locale, config('toko.required_locales'), true))
                                            ->maxLength(200),
                                        Textarea::make("deskripsi_terjemahan.{$locale}")
                                            ->label('Deskripsi')
                                            ->rows(6),
                                    ]))
                                ->values()
                                ->all()),
                    ]),

                Section::make('Pengaturan')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'slug', fn ($query) => $query->orderBy('urutan'))
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Category $c) => $c->nama('id'))
                            ->searchable(['slug', 'nama_terjemahan'])
                            ->preload()
                            ->createOptionForm(\App\Filament\Resources\Categories\CategoryResource::isian())
                            ->createOptionModalHeading('Kategori baru')
                            ->helperText('Kelola daftar kategori di menu Katalog → Kategori.'),
                        Toggle::make('is_active')
                            ->label('Tampil di toko')
                            ->default(true),
                        TextInput::make('slug')
                            ->label('Alamat halaman')
                            ->prefix('/produk/')
                            ->helperText('Kosongkan untuk dibuat otomatis dari nama English. Sebaiknya tidak diubah setelah toko ramai, karena tautan yang sudah dibagikan ikut berubah.')
                            ->maxLength(180)
                            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->validationMessages(['regex' => 'Hanya huruf kecil, angka, dan tanda hubung (-).'])
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make('Foto')
                    ->description("Maksimal {$foto['max_per_product']} foto. Otomatis diperkecil & dikonversi ke WebP. Foto pertama jadi foto utama — geser untuk mengubah urutan. Pilih warna supaya foto utama ikut berganti saat pembeli memilih warna itu (pilihan warna muncul setelah varian dibuat).")
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('images')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('urutan')
                            ->reorderable()
                            ->maxItems($foto['max_per_product'])
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
                                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => app(ProductImageStorage::class)->store($file)),
                                Select::make('warna')
                                    ->hiddenLabel()
                                    ->placeholder('Semua warna')
                                    ->options(function ($livewire): array {
                                        $produk = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                                        $warna = $produk instanceof Product ? $produk->daftarWarna() : [];

                                        return array_combine($warna, $warna);
                                    })
                                    ->native(false),
                            ]),
                    ]),
            ]);
    }
}
