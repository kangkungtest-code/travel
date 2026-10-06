# API v1 — Aplikasi Admin (Flutter)

Base URL: `https://kangkungdev.duckdns.org/api/v1` (dev). Semua endpoint admin ada di bawah `/admin`.

## Aturan umum

| Hal | Aturan |
| --- | --- |
| Header | `Accept: application/json` di semua request. Body boleh JSON (`Content-Type: application/json`) atau form. |
| Login | `POST /admin/masuk` → `token`. Kirim di setiap request: `Authorization: Bearer <token>`. Token berlaku 90 hari (`kadaluarsa_pada`). |
| Bahasa | Semua pesan (validasi & error) bahasa Indonesia, siap ditampilkan apa adanya. |
| Waktu | ISO 8601 zona toko (WITA), mis. `2026-10-02T14:09:57+08:00`. |
| Uang | Objek `{ "nilai": 220000, "teks": "Rp220.000" }`. Pakai `teks` untuk tampilan, `nilai` untuk hitung/grafik. |
| Paginasi | Daftar memakai format Laravel: `data[]`, `links{first,last,prev,next}`, `meta{current_page,last_page,per_page,total}`. Halaman berikut: `?page=2`. |
| Izin | Tiap endpoint butuh permission yang sama dengan panel web. Daftar izin akun ada di `admin.izin` (respons masuk / `GET /admin/saya`). Sembunyikan menu yang izinnya tidak dimiliki. |
| Batas | Masuk: 10 percobaan/menit. Endpoint lain: 120 request/menit per akun. |

### Kode status & error

| Status | Arti | Body |
| --- | --- | --- |
| 200 / 204 | Berhasil | data / kosong |
| 401 | Token salah, kadaluarsa, atau sudah keluar → arahkan ke layar Masuk | `{ "message": "Sesi berakhir atau belum masuk. Silakan masuk lagi." }` |
| 403 | Bukan akun admin, role dicabut, atau tidak punya izin | `{ "message": "Butuh izin order.lihat.", "izin": "order.lihat" }` |
| 404 | Data tidak ada | `{ "message": "Data tidak ditemukan." }` |
| 422 | Validasi gagal atau aturan bisnis menolak | `{ "message": "...", "errors": { "resi": ["resi wajib diisi."] } }` — `errors` hanya ada untuk validasi |
| 429 | Terlalu banyak request | — |

## Akun & perangkat

### `POST /admin/masuk`
Body: `email`, `password`, `nama_perangkat` (mis. "Pixel 8 Frendi").

```json
{
  "token": "1|0bzPIq...",
  "kadaluarsa_pada": "2026-12-31T14:09:57+08:00",
  "admin": {
    "id": "01a0f6b5-...", "nama": "Owner", "email": "admin@toko.test",
    "peran": ["Owner"],
    "izin": ["faq.kelola", "laporan.lihat", "order.lihat", "order.ubah_status", "retur.kelola", "stok.edit", "..."]
  }
}
```
Salah password → 422. Akun pembeli → 403.

### `GET /admin/saya`
`{ "data": { ...sama dengan admin di atas } }`. Panggil saat aplikasi dibuka untuk cek token masih berlaku & izin terbaru.

### `POST /admin/keluar`
204. Token dicabut dan HP ini berhenti menerima push.

### `POST /admin/perangkat`
Daftarkan token FCM HP ini. Panggil setelah masuk dan setiap `FirebaseMessaging.onTokenRefresh`.
Body: `fcm_token`, `platform` (`android` | `ios`, opsional).

### `DELETE /admin/perangkat`
Body: `fcm_token`. Matikan push untuk HP ini tanpa keluar.

## Notifikasi

### Push (FCM)
Payload `data` (semua string):

| Kunci | Contoh | Kegunaan |
| --- | --- | --- |
| `jenis` | `pesanan_dibayar` | `pesanan_baru`, `pesanan_dibayar`, `pembayaran_perlu_dicek`, `retur_diajukan`, `resi_retur`, `stok_menipis` |
| `notifikasi_id` | uuid | Tandai dibaca lewat API saat dibuka |
| `tujuan_jenis` | `pesanan` | `pesanan` / `retur` / `stok` → layar yang dibuka |
| `tujuan_id` | `KA-261002-ABCDE` | Nomor pesanan, id retur, atau id varian |

Android: notifikasi memakai `channel_id` **`pesanan`** — buat channel itu di aplikasi (importance high).

### `GET /admin/notifikasi`
Query: `belum_dibaca=1` (opsional). Respons paginasi + `belum_dibaca` (angka untuk badge).
```json
{ "data": [ { "id": "uuid", "jenis": "pesanan_dibayar", "judul": "KA-261002-ABCDE dibayar",
              "isi": "Rp149.000 · siap dikemas", "tujuan": { "jenis": "pesanan", "id": "KA-261002-ABCDE" },
              "dibaca": false, "dibuat_pada": "..." } ],
  "belum_dibaca": 3, "links": {}, "meta": {} }
```

