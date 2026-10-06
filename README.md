# travel

Aplikasi pemesanan **sewa kendaraan** (mobil, motor, minibus/bus; lepas kunci atau dengan sopir),
dengan modul **paket wisata** menyusul. Satu kode untuk banyak client: tiap client satu instalasi,
fitur yang aktif mengikuti paket yang diatur Super Admin.

Titik awal kode: salinan repo `kangkungtest-code/e-commerce` branch `claude-dev`
(Laravel 13, Filament 5, Sanctum, Spatie Permission & Translatable). Bagian khas e-commerce
(keranjang, ongkir berat, stok gudang, retur barang) diganti bertahap dengan armada, tarif,
ketersediaan unit, dan booking.

## Jalan di laptop

```bash
composer install
cp .env.example .env        # sesuaikan DB_* dan ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

- Storefront: http://localhost:8000
- Panel admin: http://localhost:8000/admin

## Branch & deploy

- `claude-dev` → https://kangkungtravel.duckdns.org (folder `/var/www/travel`, database `travel_dev`, `APP_ENV=dev`)

Repo ini **tidak punya secret**. Tiap push menjalankan `.github/workflows/deploy.yml`: job `test`
(MySQL + `php artisan test`), lalu server sendiri menarik commit yang lulus tes
(`deploy/tarik.sh` di repo e-commerce) dan menjalankan `deploy/jalankan.sh`. Provision server
(Nginx, database, HTTPS) dan akun Super Admin juga diatur dari repo e-commerce.

Identitas situs (nama, prefix nomor booking, warna, teks beranda) ada di `toko/profil.php`.
