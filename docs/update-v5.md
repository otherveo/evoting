# Prompt Update v5 — Kartu paslon (tombol eksplisit + gate baca visi/misi) + rekap operator (kunci + animasi reveal) + desentralisasi CSS

**Repo:** https://github.com/otherveo/evoting
**Live:** https://smpn7gtlo.myeclipse.my.id
**Commit acuan:** 9dfe1e1 (update-v4 sudah live)
**Dasar:** perilaku kartu pemilih saat ini (`stage-memilih.blade.php`, `BallotPage::selectCandidate`/`proceedToConfirm`), halaman rekap operator (`ElectionRecapPage`, route `/elections/{election}/recap`), dan rencana desentralisasi CSS (`css-refactor-plan.md`).
**File ini:** hanya prompt, bukan coding. Setelah push, Veo yang memberitahu Ryn, Ryn yang pull + deploy.
**Lampiran:** `count-up.html` (contoh konsep animasi count-up — perhatikan: TANPA progress bar saat menghitung), `css-refactor-plan.md` (rencana desentralisasi CSS). `reference.png` SENGAJA TIDAK dilampirkan — keadaan akhir daftar peringkat dideskripsikan dengan kata-kata di Bagian B8 untuk menghindari bias desain.

## Prinsip update ini

**Seluruh kebijakan privasi tampilan hasil DILEPAS.** Akses ke hasil/rekapitulasi diatur sepenuhnya oleh operator — bukan lagi oleh aturan privasi bawaan (mis. hasil yang selama ini disembunyikan dari semua orang, termasuk operator, sebelum Waktu Selesai). Efek konkret prinsip ini dalam update ini hanya yang tertulis di Bagian B (kunci halaman rekap diganti jadi kunci operator). Yang TIDAK diubah dalam update ini: anonimitas data suara (tabel `votes` tetap tanpa tautan ke pemilih — aturan data, bukan tampilan), halaman publik (`/p/...` turnout + hasil), dan halaman live-recap access key (`/pantau-rekap/{election}`).

## Batasan yang dikunci (berlaku untuk semua bagian)

- Jangan pernah menonaktifkan verifikasi SSL.
- Koneksi database lewat session pooler 5432, user `evoting_app.<ref>`, schema `evoting`. JANGAN pakai transaction pooler 6543, JANGAN `php artisan migrate` di production.
- JANGAN pernah regenerate APP_KEY server (rahasia TOTP admin terenkripsi dengan kunci itu).
- TOTP hanya untuk Admin, bukan Operator. Sesi pemilih 10 menit + bisa lanjut di browser yang sama.
- Referensi FR-0nn hanya di komentar kode, BUKAN teks di UI.
- Setiap tombol mengunci SEGERA saat ditekan (disabled + kursor not-allowed + perubahan warna) dan TIDAK bisa ditekan lagi kecuali aksi GAGAL; setiap tombol menampilkan popup hasil yang ringkas (1–2 kata).
- Setelah push, Veo yang memberitahu Ryn, Ryn yang pull + deploy. Jangan sentuh file `.env` server.
- Perubahan skema (kolom/tabel baru) ditulis ke `schema.sql` DAN diterapkan langsung ke Supabase live oleh Argon via koneksi langsung (psql/script, user `postgres`, session pooler 5432) — JANGAN `php artisan migrate`.

---

## Bagian A — Kartu paslon: tombol eksplisit + popup visi/misi + gate baca

Kondisi saat ini (terverifikasi dari kode, commit 9dfe1e1): kartu adalah elemen `<button class="candidate-card">` dengan `x-on:click="chooseCandidate(...)"` — klik di area mana pun = memilih. Visi (`candidate-vision`) dan misi (`mission-list`) tampil langsung di dalam kartu. Tombol "Lanjut ke Konfirmasi" (`#btn-to-confirm`, `x-on:click="goToConfirm()"`) berstatus disabled saat belum ada paslon yang dipilih (`x-bind:disabled="!selectedCand"`). `BallotPage::proceedToConfirm()` menolak bila belum ada yang dipilih dengan toast "Pilih satu pasangan calon terlebih dahulu."

### Perubahan

