<?php

namespace App\Http\Controllers\Toko;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\ShippingZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AlamatController extends Controller
{
    public function create(Request $request): View
    {
        return view('toko.akun.alamat-form', [
            'alamat' => new Address(['negara' => ShippingZone::negaraTersedia()[0] ?? 'ID', 'nama_penerima' => $request->user()->nama_lengkap]),
            'negara' => $this->negara(),
            'kembali' => $this->kembali($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validasi($request);

        DB::transaction(function () use ($user, $data) {
            $pertama = $user->addresses()->doesntExist();
            $alamat = $user->addresses()->create($data + ['is_default' => $pertama]);
            if (! $pertama && ($data['is_default'] ?? false)) {
                $this->jadikanUtama($alamat);
            }
        });

        return redirect($this->kembali($request))->with('status', __('Address saved.'));
    }

    public function edit(Request $request, Address $alamat): View
    {
        $this->pastikanMilik($request, $alamat);

        return view('toko.akun.alamat-form', ['alamat' => $alamat, 'negara' => $this->negara(), 'kembali' => $this->kembali($request)]);
    }

    public function update(Request $request, Address $alamat): RedirectResponse
    {
        $this->pastikanMilik($request, $alamat);
        $data = $this->validasi($request);

        DB::transaction(function () use ($alamat, $data) {
            $alamat->update(collect($data)->except('is_default')->all());
            if ($data['is_default'] ?? false) {
                $this->jadikanUtama($alamat);
            }
        });

        return redirect($this->kembali($request))->with('status', __('Address saved.'));
    }

    public function destroy(Request $request, Address $alamat): RedirectResponse
    {
        $this->pastikanMilik($request, $alamat);

        DB::transaction(function () use ($alamat) {
            $wasDefault = $alamat->is_default;
            $user = $alamat->user;
            $alamat->delete();

            if ($wasDefault && ($pengganti = $user->addresses()->oldest()->first())) {
                $pengganti->update(['is_default' => true]);
            }
        });

        return back()->with('status', __('Address deleted.'));
    }

    private function jadikanUtama(Address $alamat): void
    {
        Address::query()->where('user_id', $alamat->user_id)->whereKeyNot($alamat->id)->update(['is_default' => false]);
        $alamat->update(['is_default' => true]);
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'nama_penerima' => ['required', 'string', 'max:120'],
            'telepon' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'negara' => ['required', Rule::in(ShippingZone::negaraTersedia())],
            'kota' => ['required', 'string', 'max:100'],
            'kode_pos' => ['required', 'string', 'max:20'],
            'detail_alamat' => ['required', 'string', 'max:500'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }

    /** @return array<string, string> */
    private function negara(): array
    {
        return collect(ShippingZone::negaraTersedia())
            ->mapWithKeys(fn (string $k) => [$k => __(config("toko.negara.{$k}", $k))])
            ->all();
    }

    private function kembali(Request $request): string
    {
        return $request->input('kembali', $request->query('kembali')) === 'checkout' ? route('checkout') : route('akun');
    }

    private function pastikanMilik(Request $request, Address $alamat): void
    {
        abort_unless($alamat->user_id === $request->user()->id, 404);
    }
}
