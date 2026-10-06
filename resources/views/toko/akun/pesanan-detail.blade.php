@extends('layouts.toko', ['judul' => $o['nomor']])

@section('isi')
    <div class="wrap halaman">
        <nav class="remah"><a href="{{ route('akun.pesanan') }}">{{ __('Orders') }}</a></nav>
        <div class="judul-order">
            <h1 class="judul-halaman">{{ $o['nomor'] }}</h1>
            @include('toko.akun._status', ['status' => $o['status'], 'label' => $o['label_status']])
        </div>
        <p class="redup">{{ __('Placed on :date', ['date' => $o['tanggal']]) }}</p>

        <div class="dua-kolom">
            <div>
                @if ($o['menunggu'])
                    @include('toko.akun._pembayaran', ['o' => $o, 'b' => $o['pembayaran']])
                @endif

                @if ($o['progres'])
                    @php($pg = $o['progres'])
                    <section class="blok blok-sorot progres" aria-label="{{ __('Order progress') }}">
                        <ol class="progres-langkah">
                            @foreach ($pg['langkah'] as $l)
                                <li class="langkah-{{ $l['keadaan'] }}" @if ($l['keadaan'] === 'sekarang') aria-current="step" @endif>
                                    <span class="titik" aria-hidden="true"></span>
                                    <span class="langkah-label">{{ $l['label'] }}</span>
                                    @if ($l['waktu'])<span class="langkah-waktu">{{ $l['waktu'] }}</span>@endif
                                </li>
                            @endforeach
                        </ol>
                        <h2>{{ $pg['judul'] }}</h2>
                        <p>{{ $pg['teks'] }}</p>
                        @if ($pg['resi'])
                            <p class="resi">
                                {{ __('Tracking number') }}: <strong data-salin-isi>{{ $pg['resi'] }}</strong>
                                <button type="button" class="tautan" data-salin data-t-tersalin="{{ __('Copied') }}">{{ __('Copy') }}</button>
                            </p>
                        @endif
                        @if ($pg['dibayar'])<p class="redup">{{ $pg['dibayar'] }}</p>@endif
                    </section>
                @endif

                @if ($o['sewa'])
                    @php($sw = $o['sewa'])
                    <section class="blok">
                        <h2>{{ __('Your rental') }}</h2>
                        <div class="barang">
                            <span class="barang-foto">@if ($sw['foto'])<img src="{{ $sw['foto'] }}" alt="" width="400" height="300" loading="lazy">@endif</span>
                            <div class="barang-info">
                                <span class="barang-nama">{{ $sw['nama'] }}</span>
                                <span class="barang-opsi">{{ $sw['mode'] }} · {{ $sw['durasi'] }}</span>
                            </div>
                        </div>
                        <dl class="ringkas-sewa">
                            <div><dt>{{ __('Pick-up') }}</dt><dd>{{ $sw['mulai'] }}</dd></div>
                            <div><dt>{{ __('Return') }}</dt><dd>{{ $sw['selesai'] }}</dd></div>
                            <div><dt>{{ __('Location') }}</dt><dd>{{ $sw['lokasi'] }}@if ($sw['alamat_lokasi'])<br><span class="redup">{{ $sw['alamat_lokasi'] }}</span>@endif @if ($sw['jam_lokasi'])<br><span class="redup">{{ __('Open :hours', ['hours' => $sw['jam_lokasi']]) }}</span>@endif @if ($sw['peta'])<br><a href="{{ $sw['peta'] }}" target="_blank" rel="noopener">{{ __('Open map') }}</a>@endif</dd></div>
                            @if ($sw['unit'])<div><dt>{{ __('Plate number') }}</dt><dd><strong>{{ $sw['unit'] }}</strong></dd></div>@endif
                            <div><dt>{{ __('Renter') }}</dt><dd>{{ $sw['penyewa'] }}</dd></div>
                            @if ($sw['catatan'])<div><dt>{{ __('Notes') }}</dt><dd>{{ $sw['catatan'] }}</dd></div>@endif
                        </dl>
                    </section>
                @else
                <section class="blok">
                    <h2>{{ __('Items') }}</h2>
                    <ul class="daftar-barang">
                        @foreach ($o['items'] as $i)
                            <li class="barang">
                                <span class="barang-foto">@if ($i['foto'])<img src="{{ $i['foto'] }}" alt="" width="400" height="400" loading="lazy">@endif</span>
                                <div class="barang-info">
                                    <span class="barang-nama">{{ $i['nama'] }}</span>
                                    @if ($i['opsi'])<span class="barang-opsi">{{ $i['opsi'] }}</span>@endif
                                    <span class="barang-harga">{{ $i['harga'] }}</span>
                                </div>
                                <div class="barang-aksi">
                                    <span class="barang-qty">× {{ $i['qty'] }}</span>
                                    <span class="barang-total">{{ $i['total'] }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
                @endif

                @if ($o['retur'])
                    @php($r = $o['retur'])
                    <section class="blok blok-sorot">
                        <h2>{{ $r['label'] }}</h2>
                        <p>{{ $r['penjelasan'] }}</p>
                        @if ($r['catatan_admin'])<p><strong>{{ __('Note from the store') }}:</strong> {{ $r['catatan_admin'] }}</p>@endif
                        @if ($r['status'] === 'disetujui')
                            @if ($r['alamat_retur'])<p><strong>{{ __('Send it to') }}:</strong><br>{!! nl2br(e($r['alamat_retur'])) !!}</p>@endif
                            <form method="post" action="{{ route('akun.retur.resi', $r['model']) }}" class="form form-baris">
                                @csrf @method('put')
                                @include('toko.partials.field', ['nama' => 'resi_kembali', 'label' => __('Return tracking number'), 'nilai' => $r['resi_kembali']])
                                <button type="submit" class="tombol tombol-kecil">{{ __('Save') }}</button>
                            </form>
                        @endif
                    </section>
                @endif

                @if ($o['bisa_retur'])
                    <details class="blok retur" @if ($errors->hasAny(['alasan', 'foto'])) open @endif>
                        <summary>{{ __('Item arrived damaged or wrong?') }}</summary>
                        <p class="redup">{{ __('Request a return within :days days of delivery. Returns are for defective or incorrect items only.', ['days' => $o['batas_retur_hari']]) }}</p>
                        <form method="post" action="{{ route('akun.pesanan.retur', $o['nomor']) }}" enctype="multipart/form-data" class="form" novalidate>
                            @csrf
                            @include('toko.partials.field', ['nama' => 'alasan', 'label' => __('What went wrong?'), 'tipe' => 'textarea', 'attr' => 'required'])
                            <div @class(['field', 'field-galat' => $errors->has('foto')])>
                                <label for="f-foto">{{ __('Photo of the problem') }}</label>
                                <input id="f-foto" type="file" name="foto" accept="image/*" required>
                                @error('foto')<p class="field-pesan">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="tombol tombol-kecil">{{ __('Request return') }}</button>
                        </form>
                    </details>
                @endif

                @unless ($o['sewa'])
                <section class="blok">
                    <h2>{{ __('Ship to') }}</h2>
                    <p>
                        <strong>{{ $o['alamat']['nama_penerima'] ?? '' }}</strong>, {{ $o['alamat']['telepon'] ?? '' }}<br>
                        {{ $o['alamat']['detail_alamat'] ?? '' }}<br>
                        {{ $o['alamat']['kota'] ?? '' }} {{ $o['alamat']['kode_pos'] ?? '' }}, {{ $o['nama_negara'] }}
                    </p>
                </section>
                @endunless
            </div>

            <aside class="ringkasan">
                @if ($o['riwayat'])
                    <ol class="riwayat">
                        @foreach ($o['riwayat'] as $h)
                            <li><span>{{ $h['label'] }}</span><span class="redup">{{ $h['waktu'] }}</span></li>
                        @endforeach
                    </ol>
                @endif
                <dl>
                    @unless ($o['sewa'])
                    <div><dt>{{ __('Subtotal') }}</dt><dd>{{ $o['subtotal'] }}</dd></div>
                    <div><dt>{{ __('Shipping (:kg kg)', ['kg' => $o['berat_kg']]) }}</dt><dd>{{ $o['ongkir'] }}</dd></div>
                    @endunless
                    <div class="ringkasan-total"><dt>{{ __('Total') }}</dt><dd>{{ $o['total'] }}</dd></div>
                </dl>
                <p class="catatan">{{ __('Prices locked in :currency when the order was placed.', ['currency' => $o['mata_uang']]) }}</p>

                @if ($o['menunggu'])
                    <form method="post" action="{{ route('akun.pesanan.batal', $o['nomor']) }}" onsubmit="return confirm(@js($o['sewa'] ? __('Cancel this booking?') : __('Cancel this order? The items go back on sale.')))">
                        @csrf
                        <button type="submit" class="tombol tombol-garis tombol-lebar">{{ $o['sewa'] ? __('Cancel booking') : __('Cancel order') }}</button>
                    </form>
                @endif
            </aside>
        </div>
    </div>
@endsection