1. **Tombol eksplisit "Pilih Paslon Ini"** di tiap kartu. Menekan tombol ini = memilih paslon itu (sama seperti klik kartu). **Behavior lama TIDAK dihilangkan** — klik kartu di area mana pun tetap memilih.
2. **Visi & misi disembunyikan dari kartu.** Tambahkan tombol **"Lihat Visi & Misi"** di tiap kartu. Menekan tombol itu membuka **popup (modal)** yang menampilkan visi + daftar misi paslon tersebut. Gunakan komponen modal yang sudah ada (`x-modal`).
3. **Restrukturisasi kartu (wajib):** kartu saat ini adalah elemen `<button>` — TIDAK BOLEH ada `<button>` di dalam `<button>` (HTML tidak valid). Ubah kartu menjadi elemen non-button (mis. `<div role="radio">`) dengan dukungan keyboard/ARIA yang setara (fokus, Enter/Space = memilih, `aria-checked` tetap dijaga), supaya kedua tombol di dalamnya valid. Klik tombol di dalam kartu **TIDAK BOLEH** memicu pilih (hentikan propagasi); klik "Lihat Visi & Misi" tidak memilih paslon.
4. **Status dibaca per paslon:** tombol "Lihat Visi & Misi" berubah warna dengan JELAS antara belum ditekan vs sudah ditekan (mis. netral/abu-abu → hijau/centang). Menekan tombol itu (membuka popup) = menandai paslon tersebut sudah dibaca.
5. **Gate "Lanjut ke Konfirmasi":** tombol ini **TETAP BISA DITEKAN** untuk gate ini — JANGAN dibuat unclickable/disabled karena visi/misi belum dibaca (perilaku disabled yang sudah ada saat belum ada paslon yang dipilih TETAP seperti sekarang). Saat ditekan:
   - Jika ada SATU SAJA paslon yang tombol "Lihat Visi & Misi"-nya belum ditekan → **TOLAK**: tampilkan pesan persis **"Anda belum boleh lanjut sebelum membaca seluruh visi & misi setiap paslon."** (popup/toast), tetap di halaman memilih, tidak pindah ke konfirmasi.
   - Jika SEMUA paslon sudah dibaca → lanjut ke konfirmasi seperti sekarang.
   - Bilik membuka visi dan misi, menandai setiap paslon yang dibuka, dan menampilkan konfirmasi secara lokal. Saat pemilih mengirim suara, server memeriksa bahwa daftar ID yang dilaporkan mencakup semua paslon pada pemilihan tersebut.
6. **CSS perubahan ini** mengikuti Bagian C (file mandiri di `public/css/`, BUKAN `suarasekolah.css`).

---

## Bagian B — Rekapitulasi per paslon: kunci operator + animasi reveal

Kondisi saat ini (terverifikasi dari kode, commit 9dfe1e1): `ElectionRecapPage::render()` hanya memuat hasil bila `$phase === 'ditutup'` (via `$voting->electionResults()`); bila fase belum Ditutup, halaman menampilkan kartu terkunci "Rekapitulasi Per Paslon Belum Tersedia" dengan penjelasan hasil tidak ditampilkan kepada siapa pun (termasuk Operator) sebelum Waktu Selesai. Halaman ini adalah "Halaman Rekapitulasi Per Paslon" yang dimaksud (route `/elections/{election}/recap`, judul "REKAPITULASI & PEMERIKSAAN INTEGRITAS OPERATOR").

### B.1 Kunci halaman (menggantikan gate fase)

1. **Default TERKUNCI.** Ganti gate fase (`$phase === 'ditutup'`) dengan kunci operator berikut. Syarat membuka halaman: **SELURUH kuota hak suara sudah terpakai** — yaitu jumlah pemilih berstatus Sudah memilih (sama dengan total suara yang masuk) == batas maksimum hak pilih (`max_voter_rights`), mis. 300/300. **Mengakhiri pemilihan adalah shortcut:** bila fase sudah Ditutup (termasuk "akhiri lebih awal"), syarat dianggap terpenuhi dan halaman bisa dibuka — halaman rekap **TIDAK BOLEH sampai terkunci permanen** karena hak yang tidak terpakai.
2. **Buka/tutup bebas oleh operator.** Setelah syarat terpenuhi, operator bisa membuka dan menutup halaman ini secara bebas (toggle, mis. tombol "Buka Rekapitulasi" / "Tutup Rekapitulasi"). State buka/tutup **tersimpan** (persisten di database) dan terlihat sama oleh semua operator. Saat halaman tertutup: tampilkan kartu terkunci + tombol buka; bila syarat belum terpenuhi, kartu terkunci menjelaskan syaratnya (mis. "X / 300 suara terpakai — atau akhiri pemilihan untuk membuka").
3. **State dan konfigurasi reveal harus persisten** (kolom baru di `elections` dan/atau tabel baru — Argon yang merancang; tulis ke `schema.sql` + terapkan langsung ke Supabase live sesuai Batasan di atas). Semua operator yang membuka halaman yang sama melihat state yang sama; konfigurasi reveal di-refresh (poll) supaya konsisten.

