// Storefront: interaksi kecil tanpa framework.
(() => {
    // Ganti bahasa / mata uang / urutan langsung saat dipilih.
    document.querySelectorAll('form[data-auto-submit] select, select[data-auto-submit-field]').forEach((el) => {
        el.addEventListener('change', () => el.form.submit());
    });

    // Carousel foto (kartu katalog & halaman produk): geser dengan jari (scroll-snap),
    // titik penanda, panah, dan putar otomatis saat terlihat di layar.
    const kurangiGerak = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const pasangGeser = (root) => {
        const jalur = root.querySelector('[data-geser-jalur]');
        const titik = root.querySelector('[data-geser-titik]');
        let sekarang = 0;
        let timer = null;
        let jeda = false;

        const slides = () => [...jalur.querySelectorAll('.geser-slide')].filter((s) => !s.hidden);
        const ke = (i, halus = true) => {
            const s = slides();
            if (!s.length) return;
            sekarang = (i + s.length) % s.length;
            jalur.scrollTo({ left: s[sekarang].offsetLeft - jalur.offsetLeft, behavior: halus && !kurangiGerak ? 'smooth' : 'auto' });
            tandai();
        };
        const tandai = () => {
            const s = slides();
            if (titik) [...titik.children].forEach((d, i) => d.classList.toggle('aktif', i === sekarang));
            root.classList.toggle('geser-satu', s.length <= 1);
            root.dispatchEvent(new CustomEvent('geser:ganti', { detail: { slide: s[sekarang] } }));
        };
        const susun = () => {
            if (titik) titik.replaceChildren(...slides().map(() => document.createElement('span')));
            sekarang = 0;
            jalur.scrollTo({ left: 0 });
            tandai();
        };

        let tunda;
        jalur.addEventListener('scroll', () => {
            clearTimeout(tunda);
            tunda = setTimeout(() => {
                const lebar = jalur.clientWidth || 1;
                const i = Math.round(jalur.scrollLeft / lebar);
                if (i !== sekarang) { sekarang = Math.min(i, slides().length - 1); tandai(); }
            }, 60);
        }, { passive: true });

        root.querySelector('[data-geser-sebelum]')?.addEventListener('click', (e) => { e.preventDefault(); ke(sekarang - 1); });
        root.querySelector('[data-geser-berikut]')?.addEventListener('click', (e) => { e.preventDefault(); ke(sekarang + 1); });
        root.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') ke(sekarang - 1);
            if (e.key === 'ArrowRight') ke(sekarang + 1);
        });

        const otomatis = Number(root.dataset.otomatis || 0);
        if (otomatis && !kurangiGerak && 'IntersectionObserver' in window) {
            const jalan = () => { if (!timer) timer = setInterval(() => { if (!jeda && slides().length > 1) ke(sekarang + 1); }, otomatis); };
            const henti = () => { clearInterval(timer); timer = null; };
            new IntersectionObserver(([e]) => (e.isIntersecting ? jalan() : henti()), { threshold: 0.6 }).observe(root);
            ['pointerenter', 'touchstart', 'focusin'].forEach((ev) => root.addEventListener(ev, () => { jeda = true; }, { passive: true }));
            ['pointerleave', 'focusout'].forEach((ev) => root.addEventListener(ev, () => { jeda = false; }));
        }

        root.geser = { ke, susun, slides, get sekarang() { return sekarang; } };
        susun();
    };
    document.querySelectorAll('[data-geser]').forEach(pasangGeser);

    // Thumbnail halaman produk mengikuti & mengendalikan carousel.
    document.querySelectorAll('[data-galeri]').forEach((galeri) => {
        const geser = galeri.querySelector('[data-galeri-geser]');
        if (!geser) return;
        galeri.querySelectorAll('[data-thumb]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = geser.geser.slides().findIndex((s) => s.dataset.i === btn.dataset.thumb);
                if (target >= 0) geser.geser.ke(target);
            });
        });
        geser.addEventListener('geser:ganti', (e) => {
            const i = e.detail.slide?.dataset.i;
            galeri.querySelectorAll('[data-thumb]').forEach((b) => {
                if (b.dataset.thumb === i) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current');
            });
        });
    });

    // Pemilih varian: cocokkan opsi terpilih ke varian, update harga/stok/SKU.
    document.querySelectorAll('[data-pemilih]').forEach((root) => {
        const varian = JSON.parse(root.querySelector('[data-varian]').textContent);
        const harga = root.querySelector('[data-harga]');
        const stok = root.querySelector('[data-stok]');
        const sku = root.querySelector('[data-sku]');
        const beli = root.querySelector('[data-tombol-beli]');
        const radios = [...root.querySelectorAll('input[type="radio"][name^="opsi["]')];
        const kunci = (r) => r.name.slice(5, -1);

        const terpilih = () => {
            const pilih = {};
            radios.filter((r) => r.checked).forEach((r) => { pilih[kunci(r)] = r.value; });
            return pilih;
        };
        const cocok = (v, pilih) => Object.entries(pilih).every(([k, n]) => v.opsi[k] === n);

        const render = () => {
            const pilih = terpilih();
            const v = varian.find((x) => cocok(x, pilih));

            root.querySelectorAll('[data-terpilih]').forEach((el) => {
                const r = radios.find((x) => x.checked && kunci(x) === el.dataset.terpilih);
                el.textContent = r ? r.dataset.label : '';
            });

            // Tandai pilihan yang habis, dengan opsi lain tetap seperti yang dipilih.
            radios.forEach((r) => {
                const coba = { ...pilih, [kunci(r)]: r.value };
                const ada = varian.some((x) => cocok(x, coba) && x.stok > 0);
                r.closest('.chip').classList.toggle('kosong-stok', !ada);
            });

            stok.classList.remove('habis');
            if (beli) beli.disabled = !v || v.stok <= 0;
            if (!v) {
                stok.textContent = stok.dataset.tTidakAda;
                stok.classList.add('habis');
                sku.textContent = '';
                return;
            }
            harga.textContent = v.harga;
            sku.textContent = v.sku;
            if (v.stok <= 0) {
                stok.textContent = stok.dataset.tHabis;
                stok.classList.add('habis');
            } else if (v.stok <= 5) {
                stok.textContent = stok.dataset.tSisa.replace(':count', v.stok);
            } else {
                stok.textContent = stok.dataset.tAda;
            }
        };

        // Foto per warna: tampilkan foto warna terpilih (+ foto umum), foto utama = foto pertama warna itu.
        const galeri = document.querySelector('[data-galeri]');
        const gantiFoto = () => {
            if (!galeri) return;
            const warna = terpilih()[galeri.dataset.opsiWarna];
            // Foto milik warna lain disembunyikan (thumbnail & slide); foto tanpa warna selalu tampil.
            const item = [...galeri.querySelectorAll('.galeri-thumb li, .geser-slide')];
            const adaFotoWarna = warna && item.some((el) => el.dataset.warna === warna);
            item.forEach((el) => {
                el.hidden = adaFotoWarna && el.dataset.warna !== undefined && el.dataset.warna !== warna;
            });
            const daftar = galeri.querySelector('.galeri-thumb');
            if (daftar) daftar.hidden = [...daftar.children].filter((li) => !li.hidden).length <= 1;
            galeri.querySelector('[data-galeri-geser]')?.geser?.susun();
        };

        radios.forEach((r) => r.addEventListener('change', () => {
            render();
            if (galeri && kunci(r) === galeri.dataset.opsiWarna) gantiFoto();
        }));
        render();
        gantiFoto();
    });
})();

