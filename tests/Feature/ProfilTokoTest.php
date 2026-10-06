<?php

namespace Tests\Feature;

use App\Support\Chatbot\Chatbot;
use App\Support\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Identitas toko dari toko/profil.php: nama, warna, logo, teks beranda. */
class ProfilTokoTest extends TestCase
{
    use RefreshDatabase;

    public function test_tema_logo_dan_teks_beranda_dari_profil(): void
    {
        config([
            'toko.nama' => 'Toko Uji',
            'toko.tema' => ['--kangkung' => '#c8102e', '--paper' => '#fff', '--jahat' => 'red;}</style><script>'],
            'toko.logo' => 'favicon.ico',
            'toko.beranda' => ['judul' => ['en' => 'Built for the heat.', 'id' => 'Dibuat untuk cuaca panas.']],
        ]);

        $this->assertSame(':root { --kangkung: #c8102e; --paper: #fff; }', Tema::css());
        $this->assertStringEndsWith('/favicon.ico', Tema::logo());

        $this->get('/')
            ->assertOk()
            ->assertSee('--kangkung: #c8102e', false)
            ->assertDontSee('<script>', false)
            ->assertSee('class="logo"', false)
            ->assertSee('Built for the heat.')
            ->assertSee('Toko Uji');
    }

    public function test_tanpa_profil_pakai_bawaan(): void
    {
        config(['toko.tema' => [], 'toko.logo' => null, 'toko.beranda' => null]);

        $this->assertSame('', Tema::css());
        $this->assertNull(Tema::logo());
        $this->get('/')->assertOk()->assertSee('Everyday basics in honest colors.')->assertDontSee('class="logo"', false);
    }

    public function test_logo_yang_filenya_tidak_ada_diabaikan(): void
    {
        config(['toko.logo' => 'toko/tidak-ada.png']);

        $this->assertNull(Tema::logo());
    }

    public function test_chatbot_memakai_nama_toko(): void
    {
        config(['toko.nama' => 'Toko Uji']);

        $this->assertStringContainsString('Toko Uji', app(Chatbot::class)->sapaan('en'));
        $this->assertStringNotContainsString('{toko}', app(Chatbot::class)->sapaan('id'));
    }
}
