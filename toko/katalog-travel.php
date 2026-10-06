<?php

/*
| Data awal untuk instalasi travel (dibaca DemoCatalogSeeder lewat toko/profil.php 'katalog').
| Tanpa produk barang; armada contoh dibuat ArmadaDemoSeeder.
*/

return [
    'warna' => [],
    'kategori' => [],
    'produk' => [],

    'kurs' => [
        ['IDR', 'USD', 0.0000606, 2.00],
        ['IDR', 'TWD', 0.00195, 2.00],
    ],

    'faq' => [
        [
            'q' => ['id' => 'Dokumen apa yang perlu disiapkan?', 'en' => 'What documents do I need?', 'zh_TW' => '需要準備哪些證件？'],
            'a' => [
                'id' => 'Untuk lepas kunci: KTP (WNI) atau paspor (WNA) dan SIM yang sesuai jenis kendaraan. Penyewa asing memakai SIM internasional. Dokumen diunggah saat memesan dan ditunjukkan aslinya saat pengambilan. Sewa dengan sopir tidak perlu SIM.',
                'en' => 'For self-drive: an ID card (Indonesian citizens) or passport (foreign visitors) and a driving licence for the vehicle type. Foreign renters need an international driving permit. Upload them when booking and bring the originals at pick-up. Rentals with a driver don\'t need a licence.',
                'zh_TW' => '自駕：身分證（印尼公民）或護照（外籍旅客），以及符合車種的駕照；外籍承租人需國際駕照。預訂時上傳，取車時出示正本。附司機方案不需駕照。',
            ],
        ],
        [
            'q' => ['id' => 'Bagaimana harga sewa dihitung?', 'en' => 'How is the rental price calculated?', 'zh_TW' => '租金如何計算？'],
            'a' => [
                'id' => 'Per 24 jam sejak waktu ambil. Sisa waktu kurang dari sehari dihitung dengan tarif 12 jam atau per jam, mana yang lebih murah. Di musim ramai (mis. Natal & Tahun Baru) ada kenaikan harga yang ditampilkan di rincian sebelum bayar.',
                'en' => 'Per 24 hours from pick-up time. Any remaining time under a day is charged at the 12-hour or hourly rate, whichever is cheaper. Peak seasons (e.g. Christmas & New Year) carry a surcharge, shown in the price breakdown before you pay.',
                'zh_TW' => '自取車時間起每24小時計費，不足一天的部分以12小時或每小時費率中較便宜者計算。旺季（如聖誕與新年）會加價，付款前會在明細中顯示。',
            ],
        ],
        [
            'q' => ['id' => 'Apakah harga sudah termasuk BBM?', 'en' => 'Is fuel included?', 'zh_TW' => '價格含油資嗎？'],
            'a' => [
                'id' => 'Lepas kunci: belum. Kendaraan diserahkan dengan BBM tertentu dan dikembalikan dengan level yang sama. Dengan sopir: tergantung kendaraan, keterangannya tertulis di rincian harga. Tol dan parkir dibayar di jalan.',
                'en' => 'Self-drive: no. The vehicle is handed over with a set fuel level and should be returned the same. With a driver: depends on the vehicle, as stated in the price breakdown. Tolls and parking are paid on the road.',
                'zh_TW' => '自駕不含油資，還車時請保持取車時的油量。附司機方案依車輛而定，價格明細中會註明。過路費與停車費另計。',
            ],
        ],
        [
            'q' => ['id' => 'Bagaimana kalau saya terlambat mengembalikan?', 'en' => 'What if I return the vehicle late?', 'zh_TW' => '延遲還車怎麼辦？'],
            'a' => [
                'id' => 'Ada toleransi 30 menit. Lewat dari itu dikenakan biaya per jam sesuai tarif kendaraan, maksimal seharga sewa harian per hari. Hubungi kami sebelum jadwal kembali kalau ingin memperpanjang.',
                'en' => 'There\'s a 30-minute grace period. After that, an hourly charge applies based on the vehicle\'s rate, capped at the daily price per day. Contact us before your return time if you\'d like to extend.',
                'zh_TW' => '有30分鐘寬限，超過後依車輛費率按小時計費，每天最多收取一天租金。如需延長，請在還車時間前聯絡我們。',
            ],
        ],
        [
            'q' => ['id' => 'Bisakah booking dibatalkan?', 'en' => 'Can I cancel my booking?', 'zh_TW' => '可以取消預訂嗎？'],
            'a' => [
                'id' => 'Booking yang belum dibayar bisa dibatalkan sendiri dari halaman Akun → Pesanan. Untuk booking yang sudah dibayar, hubungi kami; pengembalian dana mengikuti Syarat & Ketentuan.',
                'en' => 'Unpaid bookings can be cancelled from Account → Orders. For paid bookings, contact us; refunds follow our Terms & Conditions.',
                'zh_TW' => '未付款的預訂可在「帳戶 → 訂單」自行取消。已付款的預訂請與我們聯絡，退款依服務條款辦理。',
            ],
        ],
    ],
];
