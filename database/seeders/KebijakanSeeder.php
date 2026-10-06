<?php

namespace Database\Seeders;

use App\Models\HalamanKebijakan;
use Illuminate\Database\Seeder;

/**
 * Membuat halaman kebijakan yang belum ada dari draf di database/data/kebijakan.php.
 * Halaman yang sudah ada tidak disentuh (isinya milik admin), jadi aman tiap deploy.
 */
class KebijakanSeeder extends Seeder
{
    public function run(): void
    {
        $ganti = [
            '{toko}' => config('toko.nama'),
            '{jam}' => (string) config('toko.order.batas_bayar_jam'),
            '{hari}' => (string) config('toko.retur.batas_hari'),
        ];

        foreach (require database_path('data/kebijakan.php') as $h) {
            if (HalamanKebijakan::query()->where('slug', $h['slug'])->exists()) {
                continue;
            }

            HalamanKebijakan::create([
                'slug' => $h['slug'],
                'urutan' => $h['urutan'],
                'judul_terjemahan' => $h['judul'],
                'isi_terjemahan' => array_map(fn (string $isi) => strtr($isi, $ganti), $h['isi']),
                'is_active' => true,
            ]);
        }
    }
}
