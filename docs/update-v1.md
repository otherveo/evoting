# Update v1 — Prompt untuk Argon

**Tujuan file ini:** daftar tugas perbaikan + perubahan untuk aplikasi E-Voting, berdasarkan hasil tes langsung Veo di situs live. Kerjakan semua item di bawah, uji di lokal, lalu push. Deploy ke server dilakukan Ryn setelah Veo menyetujui.

**Konteks:**
- Repo: `otherveo/evoting` (branch `main`).
- Situs live: `https://smpn7gtlo.myeclipse.my.id` — kode yang dites Veo adalah commit `3013688` (landing page + eye toggle).
- Screenshot acuan (dikirim Veo bersama file ini): `transaksi-ganda.jpeg`, `mobile-config_pemilhan.png`, `desktop-config_pemilhan.png`, `image-28.png`.
- Akun tes: admin (`admin`) dan operator (`spentu-operator-testing01`). Data tes yang sudah ada: pemilihan "Pilketis Testing" (terduplikasi akibat Bug 1), 7 hak pilih terdaftar.

## Aturan yang tetap berlaku (jangan dilanggar)

- **Jangan pernah mematikan verifikasi SSL** (tidak ada `Http::withoutVerifying()`, `CURLOPT_SSL_VERIFYPEER=false`, atau `'verify' => false`). Error cURL 60 di laptop diselesaikan dengan CA bundle, bukan dengan mematikan verifikasi.
- Database tetap via **Supavisor session pooler port 5432**, role `evoting_app`, schema `evoting`. Jangan pakai transaction pooler (6543) untuk migrasi/DDL.
- **Jangan jalankan `php artisan migrate` di production.** Skema dikelola lewat `schema.sql` (Argon yang pegang file ini).
- **Jangan regenerate `APP_KEY` server.** Secret TOTP admin terenkripsi dengan key itu; ganti key = TOTP admin rusak (sudah kejadian 2026-11-18, diperbaiki lewat reset TOTP).
- TOTP hanya untuk Admin, bukan Operator (lihat `AMANDEMEN-PRD.md`).
- Sesi pemilih tetap 10 menit dan bisa dilanjutkan di browser yang sama.
- Referensi `FR-0nn` hanya boleh jadi komentar kode, bukan teks di UI.
- Setelah push, beri tahu Veo; Ryn yang pull + deploy. Jangan ubah file `.env` server.

---

## BUG 1 — Transaksi ganda: tombol masih bisa ditekan berkali-kali saat request berjalan

**Bukti:** di daftar pemilihan ada DUA kartu "Pilketis Testing" yang identik (lihat `transaksi-ganda.jpeg`). Satu klik "Buat Pemilihan" menghasilkan dua pemilihan karena tombol tetap clickable selama request pertama belum selesai — spam request diterima server dan diproses semua.

**Lokasi kode:** `app/Livewire/Operator/ElectionIndexPage.php::createElection()` (baris ~41) + modal buat pemilihan di `resources/views/livewire/operator/election-index-page.blade.php`. Saat ini tidak ada pengaman double-submit; `Election::create()` langsung jalan tiap request masuk.

**Perbaikan yang diminta (berlaku untuk SEMUA button di aplikasi, bukan cuma tombol ini):**

1. **Begitu sebuah button ditekan, LANGSUNG DIKUNCI.** Button berubah jadi tidak bisa diklik (disabled), cursor jadi `not-allowed`, dan warnanya berubah (tampak nonaktif). Kuncian ini berlaku sejak milidetik pertama setelah klik, bukan setelah response kembali.
2. **Button TIDAK PERNAH kembali jadi clickable, kecuali aksinya GAGAL.** Kalau aksi berhasil, button tetap terkunci (atau halaman berpindah / modal tertutup). Hanya kalau aksi gagal (error) button boleh aktif lagi supaya operator bisa mencoba ulang.
3. **SETIAP button harus menampilkan popup hasil aksinya** — singkat, 1–2 kata saja (contoh: "Berhasil", "Gagal", "Tersimpan"). Jangan menampilkan paragraf panjang.
4. **Pengaman di sisi server (wajib):** kuncian client saja tidak cukup, karena dua request tetap bisa lolos bersamaan. Tambahkan pengaman supaya satu aksi yang sama tidak bisa menciptakan data ganda — misalnya token sekali pakai per submit, atau penanda idempotency di request pembuatan pemilihan. Kriteria: klik tombol buat pemilihan 10x secara spam (atau kirim request yang sama berulang) hasilnya TETAP 1 pemilihan, bukan 10.

