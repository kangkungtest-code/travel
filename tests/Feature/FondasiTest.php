<?php

namespace Tests\Feature;

use App\Models\StockLocation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FondasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['ADMIN_EMAIL'] = $_SERVER['ADMIN_EMAIL'] = 'owner@toko.test';
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = 'rahasia-123';
    }

    public function test_seeder_membuat_role_lokasi_dan_admin(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // idempotent

        $admin = User::where('email', 'owner@toko.test')->firstOrFail();

        // ADMIN_EMAIL = Super Admin: hanya mengatur platform, tidak memegang data toko.
        $this->assertTrue($admin->hasRole('Super Admin'));
        $this->assertFalse($admin->hasRole('Owner'));
        $this->assertTrue($admin->hasPermissionTo('fitur.kelola'));
        $this->assertFalse($admin->hasPermissionTo('order.ubah_status'));
        $this->assertSame(1, StockLocation::where('is_default', true)->count());
        $this->assertSame(1, User::count());
    }

    public function test_hanya_user_dengan_role_bisa_akses_panel_admin(): void
    {
        $this->seed(DatabaseSeeder::class);
        $panel = Filament::getPanel('admin');

        $admin = User::where('email', 'owner@toko.test')->firstOrFail();
        $customer = User::factory()->create();

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertFalse($customer->canAccessPanel($panel));
    }

    public function test_halaman_login_admin_bisa_dibuka(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_login_lewat_guard_admin_masuk_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'owner@toko.test')->firstOrFail();

        // Super Admin diarahkan ke Fitur & paket; Owner melihat dashboard.
        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect(\App\Filament\Pages\FiturPaket::getUrl());
        $owner = User::factory()->create();
        $owner->assignRole('Owner');
        $this->flushSession();
        $this->actingAs($owner, 'admin')->get('/admin')->assertOk();
        $this->assertGuest('web');
    }

    public function test_customer_ditolak_dari_panel_admin(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer, 'admin')->get('/admin')->assertForbidden();
    }

    public function test_api_v1_ping(): void
    {
        $this->getJson('/api/v1/ping')->assertOk()->assertJson(['status' => 'ok']);
    }
}
