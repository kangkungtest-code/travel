<?php

namespace Database\Seeders;

use App\Models\Lokasi;
use App\Models\Tarif;
use App\Models\TarifMusim;
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

            foreach (self::TARIF[$slug] ?? [] as $mode => [$harian, $jam12, $perJam, $minimal, $bbm]) {
                Tarif::firstOrCreate(['tipe_kendaraan_id' => $tipe->id, 'mode' => $mode], [
                    'harga_harian' => $harian, 'harga_12jam' => $jam12, 'harga_per_jam' => $perJam,
                    'minimal_jam' => $minimal, 'termasuk_bbm' => $bbm, 'is_active' => true,
                ]);
            }

            foreach ($unit as [$plat, $kota, $tahun, $warna]) {
                UnitKendaraan::firstOrCreate(['plat_nomor' => $plat], [
                    'tipe_kendaraan_id' => $tipe->id, 'lokasi_id' => $lokasi[$kota]->id,
                    'tahun' => $tahun, 'warna' => $warna, 'status' => UnitKendaraan::SIAP,
                ]);
            }
        }

        $this->gantiFaqBarang();

        foreach ([
            ['Libur Natal & Tahun Baru', '2026-12-20', '2027-01-05', 30],
            ['Libur sekolah pertengahan tahun', '2027-06-26', '2027-07-11', 15],
        ] as [$nama, $mulai, $selesai, $persen]) {
            TarifMusim::firstOrCreate(['nama' => $nama], ['mulai' => $mulai, 'selesai' => $selesai, 'kenaikan_persen' => $persen, 'is_active' => true]);
        }
    }

    /**
     * Instalasi yang dulu terisi FAQ toko baju (sebelum jadi travel): ganti sekali dengan
     * FAQ sewa dari data travel. FAQ buatan admin tidak disentuh setelah itu.
     */
    private function gantiFaqBarang(): void
    {
        $file = base_path('toko/katalog-travel.php');
        if (! is_file($file) || ! \App\Models\Faq::query()->where('pertanyaan_terjemahan', 'like', '%choose my size%')->exists()) {
            return;
        }

        \App\Models\Faq::query()->delete();
        foreach ((require $file)['faq'] as $i => $faq) {
            \App\Models\Faq::create(['pertanyaan_terjemahan' => $faq['q'], 'jawaban_terjemahan' => $faq['a'], 'urutan' => $i + 1, 'is_active' => true]);
        }
    }

    /** slug => [mode => [24 jam, 12 jam, per jam, minimal jam, termasuk BBM]] */
    private const TARIF = [
        'honda-brio' => [
            Tarif::LEPAS_KUNCI => [300000, 200000, 35000, 12, false],
            Tarif::SOPIR => [750000, 550000, 60000, 12, true],
        ],
        'toyota-avanza' => [
            Tarif::LEPAS_KUNCI => [400000, 275000, 45000, 12, false],
            Tarif::SOPIR => [900000, 650000, 75000, 12, true],
        ],
        'toyota-innova-zenix' => [
            Tarif::LEPAS_KUNCI => [750000, 500000, 80000, 12, false],
            Tarif::SOPIR => [1300000, 950000, 100000, 12, true],
        ],
        'toyota-hiace-premio' => [
            Tarif::SOPIR => [1600000, 1200000, 150000, 12, true],
        ],
        'honda-scoopy' => [
            Tarif::LEPAS_KUNCI => [90000, 60000, null, 24, false],
        ],
    ];
}