### B.2 Mode tampilan

Operator memilih mode tampilan halaman rekap (pilihan tersimpan per pemilihan):

- **Biasa** — tampil seperti sekarang (tabel rekap `x-results-breakdown`: perolehan per paslon, total suara, status integritas).
- **Animasi (reveal)** — mengikuti B.3–B.5 di bawah.

### B.3 Konfigurasi reveal (mode animasi)

1. **Urutan tampil:** operator memilih paslon mana yang ditampilkan hasil suaranya terlebih dahulu, dengan mengklik paslon sesuai urutan yang diinginkan; atau menekan tombol **"Acak Urutan Tampil"** agar sistem mengurutkannya secara acak. Urutan yang dipilih terlihat dan bisa diubah sebelum reveal dimulai.
2. **Kunci konfigurasi:** setelah operator menekan mulai, konfigurasi (urutan + mode + delay) **terkunci** — operator lain tidak bisa mengubahnya di tengah proses reveal. Tombol **Reset** (seperti pada contoh) mengembalikan ke konfigurasi dan membuka kunci.
3. **Mode reveal (pilihan operator):** **satu per satu paslon** atau **langsung semua paslon**.
4. **Delay:** operator mengatur delay dalam ms — **min 20 ms, maks 10000 ms (10 detik)** — dengan validasi seperti pada contoh (tolak nilai di luar rentang dengan pesan yang jelas). Delay adalah **total durasi animasi hitung-naik untuk SATU paslon** (bukan jeda per angka).
5. **Tombol mulai:** tombol **"Mulai Animasi"** memulai reveal; tombol **"Reset"** mengembalikannya ke konfigurasi (dan membuka kunci).

### B.4 Animasi — mode satu per satu (inti, dikerjakan dulu)

1. Tiap paslon ditampilkan **satu per satu** di layar (panggung/stage), sesuai urutan reveal yang sudah dipilih/diacak.
2. Angka suara **menghitung naik dari 0 ke n** (n = jumlah total perolehan suara paslon itu) selama delay yang diatur operator. Selama menghitung, yang tampil **HANYA angka yang terus naik — TANPA progress bar** (berbeda dari contoh `count-up.html`, yang bar hijaunya terisi sambil menghitung).
3. **Setelah hitungan berhenti**, card paslon itu **beranimasi bergerak ke posisinya di daftar peringkat** (area hasil di pojok kiri). Bilah (bar) proporsional baru muncul di baris peringkat itu setelah angka berhenti — bila bilah ternyata tidak relevan dengan desain akhir, boleh ditiadakan; angka adalah elemen wajib.
4. **Urutan reveal BUKAN urutan peringkat akhir.** Daftar peringkat selalu tersusun berdasarkan suara terbanyak di atas. Bila paslon yang baru dihitung punya suara lebih banyak dari yang sedang teratas, paslon lain **beranimasi turun ke bawah**, digeser oleh paslon baru.
5. Paslon dengan 0 suara langsung tampil 0 tanpa animasi hitung.
6. Progres reveal **tersimpan**: bila halaman di-reload di tengah reveal, lanjutkan dari posisi terakhir (paslon yang sudah ditampilkan tetap di daftar peringkat), bukan mengulang dari awal.

### B.5 Animasi — mode langsung semua paslon

Semua paslon menghitung naik **bersamaan** (masing-masing dengan durasi delay yang sama), lalu semua card beranimasi ke posisi peringkatnya **sekaligus**. Aturan lainnya sama dengan B.4 (tanpa progress bar saat menghitung; bilah hanya setelah angka berhenti; peringkat akhir berdasarkan suara terbanyak).

### B.6 Keadaan akhir daftar peringkat (deskripsi kata-kata — JANGAN lampirkan gambar)

