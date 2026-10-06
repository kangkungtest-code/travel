<?php

/*
|--------------------------------------------------------------------------
| Fitur yang bisa dibuka-tutup & isi paket
|--------------------------------------------------------------------------
| Diatur Super Admin di panel (Pengaturan → Fitur & paket). Fitur inti (katalog,
| kategori, keranjang, checkout, pesanan, akun, QRIS/VA, kebijakan, FAQ, SEO, dll.)
| tidak ada di sini karena selalu menyala.
|
| Instalasi yang belum pernah memilih paket = semua fitur menyala (perilaku lama).
| Lihat claude/rencana-fitur-paket.md di Project.
*/

return [
    // kunci => [nama, keterangan, siap?]. siap=false: fitur belum dibangun (tidak bisa dinyalakan).
    'fitur' => [
        // Travel (sewa kendaraan). Sewa mobil selalu ada; lepas kunci / dengan sopir minimal satu menyala.
        'mode_lepas_kunci' => ['Sewa lepas kunci', 'Penyewa menyetir sendiri (wajib unggah KTP/paspor & SIM). Kalau kedua mode mati, lepas kunci tetap dipakai.', true],
        'mode_sopir' => ['Sewa dengan sopir', 'Kendaraan disewa bersama sopir dari toko.', true],
        'sewa_motor' => ['Sewa motor', 'Jenis kendaraan motor/skuter di katalog & pencarian.', true],
        'sewa_bus' => ['Sewa minibus & bus', 'Jenis kendaraan minibus dan bus untuk rombongan.', true],
        'paket_wisata' => ['Paket wisata & open trip', 'Paket tour dengan itinerary, jadwal & kuota.', false],
        'bayar_dp' => ['Bayar DP', 'Penyewa bisa membayar uang muka, pelunasan menyusul.', false],
        'antar_jemput' => ['Antar-jemput kendaraan', 'Kendaraan diantar ke hotel/bandara dengan biaya per zona.', false],
        'login_google' => ['Login Google', 'Tombol "Lanjut dengan Google" di halaman masuk/daftar.', true],
        'login_line' => ['Login LINE', 'Tombol "Lanjut dengan LINE" di halaman masuk/daftar.', true],
        'chatbot' => ['Chatbot', 'Gelembung tanya-jawab otomatis di semua halaman toko.', true],
        'retur' => ['Retur barang', 'Pembeli bisa mengajukan retur; admin memproses di menu Retur.', true],
        'email_pemilik' => ['Email notifikasi pemilik', 'Email rincian pesanan/retur ke pemilik toko (butuh SMTP).', true],
        'paypal' => ['PayPal', 'Pembayaran PayPal (USD/TWD) untuk pembeli luar negeri.', true],
        'multi_bahasa' => ['Multi-bahasa', 'Toko dalam bahasa Inggris & 繁體中文 selain Bahasa Indonesia.', true],
        'multi_mata_uang' => ['Multi-mata uang', 'Harga tampil dalam USD/TWD (kurs dikelola admin) selain Rupiah.', true],
        'kirim_luar_negeri' => ['Kirim ke luar negeri', 'Zona ongkir untuk negara selain Indonesia.', true],
        'aplikasi_mobile' => ['Aplikasi admin mobile', 'API aplikasi admin & notifikasi push ke HP.', true],
        'laporan_lengkap' => ['Laporan lengkap', 'Ekspor laporan penjualan (Excel/CSV).', false],
        'staf' => ['Akun staf & peran', 'Owner bisa menambah karyawan dengan akses terbatas.', true],
    ],

    // nomor => daftar fitur. Nama paket diatur di panel (bawaan "Paket 1/2/3").
    'paket' => [
        1 => [], // tanpa saklar mode = lepas kunci saja
        2 => ['mode_lepas_kunci', 'mode_sopir', 'sewa_motor', 'login_google', 'login_line', 'chatbot', 'retur', 'email_pemilik', 'laporan_lengkap', 'staf'],
        3 => [
            'mode_lepas_kunci', 'mode_sopir', 'sewa_motor', 'sewa_bus', 'paket_wisata', 'bayar_dp', 'antar_jemput',
            'login_google', 'login_line', 'chatbot', 'retur', 'email_pemilik', 'laporan_lengkap', 'staf',
            'paypal', 'multi_bahasa', 'multi_mata_uang', 'kirim_luar_negeri', 'aplikasi_mobile',
        ],
    ],
];