### `POST /admin/notifikasi/{id}/dibaca` · `POST /admin/notifikasi/dibaca-semua`
→ `{ "belum_dibaca": 2 }`

## Dashboard — izin `laporan.lihat`

### `GET /admin/dashboard?periode=7_hari`
`periode`: `hari_ini` | `7_hari` (default) | `30_hari` | `bulan_ini`.
```json
{
  "periode": { "kode": "7_hari", "dari": "2026-09-26", "sampai": "2026-10-02" },
  "penjualan": { "nilai": 8138000, "teks": "Rp8.138.000" },
  "order_terbayar": 14,
  "rata_per_order": { "nilai": 581285.71, "teks": "Rp581.286" },
  "perlu_diproses": 4, "menunggu_bayar": 4, "retur_baru": 0, "stok_menipis": 44,
  "grafik_harian": [ { "tanggal": "2026-09-26", "penjualan_idr": 358000 } ],
  "produk_terlaris": [ { "nama": "Kaos Basic Katun Combed 30s", "qty": 12 } ]
}
```
`penjualan`, `order_terbayar`, grafik, dan terlaris mengikuti periode. `perlu_diproses`, `menunggu_bayar`, `retur_baru`, `stok_menipis` = kondisi saat ini.

## Pesanan — izin `order.lihat` (lihat) & `order.ubah_status` (aksi)

### `GET /admin/pesanan`
Query (semua opsional):

| Param | Nilai |
| --- | --- |
| `tab` | `perlu_diproses` (dibayar, terlama dulu) · `dikemas` · `dikirim` · `menunggu_bayar` · `selesai` · `batal` (kadaluarsa + dibatalkan) |
| `q` | Cari nomor pesanan, nama penerima, email/nama pembeli |
| `dari`, `sampai` | `YYYY-MM-DD` (tanggal pesanan dibuat, WITA) |
| `per_halaman` | 1–50, default 20 |

Item:
```json
{ "nomor": "KA-261001-RUM1K", "status": "dibayar", "label_status": "Dibayar",
  "pembeli": { "nama": "Mila", "email": "mila@example.org" },
  "jumlah_barang": 2,
  "total_idr": { "nilai": 278000, "teks": "Rp278.000" },
  "total_pembeli": { "mata_uang": "TWD", "nilai": 554, "teks": "NT$554" },
  "negara": "ID", "metode_bayar": "QRIS", "resi": null,
  "dibuat_pada": "...", "dibayar_pada": "...", "kadaluarsa_pada": "..." }
```
Status: `menunggu_pembayaran`, `dibayar`, `diproses`, `dikirim`, `selesai`, `kadaluarsa`, `dibatalkan`.

### `GET /admin/pesanan/jumlah`
Badge tab: `{ "perlu_diproses": 4, "dikemas": 2, "dikirim": 8, "menunggu_bayar": 4, "selesai": 0, "batal": 0 }`

### `GET /admin/pesanan/{nomor}`
Semua field daftar, ditambah:

| Field | Isi |
| --- | --- |
| `barang[]` | `nama`, `sku`, `opsi` (mis. `{"Warna":"Putih","Ukuran":"L"}`), `qty`, `harga_idr`, `subtotal_idr`, `foto` (thumbnail sesuai warna), `varian_id` |
| `alamat` | `nama_penerima`, `telepon`, `detail`, `kota`, `kode_pos`, `negara`, `nama_negara` |
| `kontak_pembeli` | `telepon`, `whatsapp_url` (siap dibuka, sudah berisi sapaan + nomor pesanan; null kalau nomor tidak valid) |
| `biaya` | `subtotal_idr`, `ongkir_idr`, `total_idr`, `berat_gram` |
| `pembayaran[]` | `metode`, `status` (`pending`/`berhasil`/`gagal`/`kadaluarsa`/`direfund`), `jumlah`, `mata_uang`, `catatan`, `dibuat_pada`, `dibayar_pada` |
| `riwayat[]` | `dari`, `ke`, `label`, `catatan`, `oleh`, `waktu` (urut lama → baru) |
| `retur` | `{ id, status, label_status }` atau null |
| `aksi[]` | Tombol yang boleh ditampilkan sekarang: `{ "kode": "kirim", "label": "Kirim", "wajib": ["resi"] }`. Kosong kalau tidak ada aksi / tidak punya izin. |

### Aksi (semua `POST`, respons = detail pesanan terbaru)