**Cara verifikasi:** buat pemilihan baru sambil spam-klik tombol simpan; pastikan hanya 1 pemilihan yang terbentuk di daftar dan di database. Ulangi untuk tombol simpan lain (paslon, jadwal, pengaturan).

---

## BUG 2 — Ganti nama pemilihan selalu gagal tanpa alasan jelas

**Bukti:** mengubah nama pemilihan (di halaman konfigurasi, section Identitas) selalu menampilkan `"Gagal menyimpan pengaturan pemilihan. Silakan coba kembali."` — tanpa alasan apa pun. Terjadi di semua skenario: pemilihan baru, pemilihan yang sudah ada jadwal, dan pemilihan yang sudah dimulai.

**Lokasi kode:** `app/Livewire/Operator/ElectionDetailPage.php::saveElectionInfo()` (baris ~89–130). Method ini membungkus semuanya dalam `try/catch` dan melempar semua exception ke `reportSafeError($e, 'Gagal menyimpan pengaturan pemilihan. Silakan coba kembali.', 'name')`. Pesan mentah exception-nya SUDAH dicatat di log server oleh `reportSafeError` (`app/Livewire/Concerns/HandlesSafeErrors.php`), jadi penyebab aslinya bisa dibaca di `storage/logs/laravel-YYYY-MM-DD.log` di server — jangan menebak, baca log itu.

**Perbaikan yang diminta:**

1. Reproduksi dulu di lokal (atau minta log server ke Ryn), baca exception aslinya, dan perbaiki **akar masalahnya** — bukan cuma mengganti pesan error.
2. **Periksa kemungkinan ini saat diagnosis:** apakah nama sebenarnya TERSIMPAN walau pesan error muncul (kemungkinan `update()` berhasil tapi ada langkah sesudahnya yang throw — misalnya audit log atau `syncElectionFields()`)? Kalau iya, perbaiki alurnya supaya tidak ada simpan-separuh.
3. Pesan error ke operator harus **menyebutkan penyebab spesifiknya** (contoh: validasi apa yang gagal, atau kendala apa yang terjadi), bukan satu kalimat generik untuk semua kegagalan. Tetap log mentahnya di server seperti sekarang.
4. Verifikasi di ketiga skenario: (a) pemilihan baru, (b) pemilihan yang sudah ada jadwal, (c) pemilihan yang sudah dimulai. Ketiganya harus bisa ganti nama (selama masih diizinkan fasenya).

---

## PERUBAHAN 1 — Batas hak pilih: minimal 3, maksimal 10000

**Saat ini:** batas maksimum hak pilih minimal 1, maksimal 100000 (`ElectionIndexPage::createElection` — `'min:1', 'max:100000'`; `ElectionDetailPage::saveElectionInfo` — `min: max(1, jumlah_hak_yang_sudah_dibuat)`, `max:100000`).

**Yang diminta:** minimal **3**, maksimal **10000**. Berlaku di dua tempat:

- Form buat pemilihan baru (`ElectionIndexPage`).
- Form pengaturan pemilihan (`ElectionDetailPage::saveElectionInfo`) — di sini batas bawah tetap harus menghormati hak yang sudah dibuat: `min = max(3, jumlah_hak_pilih_yang_sudah_ada)`. Jadi kalau sudah ada 7 hak pilih, batas bawahnya 7, bukan 3.
- Perbarui juga pesan validasinya (contoh: "Batas maksimum hak pilih minimal 3 dan maksimal 10000.").
- Catatan: CHECK constraint di database (`max_voter_rights >= 1`) tidak menghalangi aturan baru ini, jadi tidak perlu ubah skema — cukup validasi aplikasi. Kalau Argon mau menegakkannya juga di database, itu perubahan `schema.sql` (ikuti alur schema yang sudah disepakati).

---

## PERUBAHAN 2 — Visual hierarchy halaman konfigurasi + alur sekuensial saat pembuatan

### 2.1 Tukar posisi section Paslon dan Tautan (tampilan umum)

Saat ini di `resources/views/livewire/operator/election-detail-page.blade.php` urutannya: Bagian 1 Identitas + Kuota + Jadwal, **Bagian 2 Tautan Halaman Publik**, Bagian 3 Pasangan Calon. Ini salah secara hierarki: paslon WAJIB diisi sebelum jadwal bisa disimpan (jadwal menolak disimpan kalau paslon belum minimal 2), tapi section paslon malah di paling bawah.

