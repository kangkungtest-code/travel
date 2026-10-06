<?php

/*
 * Katalog contoh toko baju "Kangkung Apparel" (fiktif) untuk server dev/demo.
 * Harga dalam IDR. Warna: [nama id, nama en, nama zh_TW, hex untuk foto placeholder].
 * Bentuk foto placeholder: tee / shirt / pants / jacket / dress / skirt / hat / bag / socks.
 */

$warna = [
    'hitam' => ['Hitam', 'Black', '黑色', '#2b2b2b'],
    'putih' => ['Putih', 'White', '白色', '#f2f0eb'],
    'navy' => ['Navy', 'Navy', '藏青色', '#1f2f4d'],
    'abu' => ['Abu-abu', 'Grey', '灰色', '#9a9a9a'],
    'krem' => ['Krem', 'Cream', '米色', '#e8dcc4'],
    'hijau' => ['Hijau Zaitun', 'Olive', '橄欖綠', '#6b7044'],
    'merah' => ['Merah Bata', 'Brick Red', '磚紅色', '#a5452f'],
    'biru' => ['Biru Denim', 'Denim Blue', '丹寧藍', '#4a6a8f'],
    'cokelat' => ['Cokelat', 'Brown', '咖啡色', '#7a5536'],
    'sage' => ['Hijau Sage', 'Sage', '鼠尾草綠', '#a7b59a'],
];

