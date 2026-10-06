<?php

namespace Database\Seeders;

use App\Models\Lokasi;
use App\Models\TipeKendaraan;
use App\Models\UnitKendaraan;
use Illuminate\Database\Seeder;

/**
 * Armada contoh untuk laptop & server dev/demo (bukan production).
 * Idempotent: lokasi dicari per kota, kendaraan per slug, unit per plat —
 * data buatan admin tidak tersentuh dan aman dijalankan tiap deploy.
 */
class ArmadaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $lokasi = [];
        foreach ([
            ['Denpasar', ['id' => 'Pool Denpasar', 'en' => 'Denpasar pool', 'zh_TW' => '登巴薩據點'], 'Jl. Contoh No. 1, Denpasar', '07.00–21.00'],
            ['Ubud', ['id' => 'Pool Ubud', 'en' => 'Ubud pool', 'zh_TW' => '烏布據點'], 'Jl. Contoh No. 2, Ubud', '08.00–20.00'],
        ] as $i => [$kota, $nama, $alamat, $jam]) {
            $lokasi[$kota] = Lokasi::firstOrCreate(['kota' => $kota], [
                'nama_terjemahan' => $nama, 'alamat' => $alamat, 'jam_operasional' => $jam,
                'urutan' => $i + 1, 'is_active' => true,
            ]);
        }

        $data = [
            // slug, nama en/id/zh, jenis, kursi, transmisi, bbm, bagasi, fasilitas, unit [plat, kota, tahun, warna]
            ['honda-brio', ['en' => 'Honda Brio', 'id' => 'Honda Brio', 'zh_TW' => 'Honda Brio'], 'mobil', 4, 'otomatis', 'bensin', 1, ['ac', 'audio', 'usb'],
                [['DK 1101 TR', 'Denpasar', 2023, 'Putih'], ['DK 1102 TR', 'Ubud', 2022, 'Merah']]],
            ['toyota-avanza', ['en' => 'Toyota Avanza', 'id' => 'Toyota Avanza', 'zh_TW' => 'Toyota Avanza'], 'mobil', 7, 'otomatis', 'bensin', 2, ['ac', 'audio', 'usb', 'kursi_anak'],
                [['DK 1201 TR', 'Denpasar', 2023, 'Silver'], ['DK 1202 TR', 'Denpasar', 2022, 'Hitam'], ['DK 1203 TR', 'Ubud', 2024, 'Putih']]],
            ['toyota-innova-zenix', ['en' => 'Toyota Innova Zenix', 'id' => 'Toyota Innova Zenix', 'zh_TW' => 'Toyota Innova Zenix'], 'mobil', 7, 'otomatis', 'hybrid', 3, ['ac', 'audio', 'usb', 'reclining'],
                [['DK 1301 TR', 'Denpasar', 2024, 'Hitam']]],
            ['toyota-hiace-premio', ['en' => 'Toyota HiAce Premio', 'id' => 'Toyota HiAce Premio', 'zh_TW' => 'Toyota HiAce Premio'], 'minibus', 12, 'manual', 'solar', 8, ['ac', 'audio', 'usb', 'reclining', 'wifi'],
                [['DK 7401 TR', 'Denpasar', 2023, 'Putih']]],
            ['honda-scoopy', ['en' => 'Honda Scoopy', 'id' => 'Honda Scoopy', 'zh_TW' => 'Honda Scoopy'], 'motor', 2, 'otomatis', 'bensin', null, ['helm', 'jas_hujan'],
                [['DK 3501 TR', 'Ubud', 2024, 'Krem'], ['DK 3502 TR', 'Ubud', 2023, 'Hitam']]],
        ];

        foreach ($data as $urutan => [$slug, $nama, $jenis, $kursi, $transmisi, $bbm, $bagasi, $fasilitas, $unit]) {
            $tipe = TipeKendaraan::firstOrCreate(['slug' => $slug], [
                'nama_terjemahan' => $nama,
                'deskripsi_terjemahan' => [
                    'en' => 'Clean, well-maintained and ready for your trip. Contoh data — ganti di panel.',
                    'id' => 'Bersih, terawat, dan siap untuk perjalanan Anda. Contoh data — ganti di panel.',
                    'zh_TW' => '乾淨、保養良好，隨時出發。（範例資料）',
                ],
                'jenis' => $jenis, 'kursi' => $kursi, 'transmisi' => $transmisi, 'bbm' => $bbm,
                'bagasi' => $bagasi, 'fasilitas' => $fasilitas, 'urutan' => $urutan + 1, 'is_active' => true,
            ]);

            foreach ($unit as [$plat, $kota, $tahun, $warna]) {
                UnitKendaraan::firstOrCreate(['plat_nomor' => $plat], [
                    'tipe_kendaraan_id' => $tipe->id, 'lokasi_id' => $lokasi[$kota]->id,
                    'tahun' => $tahun, 'warna' => $warna, 'status' => UnitKendaraan::SIAP,
                ]);
            }
        }
    }
}
