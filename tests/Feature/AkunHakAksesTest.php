<?php

namespace Tests\Feature;

use App\Filament\Pages\FiturPaket;
use App\Filament\Resources\AkunAdmin\AkunAdminResource;
use App\Filament\Resources\AkunAdmin\Pages\ManageAkunAdmin;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Peran\Pages\ManagePeran;
use App\Filament\Resources\Peran\PeranResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Stocks\StockResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Fitur;
use Database\Seeders\RoleAndPermissionSeeder as R;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AkunHakAksesTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(R::class);
        Filament::setCurrentPanel('admin');

        $this->super = User::factory()->create(['email' => 'platform@contoh.com']);
        $this->super->assignRole(R::SUPER_ADMIN);
    }

    private function owner(): User
    {
        $o = User::factory()->create(['email' => 'owner@toko.test']);
        $o->assignRole(R::OWNER);

        return $o;
    }

    private function masuk(User $u): void
    {
        $this->flushSession();
        $this->actingAs($u, 'admin');
    }

    public function test_super_admin_membuat_akun_owner(): void
    {
        $this->masuk($this->super);
        $this->get(AkunAdminResource::getUrl())->assertOk()->assertSee('Akun Owner');

        Livewire::test(ManageAkunAdmin::class)
            ->callAction('create', data: [
                'nama_lengkap' => 'Bu Sari', 'email' => 'Sari@Toko.test', 'password_baru' => 'rahasia-owner-1',
            ])
            ->assertHasNoActionErrors();

        $owner = User::where('email', 'sari@toko.test')->firstOrFail();
        $this->assertTrue($owner->hasRole(R::OWNER));
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue($owner->adalahAdmin());

        // Owner baru bisa masuk & melihat dashboard, tapi tidak melihat Fitur & paket.
        $this->masuk($owner);
        $this->get('/admin')->assertOk();
        $this->get(FiturPaket::getUrl())->assertForbidden();
    }

    public function test_daftar_akun_menyembunyikan_super_admin(): void
    {
        $owner = $this->owner();
        $staf = User::factory()->create();
        Role::findOrCreate('Gudang', R::GUARD)->syncPermissions(['stok.edit']);
        $staf->assignRole('Gudang');
        $superLain = User::factory()->create();
        $superLain->assignRole(R::SUPER_ADMIN);

        // Super Admin hanya melihat akun Owner.
        $this->masuk($this->super);
        Livewire::test(ManageAkunAdmin::class)
            ->assertCanSeeTableRecords([$owner])
            ->assertCanNotSeeTableRecords([$staf, $this->super, $superLain]);

        // Owner hanya melihat staf (bukan dirinya, bukan Owner lain, bukan Super Admin).
        $this->masuk($owner);
        Livewire::test(ManageAkunAdmin::class)
            ->assertCanSeeTableRecords([$staf])
            ->assertCanNotSeeTableRecords([$owner, $this->super, $superLain]);
        $this->assertFalse(AkunAdminResource::canEdit($this->super));
    }

    public function test_owner_membuat_peran_dan_staf_dengan_akses_terbatas(): void
    {
        $owner = $this->owner();
        $this->masuk($owner);
        $this->get(PeranResource::getUrl())->assertOk();

        // Izin khusus Owner / Super Admin tidak bisa diselipkan.
        Livewire::test(ManagePeran::class)
            ->callAction('create', data: ['name' => 'Gudang', 'izin' => ['stok.edit', 'staf.kelola', 'fitur.kelola']])
            ->assertHasActionErrors(['izin.1', 'izin.2']);
        Livewire::test(ManagePeran::class)
            ->callAction('create', data: ['name' => 'Gudang', 'izin' => ['stok.edit']])
            ->assertHasNoActionErrors();
        $peran = Role::where('name', 'Gudang')->firstOrFail();
        $this->assertSame(['stok.edit'], $peran->permissions->pluck('name')->all());

        // Nama sistem tidak boleh dipakai.
        Livewire::test(ManagePeran::class)
            ->callAction('create', data: ['name' => 'Owner', 'izin' => ['stok.edit']])
            ->assertHasActionErrors(['name']);

        Livewire::test(ManageAkunAdmin::class)
            ->callAction('create', data: [
                'nama_lengkap' => 'Andi Gudang', 'email' => 'andi@toko.test', 'password_baru' => 'rahasia-staf-1', 'peran' => 'Gudang',
            ])
            ->assertHasNoActionErrors();
        $staf = User::where('email', 'andi@toko.test')->firstOrFail();
        $this->assertTrue($staf->hasRole('Gudang'));

        // Peran Owner / Super Admin tidak bisa dipilih untuk staf.
        Livewire::test(ManageAkunAdmin::class)
            ->callAction('create', data: [
                'nama_lengkap' => 'Nakal', 'email' => 'nakal@toko.test', 'password_baru' => 'rahasia-staf-1', 'peran' => R::SUPER_ADMIN,
            ])
            ->assertHasActionErrors(['peran']);

        // Staf Gudang: hanya stok.
        $this->masuk($staf);
        $this->get(StockResource::getUrl())->assertOk();
        $this->get(ProductResource::getUrl())->assertForbidden();
        $this->get(OrderResource::getUrl())->assertForbidden();
        $this->get(AkunAdminResource::getUrl())->assertForbidden();
        $this->get(PeranResource::getUrl())->assertForbidden();
    }

    public function test_menonaktifkan_akun_staf(): void
    {
        $owner = $this->owner();
        Role::findOrCreate('CS', R::GUARD)->syncPermissions(['order.lihat']);
        $staf = User::factory()->create(['email' => 'cs@toko.test']);
        $staf->assignRole('CS');
        $staf->createToken('hp', ['admin']);

        $this->masuk($owner);
        Livewire::test(ManageAkunAdmin::class)
            ->callTableAction('edit', $staf, data: ['nama_lengkap' => 'CS', 'email' => 'cs@toko.test', 'peran' => 'CS', 'aktif' => false])
            ->assertHasNoTableActionErrors();

        $staf->refresh();
        $this->assertNotNull($staf->nonaktif_pada);
        $this->assertFalse($staf->adalahAdmin());
        $this->assertSame(0, $staf->tokens()->count());
        $this->assertTrue($staf->hasRole('CS')); // data & peran tetap, hanya tidak bisa masuk
    }

    public function test_fitur_staf_mati(): void
    {
        $owner = $this->owner();
        Role::findOrCreate('CS', R::GUARD)->syncPermissions(['order.lihat']);
        $staf = User::factory()->create();
        $staf->assignRole('CS');

        Fitur::terapkan(1, []);

        $this->assertFalse($staf->adalahAdmin());   // staf tidak bisa masuk
        $this->assertTrue($owner->adalahAdmin());   // Owner tetap
        $this->assertTrue($this->super->adalahAdmin());

        $this->masuk($owner);
        $this->get(AkunAdminResource::getUrl())->assertForbidden();
        $this->get(PeranResource::getUrl())->assertForbidden();

        // Super Admin tetap bisa mengelola akun Owner.
        $this->masuk($this->super);
        $this->get(AkunAdminResource::getUrl())->assertOk();
    }

    public function test_email_pembeli_tidak_bisa_dipakai_untuk_akun_admin(): void
    {
        User::factory()->create(['email' => 'pembeli@contoh.com']);
        $this->masuk($this->super);

        Livewire::test(ManageAkunAdmin::class)
            ->callAction('create', data: ['nama_lengkap' => 'X', 'email' => 'pembeli@contoh.com', 'password_baru' => 'rahasia-owner-1'])
            ->assertHasActionErrors(['email']);
    }

    public function test_profil_bisa_dibuka_untuk_ganti_password(): void
    {
        $this->masuk($this->owner());
        $this->get('/admin/profile')->assertOk()->assertSee('Nama');
    }

    public function test_super_admin_dengan_username_tanpa_email(): void
    {
        putenv('SUPERADMIN_PASSWORD=rahasia-super-123');
        try {
            $this->artisan('toko:super-admin', ['email' => 'Frendi', '--murni' => true])->assertSuccessful();
        } finally {
            putenv('SUPERADMIN_PASSWORD');
        }
        $this->artisan('toko:super-admin', ['email' => 'bukan valid!'])->assertFailed();

        $u = User::where('username', 'frendi')->firstOrFail();
        $this->assertTrue($u->adalahSuperAdmin());
        $this->assertStringEndsWith('.invalid', $u->email);

        // Login panel dengan username.
        $this->flushSession();
        Livewire::test(\App\Filament\Pages\Auth\Masuk::class)
            ->fillForm(['email' => 'frendi', 'password' => 'rahasia-super-123'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($u, 'admin');

        // Owner tetap login dengan email; password salah ditolak.
        auth('admin')->logout();
        $owner = User::factory()->create(['email' => 'owner2@toko.test', 'password' => 'rahasia-owner-9']);
        $owner->assignRole(R::OWNER);
        Livewire::test(\App\Filament\Pages\Auth\Masuk::class)
            ->fillForm(['email' => 'Owner2@toko.test', 'password' => 'rahasia-owner-9'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($owner, 'admin');
        auth('admin')->logout();
        Livewire::test(\App\Filament\Pages\Auth\Masuk::class)
            ->fillForm(['email' => 'frendi', 'password' => 'salah-salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
    }
}