// Chatbot: jawaban otomatis dari server, tombol WA/LINE kalau perlu admin.
(() => {
    const root = document.querySelector('[data-chat]');
    if (!root) return;

    const buka = root.querySelector('[data-chat-buka]');
    const panel = root.querySelector('.chat-panel');
    const isi = root.querySelector('[data-chat-isi]');
    const saran = root.querySelector('[data-chat-saran]');
    const form = root.querySelector('[data-chat-form]');
    const input = form.querySelector('input');
    const csrf = document.querySelector('form [name="_token"]')?.value;
    let dimulai = false;

    const tautkan = (teks) => {
        const frag = document.createDocumentFragment();
        teks.split(/(https?:\/\/\S+)/g).forEach((bagian, i) => {
            if (i % 2) {
                const a = document.createElement('a');
                a.href = bagian; a.textContent = bagian; a.target = '_blank'; a.rel = 'noopener';
                frag.append(a);
            } else {
                frag.append(bagian);
            }
        });
        return frag;
    };

    const pesan = (teks, dari, kontak = []) => {
        const el = document.createElement('div');
        el.className = `chat-pesan chat-${dari}`;
        const p = document.createElement('p');
        p.append(tautkan(teks));
        el.append(p);
        if (kontak.length) {
            const k = document.createElement('div');
            k.className = 'chat-kontak';
            kontak.forEach((c) => {
                const a = document.createElement('a');
                a.href = c.url; a.target = '_blank'; a.rel = 'noopener';
                a.className = `tombol tombol-kecil chat-${c.jenis}`;
                a.textContent = c.label;
                k.append(a);
            });
            el.append(k);
        }
        isi.append(el);
        isi.scrollTop = isi.scrollHeight;
    };

    const tampilkanSaran = (daftar) => {
        saran.replaceChildren(...daftar.map((t) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'chip-saran'; b.textContent = t;
            b.addEventListener('click', () => kirim(t));
            return b;
        }));
    };

    const minta = async (metode, badan) => {
        const res = await fetch(root.dataset.url, {
            method: metode,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf || '' },
            body: badan ? JSON.stringify(badan) : undefined,
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(res.status);
        return res.json();
    };

    const mulai = async () => {
        if (dimulai) return;
        dimulai = true;
        try {
            const d = await minta('GET');
            pesan(d.teks, 'bot');
            tampilkanSaran(d.saran);
        } catch {
            pesan(root.dataset.tGalat, 'bot');
            dimulai = false;
        }
    };

    const kirim = async (teks) => {
        teks = teks.trim();
        if (!teks) return;
        pesan(teks, 'saya');
        input.value = '';
        try {
            const d = await minta('POST', { pesan: teks });
            pesan(d.teks, 'bot', d.kontak);
        } catch {
            pesan(root.dataset.tGalat, 'bot');
        }
    };

    const setBuka = (terbuka) => {
        panel.hidden = !terbuka;
        buka.setAttribute('aria-expanded', String(terbuka));
        root.classList.toggle('terbuka', terbuka);
        if (terbuka) { mulai(); input.focus(); } else { buka.focus(); }
    };

    buka.addEventListener('click', () => setBuka(panel.hidden));
    root.querySelector('[data-chat-tutup]').addEventListener('click', () => setBuka(false));
    panel.addEventListener('keydown', (e) => { if (e.key === 'Escape') setBuka(false); });
    form.addEventListener('submit', (e) => { e.preventDefault(); kirim(input.value); });
})();

