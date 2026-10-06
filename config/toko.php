<?php

// Identitas toko (nama, logo, warna, katalog awal) ada di toko/profil.php.
$profil = (file_exists($f = base_path('toko/profil.php')) ? require $f : []) + [
    'nama' => 'Kangkung Apparel',
    'prefix_order' => 'KA',
    'logo' => null,
    'tema' => [],
    'warna_admin' => null,
    'beranda' => null,
    'katalog' => null,
];

return [
    /* Nama toko di storefront. */
    'nama' => env('TOKO_NAMA', $profil['nama']),

    /* Logo (path di public/), null = tulisan nama toko. */
    'logo' => $profil['logo'],

    /* Variabel CSS storefront yang diganti, mis. ['--kangkung' => '#c8102e']. */
    'tema' => $profil['tema'],

    'warna_admin' => $profil['warna_admin'],

    /* ['judul' => [locale => teks], 'teks' => [locale => teks]] atau null. */
    'beranda' => $profil['beranda'],

    /* File data katalog awal di folder toko/, null = database/data/katalog-demo.php. */
    'katalog' => $profil['katalog'],

    /*
    | Bahasa yang didukung untuk konten (produk, FAQ) dan UI.
    | Urutan = urutan tab di panel admin. Locale pertama = default storefront.
    */
    'locales' => [
        'en' => 'English',
        'id' => 'Bahasa Indonesia',
        'zh_TW' => '繁體中文',
    ],

    /* Locale yang wajib diisi untuk nama produk. */
    'required_locales' => ['en', 'id'],

    'currencies' => ['USD', 'IDR', 'TWD'],
    'base_currency' => 'IDR',

    'product_images' => [
        'disk' => 'public',
        'directory' => 'products',
        'max_per_product' => 8,
        'max_upload_kb' => 10240,
        'max_width' => 1600,
        'thumb_width' => 400,
        'quality' => 80,
    ],

    /* Nama negara (kode ISO alpha-2). Negara yang bisa dipilih = negara di zona ongkir aktif. */
    'negara' => [
        'ID' => 'Indonesia',
        'TW' => 'Taiwan',
        'SG' => 'Singapore',
        'MY' => 'Malaysia',
        'HK' => 'Hong Kong',
        'JP' => 'Japan',
        'US' => 'United States',
        'AU' => 'Australia',
    ],

    /* Zona waktu tampilan untuk pembeli (database tetap UTC). WITA = UTC+8, sama dengan Taiwan. */
    'zona_waktu' => env('TOKO_ZONA_WAKTU', 'Asia/Makassar'),

    'order' => [
        'prefix_nomor' => env('TOKO_PREFIX_ORDER', $profil['prefix_order']),
        // Batas bayar sejak order dibuat; lewat dari ini order kadaluarsa dan stok dilepas.
        'batas_bayar_jam' => (int) env('TOKO_BATAS_BAYAR_JAM', 24),
        'maks_qty_per_item' => 20,
    ],

    // Wajib verifikasi email sebelum checkout. null = otomatis: wajib hanya kalau SMTP sudah diisi.
    'wajib_verifikasi_email' => env('TOKO_WAJIB_VERIFIKASI') === null ? null : filter_var(env('TOKO_WAJIB_VERIFIKASI'), FILTER_VALIDATE_BOOLEAN),

    'retur' => [
        // Retur hanya untuk barang cacat / salah kirim, diajukan maksimal N hari setelah order selesai.
        'batas_hari' => (int) env('TOKO_BATAS_RETUR_HARI', 7),
        // Alamat tujuan pengiriman balik barang retur (ditampilkan ke pembeli setelah retur disetujui).
        'alamat' => env('TOKO_ALAMAT_RETUR', ''),
    ],
];
