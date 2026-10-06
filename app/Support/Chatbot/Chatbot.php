<?php

namespace App\Support\Chatbot;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Chatbot tanpa AI: mencocokkan kata kunci di pesan pembeli dengan topik
 * dari file teks balasan (lihat resources/chatbot/balasan.txt untuk formatnya).
 *
 * File yang dipakai: storage/app/chatbot/balasan.txt (hasil edit admin di panel),
 * atau file bawaan di resources/chatbot/balasan.txt kalau admin belum pernah mengedit.
 */
class Chatbot
{
    public const WAJIB = ['sapaan', 'tidak-ketemu'];

    /** @var array<string, array>|null */
    private ?array $topik = null;

    public static function pathEdit(): string
    {
        return storage_path('app/chatbot/balasan.txt');
    }

    public static function pathBawaan(): string
    {
        return resource_path('chatbot/balasan.txt');
    }

    public static function isiFile(): string
    {
        return File::exists(self::pathEdit()) ? File::get(self::pathEdit()) : File::get(self::pathBawaan());
    }

    /** Simpan hasil edit admin. Melempar FormatSalah kalau isinya tidak valid. */
    public static function simpanFile(string $isi): void
    {
        self::parse($isi);
        File::ensureDirectoryExists(dirname(self::pathEdit()));
        File::put(self::pathEdit(), str_replace("\r\n", "\n", $isi));
    }

    /** @return array<string, array{nama: string, kata: array<int, string>, tanya: array<string, string>, jawab: array<string, string>, admin: bool}> */
    public static function parse(string $isi): array
    {
        $topik = [];
        $nama = null;
        $kunciTerakhir = null;
        // File balasan boleh berisi semua bahasa walau multi-bahasa sedang dimatikan.
        $locales = array_keys(\App\Support\Fitur::semuaBahasa());

        foreach (preg_split('/\R/u', $isi) as $no => $baris) {
            $nomor = $no + 1;
            if (trim($baris) === '' || str_starts_with(ltrim($baris), '#')) {
                continue;
            }

            if (preg_match('/^==\s*([a-z0-9\-]+)\s*$/', $baris, $m)) {
                $nama = $m[1];
                if (isset($topik[$nama])) {
                    throw new FormatSalah("Baris {$nomor}: topik \"{$nama}\" sudah ada.");
                }
                $topik[$nama] = ['nama' => $nama, 'kata' => [], 'tanya' => [], 'jawab' => [], 'admin' => false];
                $kunciTerakhir = null;

                continue;
            }

            if ($nama === null) {
                throw new FormatSalah("Baris {$nomor}: tulis \"== nama-topik\" dulu sebelum isi topik.");
            }

            // Baris lanjutan jawaban (diawali spasi/tab).
            if (preg_match('/^[ \t]+\S/', $baris) && $kunciTerakhir) {
                [$jenis, $loc] = $kunciTerakhir;
                $topik[$nama][$jenis][$loc] .= "\n".trim($baris);

                continue;
            }

            if (! preg_match('/^([a-z]+)(?:\.([a-zA-Z_]+))?\s*:\s*(.*)$/u', $baris, $m)) {
                throw new FormatSalah("Baris {$nomor}: format tidak dikenali. Gunakan \"kata:\", \"tanya.id:\", \"jawab.id:\" atau \"admin:\".");
            }
            [, $kunci, $loc, $nilai] = $m;
            $nilai = trim($nilai);

            switch ($kunci) {
                case 'kata':
                    $topik[$nama]['kata'] = array_values(array_filter(array_map(
                        fn ($k) => self::normal($k), explode(',', $nilai)
                    )));
                    $kunciTerakhir = null;
                    break;

                case 'tanya':
                case 'jawab':
                    if (! in_array($loc, $locales, true)) {
                        throw new FormatSalah("Baris {$nomor}: bahasa \"{$loc}\" tidak dikenal. Pilihan: ".implode(', ', $locales).'.');
                    }
                    $topik[$nama][$kunci][$loc] = $nilai;
                    $kunciTerakhir = [$kunci, $loc];
                    break;

                case 'admin':
                    $topik[$nama]['admin'] = in_array(Str::lower($nilai), ['ya', 'yes', 'y', '1', 'true'], true);
                    $kunciTerakhir = null;
                    break;

                default:
                    throw new FormatSalah("Baris {$nomor}: \"{$kunci}\" tidak dikenal.");
            }
        }

        foreach (self::WAJIB as $w) {
            if (! isset($topik[$w])) {
                throw new FormatSalah("Topik \"{$w}\" wajib ada.");
            }
        }
        foreach ($topik as $t) {
            if (empty($t['jawab']['en']) && empty($t['jawab']['id'])) {
                throw new FormatSalah("Topik \"{$t['nama']}\" belum punya jawaban (minimal jawab.id atau jawab.en).");
            }
            if (! in_array($t['nama'], self::WAJIB, true) && empty($t['kata'])) {
                throw new FormatSalah("Topik \"{$t['nama']}\" belum punya kata kunci (kata:).");
            }
        }

        return $topik;
    }

