<?php

/*
|--------------------------------------------------------------------------
| Pilihan tetap untuk armada (sewa kendaraan)
|--------------------------------------------------------------------------
| Kunci disimpan di database, label 3 bahasa dipakai di panel (id) dan
| storefront (bahasa pembeli). Menambah pilihan cukup di sini.
*/

return [
    /* Mode sewa. Label dipakai di panel (id) & storefront. */
    'mode' => [
        'lepas_kunci' => ['id' => 'Lepas kunci', 'en' => 'Self-drive', 'zh_TW' => '自駕'],
        'sopir' => ['id' => 'Dengan sopir', 'en' => 'With driver', 'zh_TW' => '附司機'],
    ],

    'jenis' => [
        'mobil' => ['id' => 'Mobil', 'en' => 'Car', 'zh_TW' => '汽車'],
        'motor' => ['id' => 'Motor', 'en' => 'Scooter / motorbike', 'zh_TW' => '機車'],
        'minibus' => ['id' => 'Minibus', 'en' => 'Minibus', 'zh_TW' => '小巴'],
        'bus' => ['id' => 'Bus', 'en' => 'Bus', 'zh_TW' => '巴士'],
    ],

    'transmisi' => [
        'otomatis' => ['id' => 'Otomatis', 'en' => 'Automatic', 'zh_TW' => '自排'],
        'manual' => ['id' => 'Manual', 'en' => 'Manual', 'zh_TW' => '手排'],
    ],

    'bbm' => [
        'bensin' => ['id' => 'Bensin', 'en' => 'Petrol', 'zh_TW' => '汽油'],
        'solar' => ['id' => 'Solar', 'en' => 'Diesel', 'zh_TW' => '柴油'],
        'hybrid' => ['id' => 'Hybrid', 'en' => 'Hybrid', 'zh_TW' => '油電混合'],
        'listrik' => ['id' => 'Listrik', 'en' => 'Electric', 'zh_TW' => '電動'],
    ],

    'fasilitas' => [
        'ac' => ['id' => 'AC', 'en' => 'Air conditioning', 'zh_TW' => '冷氣'],
        'audio' => ['id' => 'Audio / Bluetooth', 'en' => 'Audio / Bluetooth', 'zh_TW' => '音響 / 藍牙'],
        'usb' => ['id' => 'Pengisi daya USB', 'en' => 'USB charger', 'zh_TW' => 'USB 充電'],
        'wifi' => ['id' => 'Wi-Fi', 'en' => 'Wi-Fi', 'zh_TW' => 'Wi-Fi'],
        'kursi_anak' => ['id' => 'Kursi anak', 'en' => 'Child seat', 'zh_TW' => '兒童座椅'],
        'helm' => ['id' => 'Helm (2)', 'en' => 'Helmets (2)', 'zh_TW' => '安全帽（2 頂）'],
        'jas_hujan' => ['id' => 'Jas hujan', 'en' => 'Raincoat', 'zh_TW' => '雨衣'],
        'reclining' => ['id' => 'Kursi reclining', 'en' => 'Reclining seats', 'zh_TW' => '可調式座椅'],
        'tv' => ['id' => 'TV / karaoke', 'en' => 'TV / karaoke', 'zh_TW' => '電視 / 卡拉OK'],
    ],

    /* Status unit. Hanya unit "siap" yang bisa dialokasikan ke booking. */
    'status_unit' => [
        'siap' => 'Siap disewa',
        'perawatan' => 'Perawatan / servis',
        'nonaktif' => 'Nonaktif',
    ],

    'booking' => [
        /* Jeda antar sewa di unit yang sama (bersih-bersih, cek kendaraan), jam. */
        'jeda_jam' => (int) env('TRAVEL_JEDA_JAM', 2),
        /* Pesanan paling cepat sekian jam dari sekarang. */
        'minimal_jam_dari_sekarang' => (int) env('TRAVEL_MINIMAL_JAM_DARI_SEKARANG', 3),
        /* Lama sewa maksimal (hari). */
        'maks_hari' => 30,
        /* Pesan paling jauh sekian hari ke depan. */
        'maks_hari_ke_depan' => 365,
        /* Dokumen wajib untuk lepas kunci. Disimpan di disk privat. */
        'disk_dokumen' => 'local',
        'maks_dokumen_kb' => 5120,
    ],

    'foto' => [
        'directory' => 'kendaraan',
        'max_per_tipe' => 8,
    ],
];
