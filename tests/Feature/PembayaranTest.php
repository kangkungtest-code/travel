<?php

namespace Tests\Feature;

use App\Actions\Order\BuatOrderAction;
use App\Actions\Pembayaran\RefundPembayaranAction;
use App\Exceptions\TokoException;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Stock;
use App\Models\User;
use App\Payments\MetodePembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\TokoFixture;
use Tests\TestCase;

class PembayaranTest extends TestCase
{
    use RefreshDatabase, TokoFixture;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->siapkanToko();

        config([
            'services.paypal' => ['client_id' => 'pp-id', 'client_secret' => 'pp-secret', 'webhook_id' => 'WH-1', 'mode' => 'sandbox'],
            'services.xendit.secret_key' => 'xnd_development_abc',
            'services.xendit.callback_token' => 'token-rahasia',
        ]);
        ExchangeRate::create(['mata_uang_asal' => 'IDR', 'mata_uang_tujuan' => 'USD', 'rate' => 0.00006, 'margin_persen' => 0, 'sumber' => 'manual', 'berlaku_dari' => now()->subDay()]);

        $this->user = $this->pembeli();
    }

    private function order(string $mataUang = 'IDR', int $qty = 2): Order
    {
        $alamat = $this->alamat($this->user);
        $cart = $this->user->cart()->firstOrCreate();
        $cart->items()->create(['variant_id' => $this->kaosM->id, 'qty' => $qty]);

        return app(BuatOrderAction::class)->execute($this->user, $alamat, $mataUang);
    }

    private function fakeXendit(string $descriptor = 'QR_STRING', string $nilai = '00020101021126QRISCONTOH'): void
    {
        Http::fake([
            'api.xendit.co/v3/payment_requests' => Http::response([
                'payment_request_id' => 'pr-123',
                'status' => 'REQUIRES_ACTION',
                'actions' => [['type' => 'PRESENT_TO_CUSTOMER', 'descriptor' => $descriptor, 'value' => $nilai]],
            ], 201),
            'api.xendit.co/refunds' => Http::response(['id' => 'rfd-1', 'status' => 'PENDING']),
        ]);
    }

    private function webhookXendit(Payment $p, string $token = 'token-rahasia', string $event = 'payment.capture', ?int $jumlah = null)
    {
        return $this->postJson('/webhook/xendit', [
            'event' => $event,
            'business_id' => 'biz',
            'created' => now()->toIso8601String(),
            'data' => [
                'payment_id' => 'py-999',
                'payment_request_id' => $p->transaksi_id_eksternal,
                'reference_id' => $p->id,
                'status' => $event === 'payment.capture' ? 'SUCCEEDED' : 'FAILED',
                'request_amount' => $jumlah ?? (int) $p->jumlah,
                'currency' => 'IDR',
                'channel_code' => 'QRIS',
            ],
        ], ['x-callback-token' => $token]);
    }

    private function stok(): Stock
    {
        return Stock::where('variant_id', $this->kaosM->id)->firstOrFail();
    }

    public function test_pilihan_metode_dan_konversi_mata_uang(): void
    {
        $idr = $this->order('IDR');
        $pilihan = collect(MetodePembayaran::pilihan($idr))->keyBy('kode');

        $this->assertSame(['paypal', 'xendit_qris', 'xendit_va'], $pilihan->keys()->all());
        $this->assertSame('USD', $pilihan['paypal']['mata_uang']);
        // (2 x 100.000 x 0,00006) + (20.000 x 0,00006) = 12 + 1,2
        $this->assertEquals(13.20, $pilihan['paypal']['jumlah']);
        $this->assertTrue($pilihan['paypal']['beda_mata_uang']);
        $this->assertEquals(220000, $pilihan['xendit_qris']['jumlah']);

        $this->actingAs($this->user, 'web')->get(route('akun.pesanan.show', $idr))
            ->assertSee('Pay now')->assertSee('$13.20')->assertSee('Rp220.000')->assertSee('Charged in USD');
    }

    public function test_simulasi_bayar_xendit_mode_test(): void
    {
        $this->fakeXendit();
        Http::fake(['api.xendit.co/v3/payment_requests/pr-123/simulate' => Http::response(['status' => 'PENDING'])]);
        $order = $this->order();

        $this->actingAs($this->user, 'web')->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_qris']);
        $this->get(route('akun.pesanan.show', $order))
            ->assertSee('Simulasikan bayar')->assertSee('belum pernah ada yang masuk');

        $this->from(route('akun.pesanan.show', $order))
            ->post(route('akun.pesanan.simulasi', $order))
            ->assertRedirect(route('akun.pesanan.show', $order))
            ->assertSessionHas('status');

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.xendit.co/v3/payment_requests/pr-123/simulate'
            && $r['amount'] === 220000);

        // Xendit lalu mengirim webhook -> tercatat & order dibayar.
        $this->webhookXendit(Payment::firstOrFail())->assertOk();
        $this->assertSame(Order::STATUS_DIBAYAR, $order->fresh()->status);
        $this->assertStringContainsString('diterima, pembayaran berhasil', \App\Models\Pengaturan::ambil('webhook_terakhir.xendit'));
    }

    public function test_cek_status_langsung_ke_xendit_tanpa_webhook(): void
    {
        $this->fakeXendit();
        $order = $this->order();
        $this->actingAs($this->user, 'web')->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_qris']);
        $p = Payment::firstOrFail();

        // Tagihan milik orang lain / masih menunggu -> tidak berubah.
        Http::fake(['api.xendit.co/v3/payment_requests/pr-123' => Http::sequence()
            ->push(['payment_request_id' => 'pr-123', 'reference_id' => 'bukan-punya-kita', 'status' => 'SUCCEEDED', 'request_amount' => 220000, 'currency' => 'IDR'])
            ->push(['payment_request_id' => 'pr-123', 'reference_id' => $p->id, 'status' => 'SUCCEEDED', 'request_amount' => 220000, 'currency' => 'IDR']),
        ]);
        $this->getJson(route('akun.pesanan.status', $order))->assertJson(['status' => Order::STATUS_MENUNGGU_PEMBAYARAN]);

        // Dibatasi: dalam 15 detik tidak bertanya lagi ke Xendit.
        $this->getJson(route('akun.pesanan.status', $order))->assertJson(['status' => Order::STATUS_MENUNGGU_PEMBAYARAN]);
        Http::assertSentCount(1); // hanya 1x cek (catatan request direset oleh Http::fake kedua)

        $this->travel(16)->seconds();
        $this->getJson(route('akun.pesanan.status', $order))->assertJson(['status' => Order::STATUS_DIBAYAR]);
        $this->assertSame(Payment::BERHASIL, $p->fresh()->status);
    }

    public function test_simulasi_tidak_ada_untuk_kunci_live_atau_orang_lain(): void
    {
        $this->fakeXendit();
        $order = $this->order();
        $this->actingAs($this->user, 'web')->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_qris']);

        $this->actingAs($this->pembeli(), 'web')
            ->post(route('akun.pesanan.simulasi', $order))->assertNotFound();

        config(['services.xendit.secret_key' => 'xnd_production_abc']);
        $this->actingAs($this->user, 'web')
            ->post(route('akun.pesanan.simulasi', $order))->assertNotFound();
    }

    public function test_order_usd_dibayar_qris_memakai_total_idr(): void
    {
        $usd = $this->order('USD');
        $pilihan = collect(MetodePembayaran::pilihan($usd))->keyBy('kode');

        $this->assertEquals((float) $usd->total, $pilihan['paypal']['jumlah']);
        $this->assertFalse($pilihan['paypal']['beda_mata_uang']);
        $this->assertEquals(220000, $pilihan['xendit_qris']['jumlah']);
    }

    public function test_alur_qris_sampai_dibayar_lewat_webhook(): void
    {
        $this->fakeXendit();
        $order = $this->order();

        $this->actingAs($this->user, 'web')
            ->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_qris'])
            ->assertRedirect(route('akun.pesanan.show', $order));

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.xendit.co/v3/payment_requests'
            && $r->hasHeader('api-version', '2024-11-11')
            && $r['channel_code'] === 'QRIS'
            && $r['request_amount'] === 220000
            && $r['currency'] === 'IDR');

        $p = Payment::firstOrFail();
        $this->assertSame(Payment::PENDING, $p->status);
        $this->assertSame('pr-123', $p->transaksi_id_eksternal);
        $this->get(route('akun.pesanan.show', $order))->assertSee('<svg', false)->assertSee('data-cek-status', false);

        // Token salah ditolak.
        $this->webhookXendit($p, 'palsu')->assertUnauthorized();
        $this->assertSame(Order::STATUS_MENUNGGU_PEMBAYARAN, $order->fresh()->status);

        $this->webhookXendit($p)->assertOk();
        $order->refresh();
        $this->assertSame(Order::STATUS_DIBAYAR, $order->status);
        $this->assertSame(Payment::BERHASIL, $p->fresh()->status);
        $this->assertSame('py-999', $p->fresh()->id_capture);
        $this->assertSame(3, $this->stok()->jumlah);
        $this->assertSame(0, $this->stok()->jumlah_reserved);

        // Webhook dikirim ulang: tidak diproses dua kali.
        $this->webhookXendit($p)->assertOk();
        $this->assertSame(3, $this->stok()->jumlah);
        $this->assertSame(1, $order->statusHistories()->where('ke', Order::STATUS_DIBAYAR)->count());

        $this->getJson(route('akun.pesanan.status', $order))->assertJson(['status' => 'dibayar']);
    }

    public function test_va_bca(): void
    {
        $this->fakeXendit('VIRTUAL_ACCOUNT_NUMBER', '8881761038089006');
        $order = $this->order();

        $this->actingAs($this->user, 'web')->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_va'])
            ->assertSessionHasErrors('bank');
        $this->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_va', 'bank' => 'BCA']);

        Http::assertSent(fn (HttpRequest $r) => ($r['channel_code'] ?? null) === 'BCA_VIRTUAL_ACCOUNT');
        $this->get(route('akun.pesanan.show', $order))->assertSee('8881761038089006')->assertSee('BCA virtual account number');
    }

    public function test_ganti_metode_dan_pakai_ulang_tagihan(): void
    {
        $this->fakeXendit();
        $order = $this->order();
        $this->actingAs($this->user, 'web');

        $this->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_qris']);
        $this->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_qris']);
        $this->assertSame(1, Payment::count());
        Http::assertSentCount(1);

        $this->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_va', 'bank' => 'BNI']);
        $this->assertSame(Payment::KADALUARSA, Payment::where('gateway', 'xendit_qris')->first()->status);
        $this->assertSame(Payment::PENDING, Payment::where('gateway', 'xendit_va')->first()->status);
    }

    public function test_gateway_error_tidak_membuat_tagihan_menggantung(): void
    {
        Http::fake(['api.xendit.co/*' => Http::response(['error_code' => 'API_VALIDATION_ERROR'], 400)]);
        $order = $this->order();

        $this->actingAs($this->user, 'web')->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_qris'])
            ->assertSessionHasErrors('order');
        $this->assertSame(Payment::GAGAL, Payment::firstOrFail()->status);
    }

    public function test_alur_paypal_redirect_dan_capture(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'A21', 'expires_in' => 32400]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PP-ORDER/capture' => Http::response([
                'id' => 'PP-ORDER', 'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '13.20']]]]]],
            ], 201),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PP-ORDER', 'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PP-ORDER']],
            ], 201),
        ]);
        $order = $this->order();

        $this->actingAs($this->user, 'web')->post(route('akun.pesanan.bayar', $order), ['metode' => 'paypal'])
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PP-ORDER');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v2/checkout/orders')
            && $r['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => '13.20']
            && str_contains($r['payment_source']['paypal']['experience_context']['return_url'], '/kembali'));

        $p = Payment::firstOrFail();
        $this->get(route('bayar.paypal.kembali', $p).'?token=PP-ORDER')
            ->assertRedirect(route('akun.pesanan.show', $order))->assertSessionHas('status');

        $this->assertSame(Order::STATUS_DIBAYAR, $order->fresh()->status);
        $this->assertSame('CAP-1', $p->fresh()->id_capture);
    }

    public function test_twd_tanpa_desimal_untuk_paypal(): void
    {
        $this->assertSame('249', \App\Payments\PayPalGateway::nilai(249.0, 'TWD'));
        $this->assertSame('13.20', \App\Payments\PayPalGateway::nilai(13.2, 'USD'));
    }

    public function test_webhook_paypal_diverifikasi(): void
    {
        $order = $this->order();
        $p = $order->payments()->create(['gateway' => 'paypal', 'status' => 'pending', 'mata_uang' => 'USD', 'jumlah' => 13.20, 'transaksi_id_eksternal' => 'PP-ORDER']);
        $event = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAP-9', 'custom_id' => $p->id,
                'amount' => ['currency_code' => 'USD', 'value' => '13.20'],
                'supplementary_data' => ['related_ids' => ['order_id' => 'PP-ORDER']],
            ],
        ];

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'A21']),
            '*/v1/notifications/verify-webhook-signature' => Http::sequence()
                ->push(['verification_status' => 'FAILURE'])
                ->push(['verification_status' => 'SUCCESS']),
        ]);

        $this->postJson('/webhook/paypal', $event)->assertUnauthorized();
        $this->assertSame(Order::STATUS_MENUNGGU_PEMBAYARAN, $order->fresh()->status);

        $this->postJson('/webhook/paypal', $event, ['PAYPAL-TRANSMISSION-ID' => 't'])->assertOk();
        $this->assertSame(Order::STATUS_DIBAYAR, $order->fresh()->status);
    }

    public function test_jumlah_tidak_cocok_tidak_mengubah_order(): void
    {
        $this->fakeXendit();
        $order = $this->order();
        $this->actingAs($this->user, 'web')->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_qris']);
        $p = Payment::firstOrFail();

        $this->webhookXendit($p, jumlah: 1000)->assertOk();

        $this->assertSame(Order::STATUS_MENUNGGU_PEMBAYARAN, $order->fresh()->status);
        $this->assertStringContainsString('tidak sama', $p->fresh()->catatan);
        $this->assertTrue($order->statusHistories()->where('catatan', 'like', '%tidak cocok%')->exists());
    }

    public function test_pembayaran_telat_setelah_kadaluarsa_ditandai_untuk_refund(): void
    {
        $this->fakeXendit();
        $order = $this->order();
        $this->actingAs($this->user, 'web')->post(route('akun.pesanan.bayar', $order), ['metode' => 'xendit_qris']);
        $p = Payment::firstOrFail();

        $this->travel(25)->hours();
        Artisan::call('toko:kadaluarsakan-order');
        $this->assertSame(Order::STATUS_KADALUARSA, $order->fresh()->status);
        $this->assertSame(Payment::KADALUARSA, $p->fresh()->status);

        // Halaman order tidak lagi menawarkan pembayaran.
        $this->get(route('akun.pesanan.show', $order))->assertDontSee('Pay now');

        // Uang tetap masuk (mis. pembeli bayar tepat sebelum QR kadaluarsa).
        $this->webhookXendit($p)->assertOk();
        $this->assertSame(Payment::BERHASIL, $p->fresh()->status);
        $this->assertSame(Order::STATUS_KADALUARSA, $order->fresh()->status);
        $this->assertTrue($order->statusHistories()->where('catatan', 'like', '%perlu refund%')->exists());
    }

    public function test_refund_otomatis_dan_gagal(): void
    {
        $this->fakeXendit();
        $order = $this->order();
        $p = $order->payments()->create(['gateway' => 'xendit_qris', 'status' => 'berhasil', 'mata_uang' => 'IDR', 'jumlah' => 220000, 'transaksi_id_eksternal' => 'pr-123', 'dibayar_pada' => now()]);

        app(RefundPembayaranAction::class)->execute($order, 'Retur');
        $this->assertSame(Payment::DIREFUND, $p->fresh()->status);
        $this->assertSame('rfd-1', $p->fresh()->refund_id);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/refunds') && $r['payment_request_id'] === 'pr-123' && $r['amount'] === 220000);

        // Tidak ada pembayaran yang bisa direfund lagi.
        $this->expectException(TokoException::class);
        app(RefundPembayaranAction::class)->execute($order, 'Retur');
    }

    public function test_kunci_live_ditolak_di_luar_production(): void
    {
        config(['services.paypal.mode' => 'live', 'services.xendit.secret_key' => 'xnd_production_xyz']);

        $this->assertNull(MetodePembayaran::gateway('paypal'));
        $this->assertNull(MetodePembayaran::gateway('xendit_qris'));
        $this->assertFalse(MetodePembayaran::adaYangAktif());
    }

    public function test_tanpa_kredensial_tombol_bayar_belum_muncul(): void
    {
        config(['services.paypal.client_id' => null, 'services.xendit.secret_key' => null]);
        $order = $this->order();

        $this->actingAs($this->user, 'web')->get(route('akun.pesanan.show', $order))
            ->assertSee('Online payment opens soon')->assertDontSee('Pay now');
        $this->post(route('akun.pesanan.bayar', $order), ['metode' => 'paypal'])->assertSessionHasErrors('order');
    }
}