**Yang diminta:** tukar posisi Bagian 2 dan Bagian 3 — urutan baru: **Identitas & Hak Pilih → Pasangan Calon → Jadwal Pemilihan → Tautan Halaman Publik**. Tautan publik selalu paling bawah (di mobile juga tetap di paling bawah).

### 2.2 Alur sekuensial (wizard) saat pembuatan pemilihan

**Masalah saat ini:** setelah buat pemilihan, operator langsung dilempar ke halaman konfigurasi yang banjir fitur — membingungkan.

**Yang diminta:** setelah operator menekan "Buat Pemilihan", JANGAN lempar ke halaman konfigurasi. Tampilkan **satu linear flow popup** (wizard) untuk melengkapi data, langkah demi langkah:

1. **Langkah 1 — Identitas:** input nama pemilihan & jumlah hak suara (batas 3–10000, lihat Perubahan 1).
2. **Langkah 2 — Data paslon:** langsung tampilkan 1 card berisi input field paslon pertama (nama, nomor, foto, visi, misi — pakai field form paslon yang sudah ada). Ada tombol **"+ Tambah Paslon"** untuk membuat card baru bagi paslon kedua (dan seterusnya). Tombol **"Next" DILARANG aktif kalau paslon belum minimal 2** — validasi ini di client dulu.
3. **Langkah 3 — Jadwal:** input waktu mulai dan selesai. Sediakan tombol **"Mulai sekarang"** yang opsional — operator harus bisa **menyimpan konfigurasi TANPA memulai pemilihan saat itu juga**. Kalau tombol "Mulai sekarang" ditekan langsung, tampilkan **popup konfirmasi tambahan** sebelum pemilihan benar-benar dimulai.
4. **Langkah 4 — Tautan:** tampilkan tautan halaman publik (jumlah pemilih + hasil) setelah pemilihan berhasil dibuat.

**Aturan penting wizard ini:**

- **Semua langkah dipegang di client dulu, JANGAN langsung live ke server.** Tidak ada yang ditulis ke database sampai wizard selesai dan dikonfirmasi di akhir. **Semua interupsi di tengah proses membatalkan semuanya** — kalau operator menutup wizard di tengah jalan, tidak ada data yang tersimpan (pemilihan tidak terbentuk).
- Di bagian atas popup ada tombol **"Saya ingin melengkapinya nanti"** untuk keluar dari mode sekuensial ini (keluar = batalkan wizard, kembali ke daftar pemilihan, tidak ada yang tersimpan).
- **Tampilan sekuensial hanya muncul sekali, saat pembuatan.** Kalau operator membuka lagi halaman konfigurasi pemilihan yang sudah ada, tampil halaman konfigurasi biasa (bukan wizard).

### 2.3 Nomor urut di setiap section card

Supaya urutannya jelas bagi operator, **setiap section card diberi nomor dengan border bulat di pojok kiri atas** (contoh: ① ② ③). Berlaku untuk langkah-langkah wizard DAN untuk section di halaman konfigurasi biasa (Identitas = 1, Paslon = 2, Jadwal = 3, Tautan = 4).

### 2.4 Section jadi dropdown

Lihat `mobile-config_pemilhan.png` dan `desktop-config_pemilhan.png`: tampilannya sudah cukup bagus dibuat jadi **dropdown/collapsible** (section bisa dibuka-tutup, seperti section "Jadwal Pemilihan" yang terbuka dengan chevron di screenshot desktop). Terapkan pola itu ke semua section halaman konfigurasi. Catatan: di mobile, tautan publik tetap pindah ke bawah (lihat 2.1).

---

## PERUBAHAN 3 — Indikator perangkat bersama + persetujuan operator sebelum memilih

### 3.1 Indikator perangkat bersama yang lebih jelas

**Masalah saat ini:** kalau perangkat yang sama dipakai 2 pemilih, tabel pantau hanya menampilkan label "Peringatan Perangkat Bersama" (lihat `image-28.png`: dua pemilih, Nayla dan Lindawaty Nono, sama-sama "Chrome Android · #80884E", sama-sama berlabel peringatan). Tidak ada indikator jelas **perangkat itu berelasi dengan pemilih yang mana** — hashtag perangkat (`#80884E`) sudah menjelaskan perangkatnya, tapi sebagai indikator relasi antar pemilih fungsinya kurang.

