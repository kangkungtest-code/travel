<?php

namespace App\Actions\Akun;

use App\Actions\Order\BatalkanOrderAction;
use App\Exceptions\TokoException;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembeli menghapus akunnya sendiri.
 * Data pribadi di akun (nama, email, password, alamat tersimpan, login sosial, keranjang,
 * token, notifikasi) dihapus/dianonimkan. Baris user & pesanan lama tetap ada karena
 * catatan transaksi wajib disimpan untuk pembukuan (lihat Kebijakan Privasi).
 */
class HapusAkunAction
{
    public function __construct(private BatalkanOrderAction $batalkan) {}

    public function execute(User $user): void
    {
        if ($user->adalahAdmin()) {
            throw new TokoException(__('Staff accounts are removed from the admin panel.'));
        }

        $berjalan = $user->orders()->whereIn('status', [Order::STATUS_DIBAYAR, Order::STATUS_DIPROSES, Order::STATUS_DIKIRIM])->exists();
        $returBerjalan = ReturnRequest::query()
            ->whereIn('status', [ReturnRequest::STATUS_DIAJUKAN, ReturnRequest::STATUS_DISETUJUI])
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
        if ($berjalan || $returBerjalan) {
            throw new TokoException(__('You still have an order or return in progress. Your account can be deleted once it is completed.'));
        }

        // Pesanan yang belum dibayar dibatalkan supaya stoknya kembali dijual.
        $user->orders()->where('status', Order::STATUS_MENUNGGU_PEMBAYARAN)->get()
            ->each(fn (Order $o) => $this->batalkan->execute($o, $user, 'Akun dihapus pembeli'));

        DB::transaction(function () use ($user) {
            $user->addresses()->delete();
            $user->socialAccounts()->delete();
            $user->cart?->items()->delete();
            $user->cart?->delete();
            $user->tokens()->delete();
            $user->notifications()->delete();

            $user->forceFill([
                'nama_lengkap' => 'Akun dihapus',
                'email' => 'dihapus-'.$user->id.'@akun-dihapus.invalid',
                'password' => Str::random(64),
                'remember_token' => null,
                'email_verified_at' => null,
                'dihapus_pada' => now(),
            ])->save();
        });
    }
}