| Endpoint | Body | Muncul saat status |
| --- | --- | --- |
| `/admin/pesanan/{nomor}/konfirmasi-bayar` | `catatan` (wajib, mis. bukti transfer) | `menunggu_pembayaran` |
| `/admin/pesanan/{nomor}/batalkan` | `catatan` (wajib) | `menunggu_pembayaran` |
| `/admin/pesanan/{nomor}/proses` | — | `dibayar` |
| `/admin/pesanan/{nomor}/kirim` | `resi` (wajib, ≤ 100 karakter; pembeli dapat email) | `diproses` |
| `/admin/pesanan/{nomor}/selesai` | — | `dikirim` |

Status yang tidak urut → 422 `Status pesanan tidak bisa diubah dari dibayar ke selesai.`

## Stok — izin `stok.edit`

### `GET /admin/stok`
Query: `q` (SKU atau nama produk), `sku` (persis, untuk hasil scan barcode), `filter` (`menipis` = tersedia 1–5 · `habis` = ≤ 0), `per_halaman` (≤ 100, default 30). Urut dari stok tersedia paling sedikit.
```json
{ "varian_id": "uuid", "sku": "KEMEJA-LINEN-SAGE-M",
  "produk": { "id": "uuid", "nama": "Kemeja Linen Lengan Pendek", "aktif": true },
  "opsi": { "Warna": "Hijau Sage", "Ukuran": "M" }, "foto": "https://.../thumb.webp",
  "harga_idr": { "nilai": 249000, "teks": "Rp249.000" },
  "stok": { "fisik": 2, "dipesan": 0, "tersedia": 2, "status": "menipis" } }
```
`dipesan` = ditahan pesanan yang belum dibayar. `status`: `aman` · `menipis` · `habis`.

### `GET /admin/stok/{varian_id}` — satu varian (format sama).

### `POST /admin/stok/{varian_id}`
| Body | Arti |
| --- | --- |
| `jenis=restock`, `jumlah` ≥ 1 | Barang masuk: stok fisik + jumlah |
| `jenis=koreksi`, `jumlah` ≥ 0 | Hasil hitung ulang: stok fisik **menjadi** jumlah |

Respons: varian terbaru. Ditolak (422) kalau koreksi di bawah jumlah `dipesan`, atau angkanya sama dengan stok sekarang.

### `GET /admin/stok/{varian_id}/riwayat`
`data[]`: `perubahan` (+/-), `alasan` (`restock`/`koreksi`/`reserve`/`lepas`/`kurangi`), `label`, `pesanan` (nomor atau null), `waktu`. Plus `meta { halaman, halaman_terakhir, total }`, `?page=2`.

## Retur — izin `retur.kelola`

### `GET /admin/retur?status=diajukan`
`status`: `diajukan` · `disetujui` · `ditolak` · `selesai` (opsional).
```json
{ "id": "uuid", "status": "diajukan", "label_status": "Diajukan",
  "alasan": "Jahitan lepas", "foto_url": "https://...", "resi_kembali": null,
  "catatan_admin": null, "penyelesaian": null,
  "pesanan": { "nomor": "KA-...", "pembeli": "Mila", "total_idr": { "nilai": 120000, "teks": "Rp120.000" } },
  "dibuat_pada": "...", "diperbarui_pada": "...",
  "aksi": [ { "kode": "setujui", "label": "Setujui", "wajib": [] }, { "kode": "tolak", "label": "Tolak", "wajib": ["catatan"] } ] }
```

### `GET /admin/retur/{id}` — satu retur.

### Aksi (`POST`, respons = retur terbaru)
| Endpoint | Body | Saat status |
| --- | --- | --- |
| `/admin/retur/{id}/setujui` | `catatan` (opsional, terkirim ke pembeli) | `diajukan` |
| `/admin/retur/{id}/tolak` | `catatan` (wajib) | `diajukan` |
| `/admin/retur/{id}/selesaikan` | `penyelesaian` = `refund` \| `ganti_barang`, `catatan` (opsional) | `disetujui` |

`selesaikan` dengan `refund` juga mengembalikan `refund: { otomatis: true|false, pesan, refund_id }`. `otomatis: false` = refund harus ditransfer manual (mis. VA atau konfirmasi manual); tampilkan `pesan` ke admin.

## Catatan untuk aplikasi

- Simpan token di secure storage (mis. `flutter_secure_storage`), bukan SharedPreferences.
- Setelah aksi berhasil, pakai respons (detail terbaru) untuk memperbarui layar; tidak perlu GET ulang.
- Tombol aksi: tampilkan berdasarkan `aksi[]` dari server, bukan logika status sendiri, supaya selalu sama dengan panel web.
- Notifikasi push muncul hanya kalau secret `FIREBASE_CREDENTIALS` di server sudah diisi; kotak masuk (`/admin/notifikasi`) selalu jalan.
