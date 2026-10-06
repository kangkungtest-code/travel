<?php

namespace Tests\Feature;

use App\Filament\Pages\ChatbotKontak;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\Chatbot\Chatbot;
use App\Support\Chatbot\FormatSalah;
use App\Support\KontakAdmin;
use Database\Seeders\RoleAndPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(Chatbot::pathEdit());
        parent::tearDown();
    }

    public function test_file_bawaan_valid_dan_mencocokkan_tiga_bahasa(): void
    {
        $bot = new Chatbot;

        $this->assertSame('ongkir', $bot->jawab('berapa ongkir ke taiwan?', 'id')['topik']);
        $this->assertSame('lama-kirim', $bot->jawab('How long does shipping take?', 'en')['topik']);
        $this->assertSame('retur', $bot->jawab('我要退貨', 'zh_TW')['topik']);
        $this->assertSame('retur', $bot->jawab('barangnya salah kirim kak', 'id')['topik']);
        $this->assertSame('salam', $bot->jawab('halo', 'id')['topik']);
        $this->assertSame('ongkir', $bot->jawab('berapa ongkirnya?', 'id')['topik']);

        // Minta admin secara eksplisit -> tombol kontak muncul walau topik lain yang menang.
        $campur = $bot->jawab('barangnya salah kirim, mau ngobrol sama admin', 'id');
        $this->assertSame('retur', $campur['topik']);
        $this->assertTrue($campur['admin']);

        // "wa" tidak boleh cocok di dalam kata "warna".
        $this->assertNotSame('admin', $bot->jawab('warna biru', 'id')['topik']);

        $tidak = $bot->jawab('qwerty zxcv', 'en');
        $this->assertSame('tidak-ketemu', $tidak['topik']);
        $this->assertTrue($tidak['admin']);
        $this->assertStringContainsString('Sorry', $tidak['teks']);

        $this->assertNotEmpty($bot->saran('zh_TW'));
    }

    public function test_validasi_format(): void
    {
        $dasar = "== sapaan\njawab.id: Halo\n\n== tidak-ketemu\njawab.id: Maaf\n";

        $this->assertCount(2, Chatbot::parse($dasar));

        foreach ([
            "== ongkir\nkata: ongkir\njawab.id: x\n" => 'wajib ada',
            $dasar."== ongkir\njawab.id: x\n" => 'kata kunci',
            $dasar."== ongkir\nkata: a\njawab.fr: x\n" => 'tidak dikenal',
            $dasar."jawaban tanpa format\n" => 'format tidak dikenali',
            $dasar."== sapaan\njawab.id: dobel\n" => 'sudah ada',
        ] as $isi => $pesan) {
            try {
                Chatbot::parse($isi);
                $this->fail("Seharusnya gagal: {$pesan}");
            } catch (FormatSalah $e) {
                $this->assertStringContainsString($pesan, $e->getMessage());
            }
        }

        // Baris lanjutan (diawali spasi) digabung ke jawaban.
        $t = Chatbot::parse($dasar."== a\nkata: a\njawab.id: baris satu\n  baris dua\n");
        $this->assertSame("baris satu\nbaris dua", $t['a']['jawab']['id']);
    }

    public function test_endpoint_dan_tombol_kontak(): void
    {
        $this->getJson('/chatbot')->assertOk()->assertJsonStructure(['teks', 'saran', 'kontak'])->assertJsonPath('kontak', []);

        Pengaturan::simpan('kontak.wa', KontakAdmin::normalisasiWa('0812-3456-7890'));
        Pengaturan::simpan('kontak.line', '@kangkung');

        $res = $this->postJson('/chatbot', ['pesan' => 'mau ngobrol sama admin'])->assertOk();
        $kontak = collect($res->json('kontak'))->keyBy('jenis');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $kontak['wa']['url']);
        $this->assertStringContainsString(rawurlencode('mau ngobrol sama admin'), $kontak['wa']['url']);
        $this->assertSame('https://line.me/R/ti/p/%40kangkung', $kontak['line']['url']);

        $this->postJson('/chatbot', ['pesan' => 'berapa ongkirnya'])->assertJsonPath('kontak', []);
        $this->postJson('/chatbot', ['pesan' => ''])->assertUnprocessable();

        $this->withSession(['locale' => 'zh_TW'])->postJson('/chatbot', ['pesan' => '運費'])
            ->assertJsonPath('teks', fn ($t) => str_contains($t, '運費'));

        $this->get('/faq')->assertSee('wa.me/6281234567890', false);
    }

    public function test_line_id_pribadi_dan_nomor_internasional(): void
    {
        $this->assertSame('886912345678', KontakAdmin::normalisasiWa('+886 912 345 678'));
        Pengaturan::simpan('kontak.line', 'frendi.toko');
        $this->assertSame('https://line.me/R/ti/p/~frendi.toko', KontakAdmin::urlLine());
    }

    public function test_admin_mengedit_file_chatbot(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Owner');
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin, 'admin');

        $this->get(ChatbotKontak::getUrl())->assertOk()->assertSee('File balasan chatbot');

        $baru = Chatbot::isiFile()."\n== promo\nkata: promo, diskon, sale\njawab.id: Promo akhir bulan diskon 10%.\njawab.en: 10% off at the end of the month.\n";

        Livewire::test(ChatbotKontak::class)
            ->set('data.balasan', $baru)
            ->call('simpan')
            ->assertNotified('Tersimpan');

        $this->assertSame('promo', (new Chatbot)->jawab('ada diskon?', 'id')['topik']);

        // Format salah tidak tersimpan.
        Livewire::test(ChatbotKontak::class)
            ->set('data.balasan', "== promo\nkata: a\njawab.id: x\n")
            ->call('simpan')
            ->assertNotified('File balasan belum disimpan');
        $this->assertSame('promo', (new Chatbot)->jawab('ada diskon?', 'id')['topik']);
    }
}
