<?php

/*
|--------------------------------------------------------------------------
| Profil toko
|--------------------------------------------------------------------------
| Semua yang membedakan satu toko dengan toko lain yang memakai kode yang
| sama ada di folder toko/ (dan public/toko/ untuk logo). Branch toko lain
| (mis. toko-fastandflux) hanya mengubah folder ini, jadi update dari
| claude-dev bisa di-merge tanpa bentrok.
|
| ATURAN: di branch claude-dev, file di folder toko/ jangan diubah lagi
| kecuali menambah kunci baru yang punya nilai bawaan di config/toko.php.
*/

return [
    // Nama toko di header, judul halaman, email, invoice Xendit, dll.
    'nama' => 'Kangkung Travel',

    // Awalan nomor pesanan, mis. KT-20261005-0001.
    'prefix_order' => 'KT',

    // Logo di header & panel admin (path di dalam public/). null = tulisan nama toko saja.
    'logo' => null,

    // Warna storefront (variabel CSS di public/css/toko.css). Kosong = warna bawaan.
    'tema' => [],

    // Warna utama panel admin (hex). null = amber bawaan Filament.
    'warna_admin' => null,

    // Teks besar di beranda per bahasa. null = teks bawaan.
    'beranda' => [
        'judul' => [
            'en' => 'Rent a car, with or without a driver',
            'id' => 'Sewa mobil, lepas kunci atau dengan sopir',
            'zh_TW' => '租車，自駕或附司機',
        ],
        'teks' => [
            'en' => 'Pick your dates, choose a vehicle, pay online. Clear prices, no surprises.',
            'id' => 'Pilih tanggal, pilih kendaraan, bayar online. Harga jelas, tanpa kejutan.',
            'zh_TW' => '選擇日期與車輛，線上付款。價格透明，沒有額外費用。',
        ],
    ],

    // Data katalog awal (relatif ke folder toko/), null = katalog contoh bawaan
    // database/data/katalog-demo.php dengan foto siluet.
    'katalog' => null,
];
