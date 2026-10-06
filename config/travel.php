<?php

/*
|--------------------------------------------------------------------------
| Pilihan tetap untuk armada (sewa kendaraan)
|--------------------------------------------------------------------------
| Kunci disimpan di database, label 3 bahasa dipakai di panel (id) dan
| storefront (bahasa pembeli). Menambah pilihan cukup di sini.
*/

return [
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

    'foto' => [
        'directory' => 'kendaraan',
        'max_per_tipe' => 8,
    ],
];
