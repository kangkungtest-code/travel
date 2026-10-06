<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** HP admin yang menerima notifikasi push (token Firebase Cloud Messaging). */
#[Fillable(['user_id', 'personal_access_token_id', 'fcm_token', 'platform', 'terakhir_aktif'])]
class PerangkatAdmin extends Model
{
    use HasUuids;

    protected $table = 'perangkat_admin';

    protected function casts(): array
    {
        return ['terakhir_aktif' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