// Pembayaran: salin nomor VA & cek status otomatis sampai pembayaran masuk.
(() => {
    document.querySelectorAll('[data-salin]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const isi = btn.parentElement.querySelector('[data-salin-isi]').textContent.trim();
            try { await navigator.clipboard.writeText(isi); btn.textContent = btn.dataset.tTersalin; } catch { /* abaikan */ }
        });
    });

    const blok = document.querySelector('[data-cek-status]');
    if (!blok) return;
    const awal = blok.dataset.statusAwal;
    let percobaan = 0;
    const cek = async () => {
        percobaan += 1;
        try {
            const res = await fetch(blok.dataset.cekStatus, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (res.ok && (await res.json()).status !== awal) { window.location.reload(); return; }
        } catch { /* coba lagi nanti */ }
        if (percobaan < 120) setTimeout(cek, percobaan < 30 ? 5000 : 15000);
    };
    setTimeout(cek, 5000);
})();

// Pop-up masuk/daftar: tautan [data-masuk] membuka dialog; tanpa JS tetap ke /masuk.
(() => {
    const dialog = document.querySelector('[data-dialog-masuk]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const pilihTab = (tab) => {
        dialog.querySelectorAll('[data-tab]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === tab)));
        dialog.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== tab; });
        const pertama = dialog.querySelector(`[data-panel="${tab}"] input:not([type=hidden])`);
        if (pertama) setTimeout(() => pertama.focus(), 30);
    };
    const buka = (tab, kembali) => {
        const tujuan = kembali || location.pathname + location.search;
        dialog.querySelectorAll('[data-kembali]').forEach((i) => { i.value = tujuan; });
        dialog.querySelectorAll('[data-sosial]').forEach((a) => {
            const u = new URL(a.href, location.origin);
            u.searchParams.set('kembali', tujuan);
            a.href = u.toString();
        });
        if (!dialog.open) dialog.showModal();
        pilihTab(tab);
    };

    document.addEventListener('click', (e) => {
        const a = e.target.closest('[data-masuk]');
        if (!a) return;
        e.preventDefault();
        buka(a.dataset.tab || 'masuk', a.dataset.kembali);
    });
    dialog.querySelectorAll('[data-tab]').forEach((b) => b.addEventListener('click', () => pilihTab(b.dataset.tab)));
    dialog.querySelector('[data-tutup]').addEventListener('click', () => dialog.close());
    // Klik di luar kotak menutup pop-up.
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

    if (dialog.dataset.bukaAwal) buka(dialog.dataset.bukaAwal);
})();