**Lokasi kode:** `app/Livewire/Operator/VoterMonitorPage.php` (~baris 357) + `resources/views/livewire/operator/partials/monitor-table.blade.php` (~baris 148) + `resources/views/livewire/operator/partials/monitor-modals.blade.php` (~baris 216).

**Yang diminta:** di tabel pantau (dan di modal detail), pada setiap pemilih yang terkena peringatan, tampilkan **daftar pemilih lain yang memakai perangkat yang sama** — nama + ID hak pilih mereka (contoh: "Perangkat ini juga dipakai oleh: Lindawaty Nono (#18A7203)"). Baris-baris yang berbagi satu perangkat harus terlihat berkelompok/terhubung secara visual (misalnya badge grup perangkat yang sama dengan hashtag perangkatnya). Kriteria: operator bisa tahu sekilas siapa berbagi perangkat dengan siapa, tanpa harus membuka detail satu per satu.

### 3.2 Gerbang persetujuan operator (fitur baru)

**Latar:** sesuai instruksi Veo sebelumnya, perangkat yang sama harus **diizinkan operator dulu** untuk bisa memilih. Saat ini mekanisme itu belum ada sama sekali (tidak ada kode persetujuan di alur voter).

**Alur yang diminta:**

1. Pemilih menekan tombol mulai memilih (`app/Livewire/Voter/BallotPage.php::startVoting()` → `VotingService::startVoting()`). Statusnya berubah jadi `sedang_memilih` seperti sekarang, **TAPI** tampilan pemilih langsung dikunci oleh popup **"Menunggu persetujuan operator"** yang **tidak bisa ditutup** (unclosable) sampai ada keputusan operator.
2. Di halaman operator (halaman pantau, `VoterMonitorPage`, yang sudah me-refresh tiap 3 detik), muncul **popup perizinan secara real-time** begitu ada pemilih yang menunggu persetujuan — berisi nama pemilih, perangkat yang dipakai, dan waktu permintaan — dengan tombol **Setujui** dan **Tolak**.
3. Sisi publik (pemilih) boleh dibuat salah satu dari dua cara ini (pilih yang paling andal, tidak perlu keduanya):
   - **(a) Real-time:** kunci terbuka otomatis begitu operator menyetujui (lewat polling yang sudah ada).
   - **(b) Tombol "Cek izin" (kuning):** pemilih menekan tombol untuk mengecek status persetujuan. Setelah ditekan, tombol berubah: loading dulu, lalu **hijau "Diizinkan"** (kalau disetujui — kunci terbuka, pemilih lanjut memilih); **kuning "belum diizinkan"** (kalau operator belum merespons — pemilih tetap menunggu); **merah "tidak diizinkan"** (kalau ditolak — lalu redirect ke halaman dengan teks persis ini: "Aksesmu telah ditolak oleh operator, silakan minta QR baru jika kamu memang punya hak suara").
4. **Kalau ditolak:** sesi pemilih itu diakhiri dan token/QR-nya tidak berlaku lagi — pemilih harus minta QR baru ke operator (operator bisa terbitkan ulang dari halaman pantau). Tulis keputusan setuju/tolak ke audit log (actor operator).
5. **Batas waktu tunggu:** masa tunggu persetujuan mengikuti sesi memilih 10 menit yang sudah ada. Kalau sesi kedaluwarsa sebelum operator merespons, sesi berakhir dan pemilih kembali ke halaman awal (status kembali seperti sebelum mulai).

---

## PERUBAHAN 4 — Branding: ganti jadi DigiVoting

**Yang diminta:** brand aplikasi yang terlihat pengguna diganti dari "E-Voting" menjadi **DigiVoting** — judul halaman, header/brand, landing page, halaman login, dan semua teks UI yang menyebut nama aplikasi.

**Yang TIDAK diubah:** semua identitas teknis yang sudah terlanjur jadi (dan memang tidak terlihat pengguna) dibiarkan saja — nama schema database `evoting`, nama role `evoting_app`, nama tabel/kolom, route, config key, dan nama file (termasuk `suarasekolah.css` kalau tidak perlu). Jangan ubah struktur database atau route demi branding ini.

**Perhatian:** nama aplikasi dipakai sebagai issuer TOTP (`TotpService::getProvisioningUri`, dari `config('app.name')`). Mengganti `APP_NAME` mengubah label entri authenticator yang dibuat SESUDAH pergantian — secret-nya tetap sama dan kode TOTP lama tetap berlaku, jadi login admin tidak akan rusak. Meski begitu, setelah ganti brand, verifikasi login admin masih berfungsi (Veo akan tes; laporkan kalau ada masalah).

