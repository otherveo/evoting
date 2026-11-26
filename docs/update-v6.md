# Update v6 — TERIMPLEMENTASI + LIVE: Direktori paslon publik (landing page + halaman /paslon)

**Repo:** https://github.com/otherveo/evoting
**Live:** https://smpn7gtlo.myeclipse.my.id
**Commit:** 3871641 "feat: add public candidate directory and refine operator controls" (live 2026-11-23 — pull + deploy atas perintah Veo)
**Status file ini:** sudah dibangun Codex dan sudah live. File ini adalah catatan implementasi (bukan lagi draf prompt).
**File ini:** catatan, bukan coding. Deploy oleh Ryn (skema dulu, baru kode).
**Catatan verifikasi:** skema diterapkan ke Supabase live dalam 1 transaksi (111 statement, COMMIT OK) sebelum kode di-deploy; kolom baru `public_photo_visible` terverifikasi ada; data utuh (2 elections, 9 candidates); 4 pemeriksaan hak DEPLOY.md semua False; grant fungsi voting/staf semua True.

## Latar (keputusan Veo)

Veo bertanya 2026-11-22 malam apakah informasi paslon serta visi-misinya layak ditampilkan ke publik di landing page. Jawaban Ryn: worth it, dengan catatan — dan Veo menulis sendiri prompt-nya seperti di Bagian A.

Tujuannya: pemilih bisa membandingkan paslon dengan santai sebelum hari-H, bukan terburu-buru di dalam bilik yang cuma 10 menit. Ini normal untuk pemilu sekolah — sama seperti poster kampanye yang ditempel di dinding.

Alasan desain ini (kata Veo): daftar paslon TIDAK tampil lengkap sebagai kartu, karena perubahan kemarin (6e062bd) membuat ukuran card bervariasi, dan dikhawatirkan disalahpahami sebagai tendensius. Jadi yang tampil hanya tombol per paslon, dan detailnya baru muncul sebagai popup.

Prasyarat yang sudah dipenuhi sebelum dibangun: Veo memastikan pihak sekolah oke dengan foto paslon (anak SMP) yang tampil publik di halaman ini.

## Bagian A — Yang diminta Veo (prompt Veo, verbatim)

1. Ada tombol di landing page (paling bawah): "Lihat daftar paslon";
2. Halaman diarahkan ke /{id_pemilihan}/paslon;
3. Di sini daftar paslon ngga langsung tampil lengkap, karena perubahan kemarin membuat ukuran card-nya jadi bervariasi, dan takutnya disalahpahami sebagai sebagai suatu tendensius. Jadi tampilkan saja tombol "Lihat paslon ini";
4. Untuk detail dekorasi tombolnya itu:
- ada foto paslon 1:1 (operator can hide, fallback to "01" for example if is hidden or not exist)
- ada (contoh) "Paslon Nomor 01" di atas tombolnya
- lihat contoh kode dan gambar
5. Nanti detail paslon-nya akan muncul sebagai popup, lengkap dengan visi & misi yang tampil utuh (un-collapse-able)

## Bagian B — Yang dibangun Codex (commit 3871641)

### Rute dan halaman

- Tombol "Lihat daftar paslon" di paling bawah landing page (`/`, `landing.blade.php`) mengarah ke `/paslon` (route `public.candidates.index`).
- `GET /paslon` → `PublicCandidatesController@index`. Kalau hanya ada SATU pemilihan yang terdaftar dan tanpa param `?pilih=1`, langsung redirect ke `/{election}/paslon`. Kalau ada lebih dari satu (atau `?pilih=1`), tampil halaman pemilih pemilihan ("Pilih pemilihan"). Kalau tidak ada sama sekali: "Belum ada daftar paslon dari pemilihan yang terjadwal."
- `GET /{election}/paslon` → `PublicCandidatesController@show` (UUID pemilihan). Menampilkan daftar paslon pemilihan itu sebagai kartu dalam grid — satu kartu per paslon, bukan kartu lengkap berisi visi-misi.
- Halaman ini publik (tanpa login), di bawah throttle `throttle:public-access`.

### Pemilihan mana yang terdaftar (definisi yang dipakai Codex)

`listedElections()` di controller — berbeda dari usulan draf (fase Persiapan/Berlangsung per `currentPhase()`):
- `starts_at` dan `ends_at` tidak null (pemilihan sudah terjadwal);
- nama TIDAK cocok pola Training/Simulasi/Tes/Latihan (regex, case-insensitive) — pemilihan latihan/tes tidak masuk direktori publik;
- punya paslon (`whereHas('candidates')`).
- Tidak ada filter fase: pemilihan yang sudah `Ditutup` tetap bisa terdaftar kalau memenuhi syarat di atas. Ini keputusan implementasi Codex (draf membolehkan Codex menyesuaikan dengan pola yang sudah ada).

### Kartu per paslon

