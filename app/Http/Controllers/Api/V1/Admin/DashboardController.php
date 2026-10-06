<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminApi\Format;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Support\Laporan;
use App\Support\NotifikasiAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public const PERIODE = ['hari_ini', '7_hari', '30_hari', 'bulan_ini'];

    public function __invoke(Request $request): JsonResponse
    {
        $periode = $request->validate(['periode' => ['nullable', 'in:'.implode(',', self::PERIODE)]])['periode'] ?? '7_hari';

        $hariIni = now(config('toko.zona_waktu'));
        $dari = match ($periode) {
            'hari_ini' => $hariIni->copy(),
            '7_hari' => $hariIni->copy()->subDays(6),
            '30_hari' => $hariIni->copy()->subDays(29),
            'bulan_ini' => $hariIni->copy()->startOfMonth(),
        };
        $laporan = new Laporan($dari->toDateString(), $hariIni->toDateString());
        $r = $laporan->ringkasan();

        $stokMenipis = DB::table('stocks')
            ->whereRaw('(jumlah - jumlah_reserved) <= ?', [NotifikasiAdmin::BATAS_MENIPIS])
            ->count();

        return response()->json([
            'periode' => [
                'kode' => $periode,
                'dari' => $laporan->dari->toDateString(),
                'sampai' => $laporan->sampai->toDateString(),
            ],
            'penjualan' => Format::rupiah($r['penjualan']),
            'order_terbayar' => $r['jumlah'],
            'rata_per_order' => Format::rupiah($r['rata']),
            // Angka "sekarang" (tidak tergantung periode) untuk kartu yang perlu ditindaklanjuti.
            'perlu_diproses' => Order::where('status', Order::STATUS_DIBAYAR)->count(),
            'menunggu_bayar' => Order::where('status', Order::STATUS_MENUNGGU_PEMBAYARAN)->count(),
            'retur_baru' => ReturnRequest::where('status', ReturnRequest::STATUS_DIAJUKAN)->count(),
            'stok_menipis' => $stokMenipis,
            'grafik_harian' => $laporan->penjualanHarian()
                ->map(fn (float $nilai, string $tanggal) => ['tanggal' => $tanggal, 'penjualan_idr' => $nilai])
                ->values(),
            'produk_terlaris' => $laporan->produkTerlaris(5)
                ->map(fn (int $qty, string $nama) => ['nama' => $nama, 'qty' => $qty])
                ->values(),
        ]);
    }
}
