<?php

namespace App\Support;

/** Label 3 bahasa untuk pilihan tetap armada (config/travel.php). */
class Spesifikasi
{
    public static function label(string $grup, ?string $kunci, ?string $locale = null): ?string
    {
        if ($kunci === null) {
            return null;
        }
        $label = config("travel.{$grup}.{$kunci}");
        if (! is_array($label)) {
            return $label ?? $kunci;
        }

        return $label[$locale ?? app()->getLocale()] ?? $label['en'] ?? $kunci;
    }

    /** @return array<string, string> kunci => label, untuk Select/CheckboxList */
    public static function pilihan(string $grup, string $locale = 'id'): array
    {
        return collect(config("travel.{$grup}", []))
            ->map(fn ($label, $kunci) => is_array($label) ? ($label[$locale] ?? $label['en'] ?? $kunci) : $label)
            ->all();
    }

    public static function kursi(?int $jumlah, ?string $locale = null): ?string
    {
        if (! $jumlah) {
            return null;
        }

        return match ($locale ?? app()->getLocale()) {
            'id' => "{$jumlah} kursi",
            'zh_TW' => "{$jumlah} 人座",
            default => $jumlah === 1 ? '1 seat' : "{$jumlah} seats",
        };
    }

    /** @return array<int, string> label fasilitas dalam bahasa yang diminta */
    public static function fasilitas(?array $kunci, ?string $locale = null): array
    {
        return collect($kunci ?? [])
            ->filter(fn ($k) => config("travel.fasilitas.{$k}") !== null)
            ->map(fn ($k) => self::label('fasilitas', $k, $locale))
            ->values()->all();
    }
}