    private function topik(): array
    {
        return $this->topik ??= self::parse(self::isiFile());
    }

    /** Huruf kecil, tanda baca jadi spasi; huruf Mandarin dibiarkan. */
    public static function normal(string $teks): string
    {
        $teks = Str::lower($teks);
        $teks = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $teks);

        return trim(preg_replace('/\s+/u', ' ', $teks));
    }

    /**
     * Tambahkan bentuk dasar kata berakhiran umum bahasa Indonesia supaya
     * "ongkirnya", "ukurannya", "bisakah" tetap cocok dengan kata kunci "ongkir", dst.
     */
    private static function denganKataDasar(string $pesan): string
    {
        $tambahan = [];
        foreach (explode(' ', $pesan) as $kata) {
            foreach (['nya', 'kah', 'lah', 'pun', 'ku', 'mu'] as $akhiran) {
                if (mb_strlen($kata) > mb_strlen($akhiran) + 2 && str_ends_with($kata, $akhiran)) {
                    $tambahan[] = mb_substr($kata, 0, -mb_strlen($akhiran));
                    break;
                }
            }
        }

        return $tambahan ? $pesan.' | '.implode(' ', $tambahan) : $pesan;
    }

    /** Skor: total panjang kata kunci yang cocok (frasa panjang lebih kuat dari kata pendek). */
    private function skor(string $pesan, array $topik): int
    {
        $skor = 0;
        $berspasi = ' '.$pesan.' ';

        foreach ($topik['kata'] as $kata) {
            $cocok = preg_match('/\p{Han}/u', $kata)
                ? str_contains($pesan, $kata)          // Mandarin tidak memakai spasi antar kata
                : str_contains($berspasi, ' '.$kata.' ');

            if ($cocok) {
                $skor += mb_strlen($kata);
            }
        }

        return $skor;
    }

    private function teks(array $peta, string $locale): string
    {
        $teks = $peta[$locale] ?? $peta['en'] ?? $peta['id'] ?? reset($peta) ?: '';

        // {toko} = nama toko (config), supaya file balasan bisa dipakai toko lain.
        return str_replace('{toko}', (string) config('toko.nama'), $teks);
    }

    /** @return array{teks: string, admin: bool, topik: string} */
    public function jawab(string $pesan, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $pesan = self::denganKataDasar(self::normal($pesan));

        $terbaik = null;
        $skorTerbaik = 0;
        foreach ($this->topik() as $t) {
            if (in_array($t['nama'], self::WAJIB, true)) {
                continue;
            }
            $s = $this->skor($pesan, $t);
            if ($s > $skorTerbaik) {
                [$terbaik, $skorTerbaik] = [$t, $s];
            }
        }

        $t = $terbaik ?? $this->topik()['tidak-ketemu'];

        // Kalau pembeli jelas minta admin (topik "admin" ikut cocok), tombol kontak selalu muncul.
        $mintaAdmin = isset($this->topik()['admin']) && $this->skor($pesan, $this->topik()['admin']) > 0;

        return ['teks' => $this->teks($t['jawab'], $locale), 'admin' => $t['admin'] || $mintaAdmin, 'topik' => $t['nama']];
    }

    public function sapaan(?string $locale = null): string
    {
        return $this->teks($this->topik()['sapaan']['jawab'], $locale ?? app()->getLocale());
    }

    /** @return array<int, string> pertanyaan contoh untuk tombol saran */
    public function saran(?string $locale = null, int $batas = 6): array
    {
        $locale ??= app()->getLocale();

        return collect($this->topik())
            ->filter(fn ($t) => ! empty($t['tanya']))
            ->map(fn ($t) => $this->teks($t['tanya'], $locale))
            ->filter()
            ->take($batas)
            ->values()
            ->all();
    }
}