- Setiap paslon tampil sebagai satu kartu dengan label `Paslon Nomor {{ number }}` (h2) di atas tombol.
- Tombol "Lihat paslon ini" berisi thumbnail 1:1 (persegi): foto paslon kalau ada dan terlihat, kalau tidak ada/disembunyikan/gagal dimuat maka yang tampil nomor urut (contoh "01"). Foto disembunyikan otomatis kalau gagal dimuat (`x-on:error`).
- Klik tombol membuka popup dialog (`candidate-vision-dialog`) berisi detail paslon: nomor urut, nama, foto, visi, dan daftar misi — tampil utuh (un-collapse-able, tidak bisa dilipat).
- Urutan: nomor urut menaik. Semua kartu tampil setara; tidak ada yang ditonjolkan.

### "Operator can hide" — terjawab: kolom baru + saklar operator

- Skema: kolom baru `public_photo_visible boolean not null default true` di `evoting.candidates` (di `CREATE TABLE` dan `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`). Default `true` — paslon yang sudah ada tetap terlihat fotonya.
- Controller: foto ditampilkan hanya kalau `photo_path` ada DAN `public_photo_visible` true; kalau tidak, `photo` = null dan yang tampil nomor urut.
- Operator: di halaman detail pemilihan, setiap paslon yang punya foto mendapat saklar (role="switch") untuk menampilkan/menyembunyikan foto dari daftar publik — method `setCandidatePublicPhoto()` di `ElectionDetailPage`, dalam transaksi, dengan audit log action `ubah_visibilitas_foto_paslon` dan toast ("Foto ditampilkan di daftar publik." / "Foto disembunyikan dari daftar publik."). Saklar tidak muncul kalau paslon tidak punya foto.

### Yang TIDAK tampil (sesuai draf)

- Tidak ada perolehan suara / hasil — halaman ini tidak query tabel `votes` sama sekali, dan tidak menampilkan angka suara, persentase, progress bar, atau peringkat.
- Tidak ada data pemilih — tidak query `voter_rights`, `device_access_logs`, atau `audit_logs`. Halaman ini hanya membaca `elections` + `candidates`.
- Tidak ada tautan ke bilik / QR / hasil rekap. Hasil tetap hanya di rekap operator.

### Gerbang visi-misi di bilik — DIHAPUS setelah v6 (fac2b91, live 2026-11-23)

KOREKSI 2026-11-23: bagian ini sudah tidak berlaku. Commit fac2b91 ("Hapus keharusan lihat visi-misi di sesi pemilihan", live 2026-11-23 15:34 WITA atas perintah Veo, deploy pull + clear cache) menghapus gerbang baca visi-misi v5 — `BallotPage::confirmVote` tidak lagi menerima `readCandidateIds` dan tidak lagi menolak pemilih yang belum membaca seluruh visi-misi ("Anda belum boleh lanjut sebelum membaca seluruh visi & misi setiap paslon."). Pemilih kini bisa langsung memilih tanpa membaca visi-misi di bilik; visi-misi tetap bisa dibaca di halaman paslon publik ini. Ini membalikkan keputusan v5 yang dulu mengunci gerbang tersebut.

## Bagian C — Perubahan skema (satu-satunya perubahan skema di v6)

- `evoting.candidates` + kolom `public_photo_visible boolean not null default true`.
- Diterapkan ke Supabase live SEBELUM kode di-deploy (aturan: skema dulu, baru kode), dalam 1 transaksi via `apply-schema-hardening.py` (111 statement, COMMIT OK).
- Tidak ada migrasi lain, tidak ada fungsi database baru, tidak ada perubahan fungsi yang sudah ada.

## Bagian D — Batasan teknis (standing constraints, dipenuhi)

- Tanpa perubahan file Vite — CSS/JS baru di `public/css/` / `public/js/`; deploy tetap pull + clear cache tanpa `npm run build` (tidak ada file `resources/css/` atau `resources/js/` yang berubah di commit ini).
- Tanpa mengubah perilaku halaman lain — landing page yang sudah ada tetap seperti sekarang, hanya tombol baru di paling bawah.
- Tanpa menonaktifkan SSL verification, tanpa migrate di production, tanpa regenerate APP_KEY.
- Branch: dikerjakan Codex di branch fitur, merge ke `main` (commit 3871641), Ryn pull + deploy.

## Verifikasi deploy (2026-11-23)

- Skema: COMMIT OK (111 statement); kolom `public_photo_visible` ada (boolean, default true); data utuh (2 elections, 9 candidates); 4 pemeriksaan hak DEPLOY.md semua False; grant fungsi voting/staf semua True.
- Kode: `main` 6e062bd → 3871641 (fast-forward), cache clear (config/view/route/event/cache).
- Publik: homepage 200, /up 200, `/paslon` 302 → redirect ke `/{election_uuid}/paslon` 200 (direktori paslon tampil).
- Catatan perbaikan skrip deploy: `apply-schema-hardening.py` diperbaiki dua bug saat deploy ini — (1) `fetchone()` pg8000 versi baru mengembalikan list bukan tuple (assert diubah ke `tuple(row)`), (2) signature `fn_confirm_vote` di cek verifikasi salah `(uuid,uuid,text,text,text,text)` — yang benar `(text,text,uuid,text,inet)` sesuai `schema.sql` (sudah begitu sejak hardening 1073087).