Setelah semua paslon ditampilkan, layar menunjukkan daftar peringkat dengan, per baris: **nomor peringkat** (1, 2, 3, ...), **nama paslon**, badge **"TERATAS"** pada peringkat 1, **jumlah suara** (format ribuan id-ID, mis. 2.259), **persentase** dari total suara, dan **bilah proporsional** (lebar ∝ pangsa suara; muncul setelah angka berhenti; boleh ditiadakan bila tidak relevan). Baris peringkat 1 ditonjolkan secara visual (mis. aksen/border berbeda). **Tanpa** panah perubahan peringkat (tidak ada peringkat sebelumnya di v1).

### B.7 Data

- Saat halaman terbuka, hasil dimuat **live** (fungsi hasil live — bukan lagi hanya saat fase Ditutup). Angka yang dianimasikan adalah angka riil dari tabel suara anonim.
- Persentase = suara paslon / total suara × 100 (1 desimal).
- **Data reveal diambil SEKALI** saat reveal dimulai (satu kali ambil: hasil per paslon + total suara). Seluruh animasi setelahnya murni kode statis sisi klien yang memakai data yang sudah ada — tidak ada fetch ulang per paslon atau per tick.

### B.8 Kontrol kecepatan selama reveal

1. Selama reveal berjalan, halaman yang sedang menampilkan reveal menyediakan **kontrol kecepatan** (mis. pilihan 1x / 2x / 4x, default 1x) untuk mempercepat sisa animasi — antisipasi penonton bosan bila delay diset panjang (mis. 1 detik per paslon).
2. Kontrol ini **hanya ada di halaman yang sedang menampilkan reveal** (kontrol lokal): operator lain yang membuka halaman yang sama melihat progres reveal yang sama, tapi tidak bisa mengubah kecepatannya.
3. Mengubah kecepatan hanya mengubah tempo animasi sisi klien (sisa hitung-naik + gerakan card dibagi multiplier); delay yang dikonfigurasi TIDAK berubah, tidak ada fetch ulang data, dan hasil akhir tetap sama.
4. Berlaku untuk kedua mode (satu-per-satu dan semua-sekaligus).

---

## Bagian C — Desentralisasi CSS (Opsi A: tetap di `public/css/`)

Keputusan lokasi (diputuskan Veo 2026-11-18 20:22): **Opsi A — file pecahan tetap di `public/css/`**. (Perbandingan lengkap + cara migrasi ke Vite nanti ada di lampiran `css-refactor-plan.md`.) Aturan migrasi bertahap (sudah diputuskan Veo):

1. **Perubahan dalam update ini adalah langkah migrasi pertama:** semua style BARU/berubah di update ini (tombol kartu, popup visi/misi, kunci rekap, reveal) masuk ke **file CSS mandiri** di `public/css/` dengan struktur yang mengikuti `resources/views/` (mis. `public/css/voter/ballot.css`, `public/css/operator/recap-reveal.css`) — **JANGAN** menambah ke `suarasekolah.css`.
2. **Mekanisme load per halaman:** tambahkan `@stack('styles')` di kedua layout (`app` + `voter`) dan `@push('styles')` berisi `<link>` di tiap blade yang butuh file CSS-nya. File halaman dimuat **SETELAH** `suarasekolah.css` supaya urutan cascade tetap.
3. **Cache-busting otomatis per file:** jangan pakai banyak nomor `?v=` manual — gunakan versi dari waktu modifikasi file (mis. `asset('css/...') . '?v=' . filemtime(public_path('css/...'))`).
4. **Ekstraksi tanpa ubah styling:** class yang dikeluarkan dari `suarasekolah.css` ke file mandiri dipindah apa adanya (TANPA mengubah styling-nya, kecuali perubahannya memang butuh perubahan styling); hanya class yang **TIDAK digunakan di tempat lain** yang boleh dikeluarkan (verifikasi dengan grep ke seluruh `resources/views/`); class yang dipakai banyak blade ("shared class") tetap di tempatnya.
5. **Bukan big-bang:** TIDAK menyebarkan seluruh `suarasekolah.css` (6.737 baris) ke file-file lain sekarang. Detail latar belakang + opsi Vite ada di lampiran `css-refactor-plan.md`.

---

## Daftar Periksa Penerimaan