---

## PERUBAHAN 5 — Landing page: alur "betapa mudahnya memakai aplikasi ini"

**Saat ini:** landing page (`resources/views/landing.blade.php`) hanya halaman branding satu layar.

**Yang diminta:** tambahkan section alur cara pakai yang menjelaskan betapa mudahnya aplikasi ini digunakan, **lengkap untuk dua sisi**:

- **Sisi operator:** buat pemilihan → tambah paslon → atur jadwal → bagikan tautan/QR → pantau dan lihat rekap.
- **Sisi anonim/pemilih:** buka tautan atau scan QR → verifikasi → mulai memilih → konfirmasi pilihan → selesai.

Buat alurnya visual dan ringkas (langkah bernomor, sejalan dengan gaya landing yang sudah ada). Jangan mengubah identitas visual landing yang sudah disetujui.

---

## PERUBAHAN 6 — Fitur hapus pemilihan

**Latar:** Veo sudah membuat banyak pemilihan untuk testing sehingga daftarnya belepotan — perlu bisa dihapus.

**Syarat:** pemilihan hanya boleh dihapus kalau fasenya **Persiapan (belum pernah dimulai)** atau **Ditutup (sudah berakhir)**. **DILARANG hapus saat Berlangsung.** (Catatan: "Persiapan" diizinkan supaya pemilihan-pemilihan tes yang tidak pernah dimulai bisa dibersihkan — kalau Veo maunya ketat hanya "Ditutup", dia yang akan bilang.)

**Yang diminta:**

1. Tambahkan tombol hapus pemilihan di area operator (daftar pemilihan dan/atau halaman konfigurasi), dengan **popup konfirmasi yang mewajibkan operator mengetik nama pemilihan** sebelum penghapusan dijalankan. Penghapusan bersifat permanen dan tidak bisa dibatalkan — tulis itu jelas di popup.
2. Saat dihapus, **hapus semua yang berelasi** supaya tidak ada data nyangkut yang bikin error: kandidat + foto paslon di bucket `paslon-foto`, hak pilih, suara (votes), log akses perangkat, dan sesi pemilih yang masih aktif.
3. **Cabut semua sesi/token yang sedang aktif** untuk pemilihan itu — token/QR yang sudah terbit tidak boleh bisa dipakai lagi, dan pemilih yang sedang berada di alur memilih untuk pemilihan itu harus gagal dengan aman (pesan ramah, tidak ada suara yang tercatat).
4. **Validasi ketat:** endpoint/aksi hapus harus menolak kalau fasenya Berlangsung (cek fase di server, bukan cuma sembunyikan tombol). Setelah terhapus, tautan publik pemilihan itu harus menunjukkan "pemilihan tidak ditemukan", dan tidak ada voting yang bisa dilakukan untuk pemilihan yang sudah tidak valid.
5. **⚠️ Konflik skema yang HARUS diperhatikan sebelum implementasi:** skema saat ini MELARANG penghapusan di `votes` dan `audit_logs` — ada trigger `fn_forbid_change` (BEFORE UPDATE OR DELETE, SQLSTATE `EV001`) di kedua tabel itu, dan foreign key dari tabel-tabel terkait. Artinya `DELETE` biasa pada suara akan ditolak trigger. Jangan akali dengan mematikan trigger secara diam-diam. Rancang jalur hapusnya dengan benar (misalnya fungsi SQL khusus milik pemilik tabel, atau perubahan skema yang diusulkan dulu ke Veo), dan sampaikan rancangannya ke Veo SEBELUM implementasi kalau menyentuh trigger/skema.
6. **`audit_logs` TIDAK ikut dihapus** — itu jejak audit yang memang dirancang tidak bisa diubah/dihapus. Baris audit yang merujuk ke pemilihan yang sudah dihapus tetap tersimpan sebagai catatan historis.

---

## PERUBAHAN 7 — Access key untuk rekap real-time (membuka hasil sebelum waktunya)

**Latar:** ini sengaja mendobrak kebijakan privasi yang selama ini berlaku (hasil baru dibuka setelah Waktu Selesai) — karena klien memintanya: kepala sekolah (atau orang berwenang lain) ingin menonton progres masing-masing paslon secara real-time, misalnya ditampilkan di dewan guru.

**Yang diminta:**

