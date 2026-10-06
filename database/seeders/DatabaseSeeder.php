<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder dasar. Aman dijalankan berulang (idempotent) — dipanggil di
     * setiap deploy.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            StockLocationSeeder::class,
            AdminUserSeeder::class,
            KebijakanSeeder::class,
        ]);

        // Katalog contoh hanya untuk laptop & server dev/demo, tidak untuk production.
        if (app()->environment(['local', 'dev', 'demo'])) {
            $this->call(DemoCatalogSeeder::class);
        }
    }
}
