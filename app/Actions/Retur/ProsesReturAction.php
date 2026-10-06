<?php

namespace App\Actions\Retur;

use App\Exceptions\TokoException;
use App\Models\ReturnRequest;
use App\Notifications\ReturDiperbarui;
use Illuminate\Support\Facades\DB;

/**
 * Alur retur:
 *   diajukan -> disetujui (admin) -> [pembeli isi resi kembali] -> selesai (admin terima barang)
 *   diajukan -> ditolak (admin, wajib alasan)
 * Refund otomatis lewat API gateway menyusul di Fase 4; sekarang penyelesaian dicatat
 * (refund / ganti_barang) dan refund dilakukan manual.
 */
class ProsesReturAction
{
    public function setujui(ReturnRequest $r, ?string $catatan = null): ReturnRequest
    {
        return $this->ubah($r, ReturnRequest::STATUS_DIAJUKAN, ReturnRequest::STATUS_DISETUJUI, ['catatan_admin' => $catatan]);
    }

    public function tolak(ReturnRequest $r, string $catatan): ReturnRequest
    {
        if (trim($catatan) === '') {
            throw new TokoException(__('Give the buyer a reason when declining.'));
        }

        return $this->ubah($r, ReturnRequest::STATUS_DIAJUKAN, ReturnRequest::STATUS_DITOLAK, ['catatan_admin' => $catatan]);
    }

    public function selesaikan(ReturnRequest $r, string $penyelesaian, ?string $catatan = null): ReturnRequest
    {
        if (! in_array($penyelesaian, ReturnRequest::PENYELESAIAN, true)) {
            throw new TokoException(__('Choose refund or replacement.'));
        }

        return $this->ubah($r, ReturnRequest::STATUS_DISETUJUI, ReturnRequest::STATUS_SELESAI, array_filter([
            'penyelesaian' => $penyelesaian,
            'catatan_admin' => $catatan,
        ], fn ($v) => $v !== null));
    }

    /** Pembeli mengisi nomor resi pengiriman balik. */
    public function isiResiKembali(ReturnRequest $r, string $resi): ReturnRequest
    {
        if ($r->status !== ReturnRequest::STATUS_DISETUJUI) {
            throw new TokoException(__('The tracking number can be added after the return is approved.'));
        }

        $r->update(['resi_kembali' => trim($resi)]);
        \App\Support\NotifikasiAdmin::resiReturDiisi($r);

        return $r;
    }

    private function ubah(ReturnRequest $r, string $dari, string $ke, array $atribut): ReturnRequest
    {
        $r = DB::transaction(function () use ($r, $dari, $ke, $atribut) {
            $r = ReturnRequest::query()->lockForUpdate()->findOrFail($r->id);
            if ($r->status !== $dari) {
                throw new TokoException(__('This return has already been processed.'));
            }
            $r->update(['status' => $ke] + $atribut);

            return $r;
        });

        $r->order->user?->notify(new ReturDiperbarui($r));

        return $r;
    }
}
