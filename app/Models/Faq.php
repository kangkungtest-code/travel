<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

#[Fillable(['pertanyaan_terjemahan', 'jawaban_terjemahan', 'urutan', 'is_active'])]
class Faq extends Model
{
    use HasTranslations, HasUuids;

    /** @var array<int, string> */
    public array $translatable = ['pertanyaan_terjemahan', 'jawaban_terjemahan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer', 'is_active' => 'boolean'];
    }
}