return [
    'warna' => $warna,
    // Kategori contoh: nama Indonesia (dipakai di daftar produk di bawah) => terjemahan.
    'kategori' => [
        'Kaos' => ['id' => 'Kaos', 'en' => 'T-shirts', 'zh_TW' => 'T恤'],
        'Kemeja' => ['id' => 'Kemeja', 'en' => 'Shirts', 'zh_TW' => '襯衫'],
        'Celana' => ['id' => 'Celana', 'en' => 'Pants', 'zh_TW' => '褲子'],
        'Jaket' => ['id' => 'Jaket', 'en' => 'Jackets', 'zh_TW' => '外套'],
        'Dress' => ['id' => 'Dress', 'en' => 'Dresses', 'zh_TW' => '洋裝與裙子'],
        'Aksesoris' => ['id' => 'Aksesoris', 'en' => 'Accessories', 'zh_TW' => '配件'],
    ],
    'produk' => [
        [
            'kode' => 'KAOS-BASIC',
            'kategori' => 'Kaos',
            'bentuk' => 'tee',
            'nama' => ['id' => 'Kaos Basic Katun Combed 30s', 'en' => 'Basic Combed Cotton Tee', 'zh_TW' => '精梳棉基本款T恤'],
            'deskripsi' => [
                'id' => 'Kaos harian dari katun combed 30s yang adem dan menyerap keringat. Potongan regular fit, jahitan rantai di bahu supaya tidak mudah melar.',
                'en' => 'Everyday tee in breathable 30s combed cotton. Regular fit with taped shoulder seams that keep their shape wash after wash.',
                'zh_TW' => '採用透氣吸汗的30支精梳棉，日常百搭。標準版型，肩線車縫加固，久洗不易變形。',
            ],
            'harga' => 129000, 'berat' => 180,
            'ukuran' => ['S', 'M', 'L', 'XL'],
            'warna' => ['hitam', 'putih', 'navy', 'abu'],
        ],
        [
            'kode' => 'KAOS-OVERSIZE',
            'kategori' => 'Kaos',
            'bentuk' => 'tee',
            'nama' => ['id' => 'Kaos Oversize Heavyweight', 'en' => 'Heavyweight Oversized Tee', 'zh_TW' => '厚磅寬鬆T恤'],
            'deskripsi' => [
                'id' => 'Bahan katun 24s yang tebal dan jatuh, potongan oversize dengan bahu turun. Cocok untuk gaya santai.',
                'en' => 'Thick, structured 24s cotton with a dropped-shoulder oversized cut for an easy relaxed look.',
                'zh_TW' => '24支厚磅純棉，落肩寬鬆剪裁，輕鬆打造休閒風格。',
            ],
            'harga' => 159000, 'berat' => 240,
            'ukuran' => ['M', 'L', 'XL'],
            'warna' => ['krem', 'hijau', 'hitam'],
        ],
        [
            'kode' => 'POLO-PIQUE',
            'kategori' => 'Kaos',
            'bentuk' => 'tee',
            'nama' => ['id' => 'Polo Shirt Pique', 'en' => 'Pique Polo Shirt', 'zh_TW' => '珠地網眼Polo衫'],
            'deskripsi' => [
                'id' => 'Polo berbahan pique berpori yang tetap rapi untuk kerja maupun akhir pekan. Kerah rib yang tidak mudah melengkung.',
                'en' => 'Breathable pique polo that looks sharp at work or on the weekend. Ribbed collar that stays flat.',
                'zh_TW' => '透氣珠地網眼布料，上班週末都合宜。羅紋領口不易捲翹。',
            ],
            'harga' => 189000, 'berat' => 220,
            'ukuran' => ['S', 'M', 'L', 'XL'],
            'warna' => ['navy', 'putih', 'merah'],
        ],
        [
            'kode' => 'KEMEJA-OXFORD',
            'kategori' => 'Kemeja',
            'bentuk' => 'shirt',
            'nama' => ['id' => 'Kemeja Oxford Lengan Panjang', 'en' => 'Long Sleeve Oxford Shirt', 'zh_TW' => '牛津布長袖襯衫'],
            'deskripsi' => [
                'id' => 'Kemeja oxford klasik dengan kerah button-down. Bisa dipakai rapi dengan celana bahan atau santai digulung lengannya.',
                'en' => 'Classic oxford shirt with a button-down collar. Dress it up with trousers or roll the sleeves for a casual look.',
                'zh_TW' => '經典牛津布扣領襯衫，可搭配西褲正式穿著，也可捲起袖子輕鬆穿搭。',
            ],
            'harga' => 279000, 'berat' => 300,
            'ukuran' => ['S', 'M', 'L', 'XL'],
            'warna' => ['putih', 'biru'],
        ],
        [
            'kode' => 'KEMEJA-LINEN',
            'kategori' => 'Kemeja',
            'bentuk' => 'shirt',
            'nama' => ['id' => 'Kemeja Linen Lengan Pendek', 'en' => 'Short Sleeve Linen Shirt', 'zh_TW' => '亞麻短袖襯衫'],
            'deskripsi' => [
                'id' => 'Campuran linen dan katun yang ringan, pas untuk cuaca panas. Kerah cuban yang santai.',
                'en' => 'Lightweight linen-cotton blend made for hot days, finished with a relaxed camp collar.',
                'zh_TW' => '輕盈亞麻棉混紡，炎熱天氣的最佳選擇，搭配休閒古巴領設計。',
            ],
            'harga' => 249000, 'berat' => 200,
            'ukuran' => ['M', 'L', 'XL'],
            'warna' => ['krem', 'sage', 'putih'],
        ],
        [
            'kode' => 'CELANA-CHINO',
            'kategori' => 'Celana',
            'bentuk' => 'pants',
            'nama' => ['id' => 'Celana Chino Slim Fit', 'en' => 'Slim Fit Chino Pants', 'zh_TW' => '修身卡其褲'],
            'deskripsi' => [
                'id' => 'Chino katun twill dengan sedikit stretch supaya nyaman dipakai seharian. Potongan slim yang tidak ketat.',
                'en' => 'Cotton twill chinos with a touch of stretch for all-day comfort. Slim but never tight.',
                'zh_TW' => '棉質斜紋布加入少量彈性纖維，整天穿著都舒適。修身但不緊繃。',
            ],
            'harga' => 299000, 'berat' => 450,
            'ukuran' => ['28', '30', '32', '34', '36'],
            'warna' => ['krem', 'navy', 'hitam'],
        ],
        [
            'kode' => 'CELANA-JEANS',
            'kategori' => 'Celana',
            'bentuk' => 'pants',
            'nama' => ['id' => 'Celana Jeans Straight', 'en' => 'Straight Leg Jeans', 'zh_TW' => '直筒牛仔褲'],
            'deskripsi' => [
                'id' => 'Denim 13 oz dengan potongan straight yang timeless. Makin dipakai makin nyaman.',
                'en' => 'Timeless straight-leg cut in 13 oz denim that only gets better with wear.',
                'zh_TW' => '13盎司丹寧布，經典直筒剪裁，越穿越有味道。',
            ],
            'harga' => 349000, 'berat' => 650,
            'ukuran' => ['28', '30', '32', '34'],
            'warna' => ['biru', 'hitam'],
        ],
        [
            'kode' => 'CELANA-JOGGER',
            'kategori' => 'Celana',
            'bentuk' => 'pants',
            'nama' => ['id' => 'Celana Jogger Fleece', 'en' => 'Fleece Jogger Pants', 'zh_TW' => '刷毛束口褲'],
            'deskripsi' => [
                'id' => 'Jogger berbahan fleece lembut dengan karet pinggang dan tali serut. Untuk santai di rumah maupun jalan-jalan.',
                'en' => 'Soft fleece joggers with an elastic drawstring waist, for lounging or running errands.',
                'zh_TW' => '柔軟刷毛布料，鬆緊抽繩褲頭，居家外出都適合。',
            ],
            'harga' => 219000, 'berat' => 400,
            'ukuran' => ['S', 'M', 'L', 'XL'],
            'warna' => ['abu', 'hitam'],
        ],
        [
            'kode' => 'HOODIE-FLEECE',
            'kategori' => 'Jaket',
            'bentuk' => 'jacket',
            'nama' => ['id' => 'Hoodie Fleece Basic', 'en' => 'Basic Fleece Hoodie', 'zh_TW' => '基本款刷毛連帽上衣'],
            'deskripsi' => [
                'id' => 'Hoodie hangat dengan bagian dalam fleece, kantong kanguru, dan tudung bertali. Pas untuk ruangan ber-AC atau perjalanan.',
                'en' => 'Warm fleece-lined hoodie with a kangaroo pocket and drawstring hood. Ideal for chilly offices and travel.',
                'zh_TW' => '內刷毛保暖連帽上衣，附袋鼠口袋與抽繩帽。冷氣房或旅行都很實用。',
            ],
            'harga' => 329000, 'berat' => 550,
            'ukuran' => ['M', 'L', 'XL'],
            'warna' => ['abu', 'hitam', 'hijau'],
        ],
        [
            'kode' => 'JAKET-DENIM',
            'kategori' => 'Jaket',
            'bentuk' => 'jacket',
            'nama' => ['id' => 'Jaket Denim Klasik', 'en' => 'Classic Denim Jacket', 'zh_TW' => '經典丹寧外套'],
            'deskripsi' => [
                'id' => 'Jaket denim dengan kancing logam dan dua saku dada. Satu jaket untuk segala musim.',
                'en' => 'Denim jacket with metal buttons and twin chest pockets — one jacket for every season.',
                'zh_TW' => '金屬鈕扣搭配雙胸袋的丹寧外套，四季皆宜。',
            ],
            'harga' => 449000, 'berat' => 800,
            'ukuran' => ['M', 'L', 'XL'],
            'warna' => ['biru'],
        ],
        [
            'kode' => 'DRESS-MIDI',
            'kategori' => 'Dress',
            'bentuk' => 'dress',
            'nama' => ['id' => 'Midi Dress Rayon', 'en' => 'Rayon Midi Dress', 'zh_TW' => '嫘縈中長洋裝'],
            'deskripsi' => [
                'id' => 'Dress midi berbahan rayon yang jatuh dan adem, dengan tali pinggang yang bisa diatur.',
                'en' => 'Flowy, breathable rayon midi dress with an adjustable waist tie.',
                'zh_TW' => '垂墜透氣的嫘縈布料，附可調式腰帶的中長洋裝。',
            ],
            'harga' => 289000, 'berat' => 300,
            'ukuran' => ['S', 'M', 'L'],
            'warna' => ['sage', 'hitam', 'merah'],
        ],
        [
            'kode' => 'ROK-PLISKET',
            'kategori' => 'Dress',
            'bentuk' => 'skirt',
            'nama' => ['id' => 'Rok Plisket Midi', 'en' => 'Pleated Midi Skirt', 'zh_TW' => '百褶中長裙'],
            'deskripsi' => [
                'id' => 'Rok plisket dengan pinggang karet yang nyaman, mudah dipadukan dengan kaos maupun kemeja.',
                'en' => 'Pleated skirt with a comfy elastic waist that pairs easily with tees or shirts.',
                'zh_TW' => '鬆緊腰頭百褶裙，舒適好穿，搭T恤或襯衫都好看。',
            ],
            'harga' => 199000, 'berat' => 250,
            'ukuran' => ['All Size'],
            'warna' => ['krem', 'navy', 'cokelat'],
        ],
        [
            'kode' => 'TOPI-BASEBALL',
            'kategori' => 'Aksesoris',
            'bentuk' => 'hat',
            'nama' => ['id' => 'Topi Baseball Katun', 'en' => 'Cotton Baseball Cap', 'zh_TW' => '棉質棒球帽'],
            'deskripsi' => [
                'id' => 'Topi baseball katun twill dengan strap belakang yang bisa diatur.',
                'en' => 'Cotton twill baseball cap with an adjustable back strap.',
                'zh_TW' => '棉質斜紋布棒球帽，後方可調式扣帶。',
            ],
            'harga' => 99000, 'berat' => 100,
            'ukuran' => ['All Size'],
            'warna' => ['hitam', 'krem', 'navy'],
        ],
        [
            'kode' => 'TOTE-KANVAS',
            'kategori' => 'Aksesoris',
            'bentuk' => 'bag',
            'nama' => ['id' => 'Tote Bag Kanvas', 'en' => 'Canvas Tote Bag', 'zh_TW' => '帆布托特包'],
            'deskripsi' => [
                'id' => 'Tote bag kanvas tebal 12 oz dengan saku dalam. Muat laptop 14 inci.',
                'en' => 'Heavy 12 oz canvas tote with an inner pocket. Fits a 14-inch laptop.',
                'zh_TW' => '12盎司厚帆布托特包，附內袋，可放14吋筆電。',
            ],
            'harga' => 89000, 'berat' => 250,
            'ukuran' => ['All Size'],
            'warna' => ['krem', 'hitam'],
        ],
        [
            'kode' => 'KAOSKAKI-3PACK',
            'kategori' => 'Aksesoris',
            'bentuk' => 'socks',
            'nama' => ['id' => 'Kaos Kaki Katun (Isi 3)', 'en' => 'Cotton Crew Socks (3-Pack)', 'zh_TW' => '棉質中筒襪（3雙入）'],
            'deskripsi' => [
                'id' => 'Tiga pasang kaos kaki katun panjang sedang dengan bantalan di telapak.',
                'en' => 'Three pairs of mid-length cotton socks with a cushioned sole.',
                'zh_TW' => '三雙入中筒棉襪，腳底加厚緩衝。',
            ],
            'harga' => 49000, 'berat' => 120,
            'ukuran' => ['All Size'],
            'warna' => ['putih', 'hitam'],
        ],
    ],

    // Kurs contoh (manual, perkiraan Oktober 2026 — bukan kurs resmi). 1 IDR = rate.
    'kurs' => [
        ['IDR', 'USD', 0.0000606, 2.00],
        ['IDR', 'TWD', 0.00195, 2.00],
    ],

    'faq' => [
        [
            'q' => ['id' => 'Berapa lama pengiriman?', 'en' => 'How long does shipping take?', 'zh_TW' => '運送需要多久？'],
            'a' => [
                'id' => 'Dalam negeri 2–5 hari kerja. Ke Taiwan dan negara lain 5–10 hari kerja setelah pesanan dikirim.',
                'en' => 'Domestic orders take 2–5 business days. Taiwan and other countries take 5–10 business days after dispatch.',
                'zh_TW' => '印尼境內2–5個工作天；寄往台灣及其他國家，出貨後約5–10個工作天。',
            ],
        ],
        [
            'q' => ['id' => 'Bagaimana cara memilih ukuran?', 'en' => 'How do I choose my size?', 'zh_TW' => '如何選擇尺寸？'],
            'a' => [
                'id' => 'Lihat tabel ukuran di setiap halaman produk. Kalau ragu di antara dua ukuran, pilih yang lebih besar.',
                'en' => 'Check the size chart on each product page. If you are between sizes, we recommend sizing up.',
                'zh_TW' => '請參考各商品頁面的尺寸表。若介於兩個尺寸之間，建議選擇較大的尺寸。',
            ],
        ],
        [
            'q' => ['id' => 'Apakah bisa retur?', 'en' => 'Can I return an item?', 'zh_TW' => '可以退貨嗎？'],
            'a' => [
                'id' => 'Retur hanya untuk barang cacat atau salah kirim, diajukan maksimal 7 hari setelah pesanan selesai. Ongkir retur ditanggung toko.',
                'en' => 'Returns are accepted for defective or incorrect items within 7 days after delivery. We cover the return shipping.',
                'zh_TW' => '商品若有瑕疵或寄錯，可於訂單完成後7天內申請退貨，退貨運費由本店負擔。',
            ],
        ],
        [
            'q' => ['id' => 'Metode pembayaran apa saja?', 'en' => 'Which payment methods do you accept?', 'zh_TW' => '有哪些付款方式？'],
            'a' => [
                'id' => 'PayPal (USD/TWD), QRIS, dan Virtual Account bank Indonesia.',
                'en' => 'PayPal (USD/TWD), QRIS, and Indonesian bank virtual accounts.',
                'zh_TW' => 'PayPal（美元／新台幣）、QRIS 以及印尼銀行虛擬帳號。',
            ],
        ],
    ],
];
