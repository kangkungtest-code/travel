<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /** Semua permission staff, format `modul.aksi`. */
    public const PERMISSIONS = [
        'armada.kelola',
        'produk.kelola',
        'stok.edit',
        'order.lihat',
        'order.ubah_status',
        'retur.kelola',
        'faq.kelola',
        'kebijakan.kelola',
        'pengaturan.kelola',
        'pembayaran.kelola',
        'kurs.kelola',
        'ongkir.kelola',
        'laporan.lihat',
    ];

    public const GUARD = 'admin';

    /** Peran pemilik platform (Frendi). Hanya peran ini yang boleh mengatur fitur & paket. */
    public const SUPER_ADMIN = 'Super Admin';

    /** Izin yang hanya dimiliki Super Admin, tidak pernah diberikan ke peran client. */
    public const IZIN_SUPER = ['fitur.kelola', 'akun.owner'];

    /** Izin khusus Owner (tidak bisa diberikan ke peran staf). */
    public const IZIN_OWNER = ['staf.kelola'];

    public const OWNER = 'Owner';

    /** Label izin untuk halaman Peran (yang bisa diberikan ke staf = PERMISSIONS). */
    public const LABEL = [
        'armada.kelola' => 'Kelola armada (lokasi, kendaraan, unit)',
        'produk.kelola' => 'Kelola produk & kategori',
        'stok.edit' => 'Ubah stok',
        'order.lihat' => 'Lihat pesanan',
        'order.ubah_status' => 'Proses pesanan (ubah status, resi, batal)',
        'retur.kelola' => 'Proses retur',
        'faq.kelola' => 'Kelola FAQ & chatbot',
        'kebijakan.kelola' => 'Ubah halaman kebijakan',
        'pengaturan.kelola' => 'Kontak, media sosial & notifikasi',
        'pembayaran.kelola' => 'Akun & metode pembayaran',
        'kurs.kelola' => 'Kurs mata uang',
        'ongkir.kelola' => 'Ongkir',
        'laporan.lihat' => 'Dashboard & laporan penjualan',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...self::PERMISSIONS, ...self::IZIN_OWNER, ...self::IZIN_SUPER] as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        // Owner (client) = semua izin toko.
        Role::findOrCreate(self::OWNER, self::GUARD)->syncPermissions([...self::PERMISSIONS, ...self::IZIN_OWNER]);
        // Super Admin sengaja TIDAK punya izin toko (produk, pesanan, laporan, dll.): hanya mengatur platform.
        Role::findOrCreate(self::SUPER_ADMIN, self::GUARD)->syncPermissions(self::IZIN_SUPER);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