1. Operator bisa menerbitkan **HANYA 1 (satu) access_key aktif per pemilihan**. Menerbitkan kunci baru otomatis mencabut kunci lama.
2. Format kunci: string **64 karakter, tanpa spasi**, berisi huruf + angka + simbol. Kunci dibuat di server dengan generator acak yang aman (CSPRNG), bukan dari input operator.
3. **Penyimpanan:** kunci disimpan dalam bentuk **hash** (tidak pernah plaintext di database). Kunci asli hanya ditampilkan **SATU KALI** saat diterbitkan (seperti token sekali lihat) — sesudah itu tidak bisa dilihat lagi, hanya bisa dicabut atau diterbitkan ulang.
4. **Cara akses (diserahkan ke Argon, dengan syarat keamanan):** sediakan URL khusus dengan **path yang berbeda dari halaman publik** (bukan `/publik/...` yang biasa) yang hanya bisa dibuka dengan access_key tersebut. Syarat keamanan: kunci tidak bisa ditebak (64 karakter acak), tidak tercatat di log, tidak terindeks mesin pencari (noindex), tidak ada tautan ke halaman ini dari halaman publik mana pun, dan akses dibatasi rate-limit. Halaman ini read-only.
5. **Isi halaman:** rekap real-time per paslon (jumlah suara + progres masing-masing paslon), diperbarui otomatis seperti halaman pantau operator. **Tidak ada identitas pemilih** — yang dibuka hanya soal WAKTU hasil (hasil terlihat sebelum selesai), bukan soal siapa memilih siapa. Suara tetap anonim.
6. **Peringatan keras (wajib):** di halaman rekap ini tampilkan banner **berwarna merah** yang tegas, kira-kira berbunyi: "PERINGATAN: Halaman ini menampilkan hasil sementara secara real-time. Buka halaman ini TERPISAH dari area pemilihan — jangan tampilkan di tempat pemilih dapat melihatnya, demi menjaga asas demokrasi yang bebas." Banner harus selalu terlihat selama halaman dibuka.
7. Sediakan kontrol di area operator untuk melihat status kunci (aktif/tidak), mencabutnya, dan menerbitkan ulang.

---

## Checklist penerimaan (sebelum push)

- [ ] Bug 1: spam-klik tombol buat pemilihan 10x → tepat 1 pemilihan terbentuk. Semua button mengunci saat ditekan dan menampilkan popup singkat (1–2 kata).
- [ ] Bug 2: ganti nama pemilihan berhasil di 3 skenario (baru / ada jadwal / sudah dimulai); pesan error menyebut penyebab spesifik, bukan generik.
- [ ] Perubahan 1: batas hak pilih 3–10000 di form buat dan form pengaturan (termasuk aturan `max(3, sudah_ada)` di pengaturan).
- [ ] Perubahan 2: urutan section Identitas → Paslon → Jadwal → Tautan; wizard sekuensial muncul sekali saat pembuatan, client-side dulu, batal = tidak ada yang tersimpan; tombol "Saya ingin melengkapinya nanti" ada; section bernomor di pojok kiri atas; section jadi dropdown; tautan publik di paling bawah (mobile juga).
- [ ] Perubahan 3: di tabel pantau terlihat jelas siapa berbagi perangkat dengan siapa; alur persetujuan operator jalan end-to-end (pemilih terkunci → operator menyetujui/menolak → pemilih lanjut atau redirect penolakan dengan teks yang diminta); keputusan tercatat di audit log.
- [ ] Perubahan 4: semua brand yang terlihat pengguna berbunyi DigiVoting; login admin (TOTP) tetap berfungsi.
- [ ] Perubahan 5: landing page menampilkan alur cara pakai untuk operator dan pemilih.
- [ ] Perubahan 6: hapus pemilihan hanya untuk Persiapan/Ditutup (ditolak saat Berlangsung); semua data terkait terhapus tanpa error dan tanpa sisa; token lama tidak berlaku; tautan publik jadi "tidak ditemukan". Rancangan penanganan trigger `EV001` sudah disampaikan ke Veo kalau menyentuh skema.
- [ ] Perubahan 7: access_key 64 karakter, satu kunci aktif, hash di database, URL khusus, rekap real-time tanpa identitas pemilih, banner merah selalu terlihat.
- [ ] Tidak ada perubahan yang melanggar aturan di atas (khususnya: tidak ada verifikasi SSL yang dimatikan, tidak ada migrate di production, `APP_KEY` tidak di-regenerate).
- [ ] `npm run build` (bila ada perubahan aset) dan test suite berjalan hijau sebelum push.