1. Kartu paslon: klik di area mana pun tetap memilih (behavior lama), DAN tombol "Pilih Paslon Ini" memilih paslon yang sama.
2. Visi & misi TIDAK lagi tampil di kartu; tombol "Lihat Visi & Misi" membuka popup berisi visi + daftar misi paslon itu; popup tidak memilih paslon dan tidak bertabrakan dengan klik kartu.
3. Struktur kartu valid (tidak ada `<button>` di dalam `<button>`); keyboard/ARIA (fokus, Enter/Space, `aria-checked`) tetap bekerja seperti sebelumnya.
4. Tombol "Lihat Visi & Misi" berubah warna dengan jelas antara belum vs sudah ditekan, per paslon.
5. "Lanjut ke Konfirmasi" tetap bisa ditekan (tidak dibuat unclickable untuk gate ini); bila ada paslon yang belum dibaca → tolak dengan pesan persis "Anda belum boleh lanjut sebelum membaca seluruh visi & misi setiap paslon." dan tetap di halaman memilih; bila semua sudah dibaca → lanjut ke konfirmasi.
6. Browser mensyaratkan semua visi/misi dibuka sebelum pratinjau. Server memvalidasi kelengkapan daftar terhadap paslon terdaftar saat submit akhir.
7. Halaman rekap default terkunci; bila syarat (suara masuk == max_voter_rights, atau fase Ditutup sebagai shortcut) belum terpenuhi, kartu terkunci menjelaskan syaratnya (X / maks suara terpakai); bila sudah terpenuhi, operator bisa membuka halaman. Mengakhiri pemilihan (termasuk akhiri lebih awal) selalu membuka kunci — halaman tidak boleh terkunci permanen.
8. Setelah dibuka, operator bisa menutup dan membuka kembali halaman rekap secara bebas; state buka/tutup persisten dan sama untuk semua operator.
9. Mode tampilan (biasa vs animasi) bisa dipilih operator dan tersimpan; mode biasa tampil seperti sekarang.
10. Konfigurasi reveal: urutan tampil bisa dipilih manual atau lewat "Acak Urutan Tampil"; mode satu-per-satu vs semua; delay 20–10000 ms tervalidasi; setelah mulai, konfigurasi terkunci bagi operator lain; Reset membuka kunci dan kembali ke konfigurasi.
11. Mode satu-per-satu: tiap paslon menghitung naik 0 → n selama delay yang diatur, HANYA angka tanpa progress bar; setelah berhenti, card beranimasi ke posisi peringkatnya; peringkat selalu suara terbanyak di atas; paslon baru yang lebih tinggi menggeser yang lain turun.
12. Mode semua-sekaligus: semua paslon menghitung bersamaan, lalu semua card beranimasi ke posisi peringkatnya sekaligus.
13. Keadaan akhir: daftar peringkat berisi nomor, nama, badge "TERATAS" di peringkat 1, jumlah suara (format ribuan id-ID), persentase, dan bilah proporsional (boleh ditiadakan bila tidak relevan); tanpa panah perubahan peringkat.
14. Angka yang dianimasikan adalah angka riil; persentase = suara/total × 100 (1 desimal); paslon 0 suara langsung tampil 0; reload di tengah reveal melanjutkan dari posisi terakhir.
15. Perubahan skema (state kunci/buka, mode, konfigurasi + progres reveal) ada di `schema.sql` DAN sudah diterapkan ke Supabase live; tidak ada `php artisan migrate` di production.
16. Semua style baru/berubah di update ini ada di file CSS mandiri di `public/css/` (bukan di `suarasekolah.css`); `@stack('styles')`/`@push` bekerja; tidak ada regresi tampilan pada halaman yang tidak disentuh.
17. Tidak ada regresi: alur memilih → konfirmasi → selesai tetap jalan; halaman publik dan live-recap access key tidak berubah.
18. Selama reveal, halaman yang menampilkannya menyediakan kontrol kecepatan (1x/2x/4x, default 1x) yang mempercepat sisa animasi tanpa mengubah delay yang dikonfigurasi dan tanpa fetch ulang data; kontrol ini hanya ada di halaman yang sedang menampilkan reveal.
19. Data reveal diambil sekali saat mulai; animasi sepenuhnya sisi klien dari data yang sudah dimuat (tidak ada fetch ulang per paslon atau per tick).
