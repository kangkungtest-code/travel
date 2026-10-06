<?php

namespace Database\Seeders;

use App\Models\StockLocation;
use Illuminate\Database\Seeder;

class StockLocationSeeder extends Seeder
{
    public function run(): void
    {
        if (StockLocation::query()->where('is_default', true)->exists()) {
            return;
        }

        StockLocation::create(['nama' => 'Gudang Utama', 'is_default' => true]);
    }
}
