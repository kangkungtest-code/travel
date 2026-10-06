<?php

/*
 * Draf awal halaman kebijakan (Markdown). Dipakai KebijakanSeeder hanya untuk
 * membuat halaman yang belum ada; setelah itu isi dikelola dari panel admin.
 *
 * Penanda {toko}, {jam}, {hari} diganti nilai dari config/toko.php saat seeding.
 * Bagian "[ISI: ...]" sengaja dibiarkan untuk dilengkapi pemilik toko.
 * Draf ini contoh umum, bukan nasihat hukum.
 */

return [
    [
        'slug' => 'terms',
        'urutan' => 1,
        'judul' => [
            'en' => 'Terms & Conditions',
            'id' => 'Syarat & Ketentuan',
            'zh_TW' => '條款與細則',
        ],
        'isi' => [
            'en' => <<<'MD'
These terms apply to every purchase at {toko}. By creating an account or placing an order you agree to them.

## About us
{toko} is an online clothing store run by [ISI: business / owner name], [ISI: business address], Indonesia.

## Your account
- Please give accurate details (name, email, delivery address). We use them to process and ship your orders.
- Keep your password private. You are responsible for orders placed from your account.
- You can also sign in with Google or LINE where available.

## Products and prices
- We try to show colours and sizes as accurately as possible, but colours can look slightly different on each screen.
- Prices are set in Indonesian rupiah (IDR). Prices in other currencies are converted at the store's daily rate and are for reference.
- The price, currency and exchange rate are locked when you place your order. Later price changes do not affect it.

## Orders and payment
- Your items are held for {jam} hours after you place an order. If the order isn't paid within that time it is cancelled automatically and the items go back on sale.
- You can cancel an order yourself while it is still awaiting payment.
- Payment is processed by our payment partner. The methods available (for example QRIS or bank virtual account) are shown on your order page. Some methods charge in a different currency; the exact amount is shown before you pay.
- If we cannot fulfil a paid order (for example a stock error), we will contact you and refund the full amount.
- Cancelling an order after payment: [ISI: allowed or not, and how — e.g. contact us before it ships].

## Shipping, returns and refunds
See our Shipping Policy and Returns & Refunds Policy.

## Limitation of liability
[ISI: e.g. our liability for any order is limited to the amount you paid for that order.]

## Changes to these terms
We may update these terms from time to time. The version on this page at the time of your order applies to that order.

## Contact
Questions? Reach us via the WhatsApp or LINE links at the bottom of this page, or email [ISI: store email].
MD,
            'id' => <<<'MD'
Syarat ini berlaku untuk setiap pembelian di {toko}. Dengan membuat akun atau membuat pesanan, kamu menyetujui syarat ini.

## Tentang kami
{toko} adalah toko pakaian online yang dikelola oleh [ISI: nama usaha / pemilik], [ISI: alamat usaha], Indonesia.

## Akun kamu
- Isi data dengan benar (nama, email, alamat pengiriman). Data ini kami pakai untuk memproses dan mengirim pesanan.
- Jaga kerahasiaan password. Pesanan yang dibuat dari akunmu menjadi tanggung jawabmu.
- Kamu juga bisa masuk dengan Google atau LINE jika tersedia.

## Produk dan harga
- Kami berusaha menampilkan warna dan ukuran seakurat mungkin, tetapi warna bisa sedikit berbeda di tiap layar.
- Harga ditetapkan dalam rupiah (IDR). Harga dalam mata uang lain dikonversi dengan kurs harian toko dan bersifat perkiraan.
- Harga, mata uang, dan kurs dikunci saat pesanan dibuat. Perubahan harga setelahnya tidak memengaruhi pesananmu.

## Pesanan dan pembayaran
- Barang di pesananmu kami simpan selama {jam} jam. Jika belum dibayar dalam waktu itu, pesanan otomatis dibatalkan dan barang dijual kembali.
- Kamu bisa membatalkan sendiri pesanan yang masih menunggu pembayaran.
- Pembayaran diproses oleh mitra pembayaran kami. Metode yang tersedia (misalnya QRIS atau virtual account bank) tampil di halaman pesanan. Beberapa metode menagih dalam mata uang lain; jumlah pastinya ditampilkan sebelum kamu membayar.
- Jika pesanan yang sudah dibayar tidak bisa kami penuhi (misalnya kesalahan stok), kami akan menghubungimu dan mengembalikan dana secara penuh.
- Pembatalan setelah dibayar: [ISI: boleh atau tidak, dan caranya — mis. hubungi kami sebelum barang dikirim].

## Pengiriman, retur, dan refund
Lihat Kebijakan Pengiriman serta Kebijakan Retur & Refund.

## Batas tanggung jawab
[ISI: mis. tanggung jawab kami atas suatu pesanan terbatas pada jumlah yang kamu bayarkan untuk pesanan tersebut.]

## Perubahan syarat
Syarat ini dapat kami perbarui sewaktu-waktu. Versi yang tampil di halaman ini saat kamu membuat pesanan berlaku untuk pesanan tersebut.

## Kontak
Ada pertanyaan? Hubungi kami lewat tautan WhatsApp atau LINE di bagian bawah halaman, atau email [ISI: email toko].
MD,
            'zh_TW' => <<<'MD'
本條款適用於在 {toko} 的每一筆購買。建立帳號或下單即表示您同意本條款。

## 關於我們
{toko} 是由 [ISI: 商號／負責人名稱] 經營的線上服飾店，地址：[ISI: 營業地址]，印尼。

## 您的帳號
- 請填寫正確的資料（姓名、電子郵件、收件地址），我們會用於處理及寄送訂單。
- 請妥善保管密碼。以您帳號建立的訂單由您負責。
- 若有提供，您也可以使用 Google 或 LINE 登入。

## 商品與價格
- 我們盡力準確呈現顏色與尺寸，但不同螢幕顯示的顏色可能略有差異。
- 價格以印尼盾（IDR）訂定，其他幣別依本店每日匯率換算，僅供參考。
- 價格、幣別與匯率在下單時即鎖定，之後的價格變動不影響您的訂單。

## 訂單與付款
- 下單後商品將為您保留 {jam} 小時。若在期限內未付款，訂單會自動取消，商品重新開放販售。
- 訂單在等待付款期間，您可以自行取消。
- 付款由我們的金流合作夥伴處理，可用的付款方式（例如 QRIS 或銀行虛擬帳號）會顯示在訂單頁面。部分方式以其他幣別收款，確切金額會在付款前顯示。
- 若已付款的訂單因故無法出貨（例如庫存錯誤），我們會與您聯繫並全額退款。
- 付款後取消訂單：[ISI: 是否允許及方式，例如在出貨前聯繫我們]。

## 運送、退貨與退款
請參閱「運送政策」及「退貨與退款政策」。

## 責任限制
[ISI: 例如：本店對任一訂單之責任以您該筆訂單實際支付之金額為限。]

## 條款變更
本條款可能不定期更新。下單時本頁所示之版本適用於該筆訂單。

## 聯絡我們
有任何問題，請透過頁面底部的 WhatsApp 或 LINE 連結，或寄信至 [ISI: 店家電子郵件]。
MD,
        ],
    ],

    [
        'slug' => 'returns',
        'urutan' => 2,
        'judul' => [
            'en' => 'Returns & Refunds',
            'id' => 'Retur & Refund',
            'zh_TW' => '退貨與退款',
        ],
        'isi' => [
            'en' => <<<'MD'
## What can be returned
Returns are accepted only when:
- the item arrived **damaged or defective**, or
- you received the **wrong item** (wrong product, colour or size from what you ordered).

We don't accept returns for change of mind or a size that doesn't fit. [ISI: confirm, or describe any exception.]

Items must be unused, unwashed and with tags attached, except for the defect itself.

## Time limit
Request a return within **{hari} days** after your order is marked *Completed*.

## How to request a return
1. Open **Account → Orders** and choose the order.
2. Click *Item arrived damaged or wrong?*, describe the problem and upload a photo.
3. We review your request and reply on the same page (and by email).
4. If approved, the return address is shown on your order page. Send the item back and enter the return tracking number there.

Return shipping cost: [ISI: e.g. paid by the store for damaged / wrong items].

## Refunds and exchanges
Once we receive and check the item we either send a replacement or refund you, as agreed in the request.
- **QRIS** payments are refunded through our payment partner to the original account.
- **Bank virtual account** payments are refunded by bank transfer; we'll ask for your account details.
- Refunds are usually completed within [ISI: number] working days after we receive the item.

## Questions
Contact us via WhatsApp or LINE (links at the bottom of the page).
MD,
            'id' => <<<'MD'
## Barang yang bisa diretur
Retur hanya diterima jika:
- barang datang dalam keadaan **rusak atau cacat**, atau
- kamu menerima **barang yang salah** (produk, warna, atau ukuran berbeda dari pesanan).

Kami tidak menerima retur karena berubah pikiran atau ukuran tidak cocok. [ISI: pastikan, atau tulis pengecualiannya.]

Barang harus belum dipakai, belum dicuci, dan label masih terpasang, kecuali kerusakan itu sendiri.

## Batas waktu
Ajukan retur paling lambat **{hari} hari** setelah pesanan berstatus *Selesai*.

## Cara mengajukan retur
1. Buka **Akun → Pesanan** lalu pilih pesanannya.
2. Klik *Barang rusak atau salah kirim?*, jelaskan masalahnya, dan unggah foto.
3. Kami meninjau pengajuanmu dan membalas di halaman yang sama (juga lewat email).
4. Jika disetujui, alamat retur akan tampil di halaman pesanan. Kirim balik barangnya dan isi nomor resi pengiriman balik di sana.

Ongkos kirim retur: [ISI: mis. ditanggung toko untuk barang rusak / salah kirim].

## Refund dan penukaran
Setelah barang kami terima dan periksa, kami akan mengirim barang pengganti atau mengembalikan dana, sesuai kesepakatan di pengajuan.
- Pembayaran **QRIS** dikembalikan lewat mitra pembayaran ke rekening/akun asal.
- Pembayaran **virtual account bank** dikembalikan lewat transfer bank; kami akan menanyakan data rekeningmu.
- Refund biasanya selesai dalam [ISI: jumlah] hari kerja setelah barang kami terima.

## Pertanyaan
Hubungi kami lewat WhatsApp atau LINE (tautan di bagian bawah halaman).
MD,
            'zh_TW' => <<<'MD'
## 可退貨的情況
僅在下列情況接受退貨：
- 商品送達時**損壞或有瑕疵**，或
- 您收到**錯誤的商品**（商品、顏色或尺寸與訂單不符）。

因個人喜好改變或尺寸不合而退貨恕不受理。[ISI: 請確認，或說明例外情況。]

除瑕疵本身外，商品須未使用、未洗滌且吊牌完整。

## 期限
請於訂單顯示「已完成」後 **{hari} 天內**申請退貨。

## 申請方式
1. 前往 **帳戶 → 訂單** 並選擇該筆訂單。
2. 點選「商品有瑕疵或寄錯了嗎？」，描述問題並上傳照片。
3. 我們審核後會在同一頁面（及電子郵件）回覆。
4. 核准後，退貨地址會顯示在訂單頁面。請寄回商品並在該頁填寫退貨追蹤號碼。

退貨運費：[ISI: 例如：商品損壞／寄錯時由本店負擔]。

## 退款與換貨
收到並檢查商品後，我們會依申請內容寄出替換商品或為您退款。
- **QRIS** 付款將透過金流合作夥伴退回原帳戶。
- **銀行虛擬帳號** 付款以銀行轉帳退款，我們會向您索取帳戶資料。
- 退款通常在收到商品後 [ISI: 天數] 個工作天內完成。

## 有疑問？
請透過 WhatsApp 或 LINE 與我們聯繫（連結位於頁面底部）。
MD,
        ],
    ],

    [
        'slug' => 'shipping',
        'urutan' => 3,
        'judul' => [
            'en' => 'Shipping Policy',
            'id' => 'Kebijakan Pengiriman',
            'zh_TW' => '運送政策',
        ],
        'isi' => [
            'en' => <<<'MD'
## Where we ship
We ship to the countries you can choose at checkout.

## Shipping cost
Shipping is calculated from the total weight of your order and the destination country, and is shown at checkout before you place the order.

## Processing time
Orders are packed after payment is confirmed, usually within [ISI: number] working days.

## Couriers and tracking
- We ship with [ISI: courier names, e.g. JNE / J&T for Indonesia, and the international courier].
- When your order ships, the tracking number appears on your order page and we email it to you.
- Estimated delivery: [ISI: e.g. Indonesia 2–5 working days, Taiwan 7–14 working days].

## International orders
Import duties and taxes in the destination country, if any, are [ISI: paid by the buyer / included].

## Delivery address
Please check your address before placing the order. If a package is returned because of an incorrect address, [ISI: how re-shipping is handled].

## Questions
Contact us via WhatsApp or LINE (links at the bottom of the page).
MD,
            'id' => <<<'MD'
## Tujuan pengiriman
Kami mengirim ke negara yang bisa dipilih saat checkout.

## Ongkos kirim
Ongkir dihitung dari total berat pesanan dan negara tujuan, dan ditampilkan saat checkout sebelum pesanan dibuat.

## Waktu proses
Pesanan dikemas setelah pembayaran terkonfirmasi, biasanya dalam [ISI: jumlah] hari kerja.

## Kurir dan pelacakan
- Kami mengirim dengan [ISI: nama kurir, mis. JNE / J&T untuk Indonesia, dan kurir internasional].
- Saat pesanan dikirim, nomor resi tampil di halaman pesanan dan kami kirimkan lewat email.
- Perkiraan waktu sampai: [ISI: mis. Indonesia 2–5 hari kerja, Taiwan 7–14 hari kerja].

## Pesanan internasional
Bea masuk dan pajak impor di negara tujuan, jika ada, [ISI: ditanggung pembeli / sudah termasuk].

## Alamat pengiriman
Periksa kembali alamatmu sebelum membuat pesanan. Jika paket kembali karena alamat salah, [ISI: cara penanganan pengiriman ulang].

## Pertanyaan
Hubungi kami lewat WhatsApp atau LINE (tautan di bagian bawah halaman).
MD,
            'zh_TW' => <<<'MD'
## 配送地區
我們配送至結帳時可選擇的國家。

## 運費
運費依訂單總重量及目的地國家計算，並於結帳時、下單前顯示。

## 處理時間
付款確認後開始包裝，通常需 [ISI: 天數] 個工作天。

## 物流與追蹤
- 我們使用 [ISI: 物流公司名稱，例如印尼境內 JNE / J&T 及國際物流] 寄送。
- 出貨後，追蹤號碼會顯示在訂單頁面，並以電子郵件通知您。
- 預計送達時間：[ISI: 例如印尼 2–5 個工作天、台灣 7–14 個工作天]。

## 國際訂單
目的地國家的進口關稅及稅金（如有）[ISI: 由買家負擔／已包含]。

## 收件地址
下單前請確認地址。若包裹因地址錯誤被退回，[ISI: 重新寄送的處理方式]。

## 有疑問？
請透過 WhatsApp 或 LINE 與我們聯繫（連結位於頁面底部）。
MD,
        ],
    ],

    [
        'slug' => 'privacy',
        'urutan' => 4,
        'judul' => [
            'en' => 'Privacy Policy',
            'id' => 'Kebijakan Privasi',
            'zh_TW' => '隱私權政策',
        ],
        'isi' => [
            'en' => <<<'MD'
This policy explains what personal data {toko} collects and how we use it.

## Data we collect
- **Account:** name, email address and password (stored encrypted, we can't read it). If you sign in with Google or LINE, we receive your name and email from them.
- **Orders:** recipient name, phone number, delivery address, items ordered, amounts paid and order history.
- **Returns:** the reason and photo you upload with a return request.
- **Preferences:** your chosen language and currency.

We do **not** receive or store your card number, bank login or e-wallet PIN. Payments are handled by our payment partner.

## How we use it
- To create your account and let you sign in.
- To process, ship and support your orders, returns and refunds.
- To send emails about your orders (confirmation, shipping, returns).
- [ISI: marketing emails or not — if yes, explain how to unsubscribe.]

## Who we share it with
Only what is needed to complete your order:
- our payment partner (Xendit) to process payments and refunds;
- couriers, to deliver your package (name, phone, address);
- our email provider, to send order emails;
- Google or LINE, only if you choose to sign in with them.

We do not sell your personal data.

## Cookies
We use essential cookies to keep you signed in, remember your cart and remember your language and currency. [ISI: analytics / tracking cookies, if added later.]

## How long we keep it
[ISI: e.g. account data while your account is active; order records for N years for accounting and tax purposes.]

## Your choices
- You can view and update your details and addresses in **Account**.
- You can delete your account yourself in **Account → Delete account**. Your name, email, password, saved addresses and sign-in links are removed; records of past orders are kept for accounting, without your account details.
- To ask for a copy of your data, contact us at [ISI: store email].

## Security
The site uses HTTPS, and passwords are stored hashed. No system is completely secure, but we work to protect your data.

## Changes
We may update this policy. The date at the top of this page shows the latest version.

## Contact
[ISI: business / owner name], [ISI: business address] — [ISI: store email].
MD,
            'id' => <<<'MD'
Kebijakan ini menjelaskan data pribadi apa saja yang dikumpulkan {toko} dan bagaimana kami menggunakannya.

## Data yang kami kumpulkan
- **Akun:** nama, alamat email, dan password (disimpan terenkripsi, kami tidak bisa membacanya). Jika kamu masuk dengan Google atau LINE, kami menerima nama dan email dari layanan tersebut.
- **Pesanan:** nama penerima, nomor telepon, alamat pengiriman, barang yang dipesan, jumlah yang dibayar, dan riwayat pesanan.
- **Retur:** alasan dan foto yang kamu unggah saat mengajukan retur.
- **Preferensi:** bahasa dan mata uang yang kamu pilih.

Kami **tidak** menerima atau menyimpan nomor kartu, data login bank, atau PIN e-wallet. Pembayaran ditangani oleh mitra pembayaran kami.

## Penggunaan data
- Untuk membuat akun dan memungkinkan kamu masuk.
- Untuk memproses, mengirim, dan melayani pesanan, retur, serta refund.
- Untuk mengirim email terkait pesanan (konfirmasi, pengiriman, retur).
- [ISI: kirim email promosi atau tidak — jika ya, jelaskan cara berhenti berlangganan.]

## Pihak yang menerima data
Hanya sebatas yang diperlukan untuk menyelesaikan pesanan:
- mitra pembayaran kami (Xendit) untuk memproses pembayaran dan refund;
- kurir, untuk mengantar paket (nama, telepon, alamat);
- penyedia layanan email, untuk mengirim email pesanan;
- Google atau LINE, hanya jika kamu memilih masuk dengan layanan tersebut.

Kami tidak menjual data pribadimu.

## Cookie
Kami memakai cookie yang diperlukan agar kamu tetap masuk, keranjang tersimpan, serta bahasa dan mata uang pilihanmu diingat. [ISI: cookie analitik / pelacakan, jika nanti ditambahkan.]

## Lama penyimpanan
[ISI: mis. data akun selama akun aktif; catatan pesanan selama N tahun untuk keperluan pembukuan dan pajak.]

## Hak kamu
- Kamu bisa melihat dan mengubah data serta alamatmu di menu **Akun**.
- Kamu bisa menghapus akun sendiri di **Akun → Hapus akun**. Nama, email, password, alamat tersimpan, dan login Google/LINE dihapus; catatan pesanan lama tetap disimpan untuk pembukuan, tanpa data akunmu.
- Untuk meminta salinan data, hubungi kami di [ISI: email toko].

## Keamanan
Situs ini memakai HTTPS dan password disimpan dalam bentuk hash. Tidak ada sistem yang benar-benar aman, tetapi kami berupaya melindungi datamu.

## Perubahan
Kebijakan ini dapat kami perbarui. Tanggal di bagian atas halaman menunjukkan versi terbaru.

## Kontak
[ISI: nama usaha / pemilik], [ISI: alamat usaha] — [ISI: email toko].
MD,
            'zh_TW' => <<<'MD'
本政策說明 {toko} 蒐集哪些個人資料以及如何使用。

## 我們蒐集的資料
- **帳號：** 姓名、電子郵件及密碼（加密儲存，我們無法讀取）。若您使用 Google 或 LINE 登入，我們會從該服務取得您的姓名與電子郵件。
- **訂單：** 收件人姓名、電話、收件地址、訂購商品、付款金額及訂單紀錄。
- **退貨：** 您申請退貨時填寫的原因及上傳的照片。
- **偏好設定：** 您選擇的語言與幣別。

我們**不會**接收或儲存您的卡號、網路銀行登入資料或電子錢包密碼。付款由我們的金流合作夥伴處理。

## 資料用途
- 建立帳號並讓您登入。
- 處理、寄送及服務您的訂單、退貨與退款。
- 寄送訂單相關電子郵件（確認、出貨、退貨）。
- [ISI: 是否寄送行銷郵件——若有，請說明如何取消訂閱。]

## 資料分享對象
僅限完成訂單所需：
- 金流合作夥伴（Xendit），用於處理付款及退款；
- 物流公司，用於配送包裹（姓名、電話、地址）；
- 電子郵件服務商，用於寄送訂單通知；
- Google 或 LINE，僅在您選擇以其登入時。

我們不會出售您的個人資料。

## Cookie
我們使用必要的 Cookie 以維持登入狀態、保存購物車，並記住您選擇的語言與幣別。[ISI: 若日後加入分析／追蹤 Cookie 請說明。]

## 保存期間
[ISI: 例如：帳號資料於帳號有效期間保存；訂單紀錄因會計及稅務需求保存 N 年。]

## 您的權利
- 您可在「帳戶」中查看及更新個人資料與地址。
- 您可在「帳戶 → 刪除帳戶」自行刪除帳戶。姓名、電子郵件、密碼、已存地址及社群登入連結將被刪除；過去的訂單紀錄因會計需求保留，但不含帳戶資料。
- 如需取得資料副本，請寄信至 [ISI: 店家電子郵件]。

## 資料安全
本網站使用 HTTPS，密碼以雜湊方式儲存。沒有任何系統能保證絕對安全，但我們會盡力保護您的資料。

## 政策變更
本政策可能更新，頁面上方的日期為最新版本日期。

## 聯絡我們
[ISI: 商號／負責人名稱]，[ISI: 營業地址] — [ISI: 店家電子郵件]。
MD,
        ],
    ],
];
