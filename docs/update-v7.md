# Update v7 — DRAF: Batas 2x penggunaan per perangkat per pemilihan

**Repo:** https://github.com/otherveo/evoting
**Live:** https://smpn7gtlo.myeclipse.my.id
**Status file ini:** SUDAH DIBANGUN (oleh Ryn, atas perintah Veo — bukan Codex) dan SUDAH LIVE. File ini adalah catatan implementasi (bukan lagi draf prompt).
**File ini:** catatan, bukan coding. Deploy oleh Ryn (skema dulu, baru kode).
**Live:** branch `feat/device-limit-v7` commit `7b9e110` (deploy 2026-11-24 ~11:05 WITA atas perintah Veo; skema dulu, baru kode).

## Latar (keputusan Veo)

Saat pengecekan traffic 2026-11-24 pagi (server sehat, lag dari jalur jaringan + polling Livewire), Veo bertanya apakah backend masih bisa diberi patch di tengah traffic seperti itu. Jawaban Ryn: bisa — deploy tidak butuh downtime (pull + clear cache, situs tetap jalan), traffic saat itu kebanyakan polling bukan voting aktif. Kalau patch mengubah skema DB, skema diterapkan ke Supabase dulu baru kodenya.

Veo lalu menentukan aturan baru: satu perangkat yang sama hanya boleh digunakan maksimal 2x per pemilihan; penggunaan ketiga langsung ditolak. Aturan ini ditulisnya sendiri seperti di Bagian A.

Tujuannya: mencegah satu perangkat dipakai berulang-ulang untuk memilih (misalnya perangkat yang diedarkan ke banyak pemilih). Batas ini ditegakkan sistem sepenuhnya — bukan oleh operator, dan bukan hanya di tampilan.

## Bagian A — Yang diminta Veo (verbatim, jangan diubah)

1. Sebelum memilih. Apa pun kondisinya, begitu 1 perangkat yang sama terdeteksi ada penggunaan ketiga, tombol konfirmasi hilang total, hanya pemberitahuan dan tombol X untuk menutup popup.
2. Per-pemilihan.
3. "Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses."
4. Penggunaan kedua jelas butuh persetujuan. Sementara yang ketiga hanya diinformasikan dan tidak ada cara apa pun (termasuk illegal melalui penetrasi, hacking, XSS, dan sebagainya) untuk mengizinkannya/meloloskan penggunaan ketiga kalinya pada perangkat yang sama.

5. Untuk perangkat yang saat ini sudah terlanjur digunakan lebih dari 2 kali biarkan saja, jangan dipaksa alokasi secara acak.

6. (Admin only) Log audit akan mendapatkan informasi yang sama (bisa pilih per pemilihan, training tidak dihitung).

7. (Admin only) Admin MAMPU memblokir perangkat yang sudah digunakan 2x sehingga akan langsung terblokir dari sisi anonim bahwa yang bersangkutan harus memilih menggunakan perangkat lain.

8. Ketika link dibuka di Chrome, detik itu juga perangkatnya dicatat. Trigger-nya itu saja, sehingga URL akan sepenuhnya tidak valid pada perangkat yang sama digunakan 3x.

**Catatan:** poin 8 ini menggantikan trigger "Mulai memilih" (yang sempat terkunci 2026-11-24 10:42). Trigger yang berlaku sekarang adalah saat link bilik dibuka — perangkat dicatat detik itu juga, dan pada penggunaan ke-3 URL sepenuhnya tidak valid di perangkat itu.

## Bagian B — Spesifikasi turunan (untuk Codex)

Bagian ini adalah penurunan teknis dari Bagian A oleh Ryn (bukan kata-kata Veo). Kalau ada yang bertentangan dengan Bagian A, Bagian A yang menang.

### Identitas perangkat

- Perangkat = browser, diidentifikasi oleh `browserId` (cookie HttpOnly yang terikat sesi server), sesuai keputusan v1 yang terkunci.
- Dua browser di satu perangkat fisik dihitung sebagai dua perangkat berbeda (keterbatasan web app — browser tidak mengekspos identitas hardware; sudah ditetapkan sejak v1).

### Cakupan

- Per pemilihan (`election_id`). Hitungan penggunaan dihitung terpisah untuk setiap pemilihan; perangkat yang sudah dipakai 2x di pemilihan A tetap bisa dipakai di pemilihan B (hitungan B mulai dari 0).

### Alur dan titik penegakan

- Penegakan terjadi **saat link bilik dibuka**: **1x penggunaan = 1 link bilik yang dibuka oleh satu pemilih (satu QR token) di perangkat itu, dicatat detik itu juga saat halaman dimuat** (keputusan Veo 2026-11-24 10:44, menggantikan trigger "Mulai memilih"). Saat pemilih membuka link bilik, sistem mencatat perangkat ini dan menghitung berapa kali perangkat ini sudah dipakai untuk pemilihan tersebut (tidak termasuk pembukaan saat ini).
- Satu QR token yang dibuka berulang kali di perangkat yang sama (misalnya refresh halaman) dihitung sebagai 1 penggunaan — yaitu penggunaan pertama untuk token itu — bukan penggunaan baru setiap kali halaman dimuat. Hitungan bertambah saat pemilih (QR token) yang BERBEDA membuka link di perangkat itu.
- Penggunaan ke-1: normal (tidak ada gerbang tambahan).
- Penggunaan ke-2: perangkat terdeteksi sebagai perangkat bersama — gerbang persetujuan operator tetap berlaku seperti sekarang (tidak berubah). Pemilih menunggu persetujuan operator penerbit QR.
- Penggunaan ke-3: ditolak. **URL sepenuhnya tidak valid di perangkat itu** — pemilih tidak bisa masuk bilik sama sekali. Yang tampil hanya popup pemberitahuan berisi pesan di Bagian A poin 3, dengan tombol X untuk menutup popup. **Tombol konfirmasi tidak ditampilkan sama sekali** — bukan dinonaktifkan, tapi hilang total. Pembukaan link ini tidak dicatat (tidak menambah hitungan).

### Penegakan server-side (wajib — ini inti poin 4 Bagian A)

- Batas ini **tidak boleh** hanya disembunyikan di UI. Menyembunyikan tombol saja tidak cukup: pemilih (atau penyerang) bisa memanggil API langsung dan melewati UI.
- Penegakan utama ada di jalur membuka link bilik (saat halaman bilik dimuat): server mencatat perangkat dan menghitung penggunaan sebelumnya, lalu menolak bila sudah 2x — URL sepenuhnya tidak valid di perangkat itu. `fn_confirm_vote` juga harus menegakkan batas ini sebagai pertahanan berlapis (bila ada yang memanggil API langsung tanpa melalui pembukaan link), dengan cara yang tidak menolak sesi yang sah — misalnya dengan memeriksa hitungan pada saat sesi dimulai, bukan hitungan yang sudah termasuk sesi saat ini.
- Tidak ada jalur apa pun — termasuk operator, endpoint lain, atau parameter apa pun — yang bisa mengizinkan atau meloloskan penggunaan ketiga pada perangkat yang sama. Satu-satunya jalan adalah pemilih memakai perangkat lain.

### Pesan penolakan (verbatim)

"Satu perangkat yang sama hanya diizinkan maksimal 2x digunakan. Silakan cari perangkat lain untuk melanjutkan proses."

### Aturan peralihan (grandfather) — Bagian A poin 5

- Penggunaan yang tercatat SEBELUM v7 di-deploy **tidak dihitung** ke dalam batas 2x. Hitungan dimulai saat v7 live.
- Perangkat yang saat ini sudah terlanjur tercatat dipakai lebih dari 2x **dibiarkan saja** — tidak diblokir retroaktif, dan riwayat penggunaannya tidak dihitung ulang atau dialokasikan ulang secara acak agar pas dengan batas. (Tafsir Ryn atas "jangan dipaksa alokasi secara acak": jangan utak-atik data riwayat yang sudah ada.)
- Artinya: perangkat yang sudah >2x sebelum v7 tetap bisa dipakai seperti biasa sampai ia mencapai 2 penggunaan BARU (pasca-v7) untuk pemilihan itu.

### Fitur admin — Bagian A poin 6 dan 7

**Log audit perangkat (admin only):**
- Log audit mendapatkan informasi yang sama dengan yang dipakai penegakan batas — yaitu penggunaan perangkat (perangkat mana, berapa kali dipakai, untuk pemilihan mana).
- Bisa dipilih/difilter per pemilihan.
- Pemilihan training tidak dihitung — konsisten dengan pola yang sudah ada di v6 (`listedElections()` mengecualikan nama yang cocok pola Training/Simulasi/Tes/Latihan, case-insensitive). Penggunaan di pemilihan training/latihan/tes tidak masuk hitungan.

**Blokir manual oleh admin (admin only):**
- Admin dapat memblokir sebuah perangkat yang sudah digunakan 2x.
- Begitu diblokir, perangkat itu **langsung terblokir dari sisi anonim (sisi pemilih)** — pemilih yang mencoba memilih dari perangkat itu ditolak dan diberitahu bahwa ia harus memilih menggunakan perangkat lain.
- Blokir ini manual dan langsung (tidak menunggu ada percobaan penggunaan ketiga). Admin menemukan perangkat yang akan diblokir lewat log audit di atas.
- Blokir ini melengkapi (bukan menggantikan) penolakan otomatis penggunaan ketiga.

### Yang TIDAK berubah

- Sesi pemilih 10 menit tetap berlaku.
- Gerbang persetujuan perangkat bersama tetap berlaku untuk penggunaan ke-2 (persetujuan hanya ke operator penerbit QR, tanpa eskalasi otomatis).
- Aturan v1 lain yang terkunci tetap berlaku (penghapusan pemilihan total, kuota QR dengan FOR UPDATE, dll.).

## Bagian C — Pertanyaan terbuka

1. **Apa yang dihitung sebagai "1x penggunaan"? — TERJAWAB, lalu DIGANTI (Veo 2026-11-24 10:44).** Sempat terkunci sebagai "1 sesi bilik dibuka, dihitung saat tombol 'Mulai memilih' ditekan" (10:42), lalu Veo menggantinya: "1x penggunaan" = 1 link bilik yang dibuka, perangkat dicatat detik itu juga saat halaman dimuat. Trigger yang berlaku adalah pembukaan link, bukan "Mulai memilih" dan bukan suara yang tercatat.
2. **Apakah penggunaan ke-2 yang disetujui operator tapi tidak jadi menekan "Mulai memilih" ikut dihitung? — TIDAK LAGI RELEVAN.** Trigger sudah pindah ke pembukaan link (poin 1), jadi "Mulai memilih" bukan lagi titik hitung. Yang dihitung adalah pembukaan link oleh pemilih (QR token) yang berbeda di perangkat itu.
3. **Apakah operator perlu tampilan untuk melihat hitungan penggunaan per perangkat?** Tidak diminta Veo — jangan tambahkan kecuali Veo meminta.
4. **Apakah "training tidak dihitung" juga berlaku untuk hitungan batas 2x (bukan hanya log audit)?** Bagian A poin 6 menyebutkannya dalam konteks log audit. Pembacaan yang wajar: ya, karena log audit menampilkan hitungan yang sama dengan yang dipakai penegakan batas — jadi penggunaan di pemilihan training tidak menghabiskan jatah 2x perangkat. Butuh konfirmasi Veo.
5. **Cakupan blokir manual admin: per pemilihan atau global?** Mengingat batas 2x dan log audit sama-sama per pemilihan, pembacaan yang wajar adalah per pemilihan (perangkat diblokir untuk pemilihan itu). Butuh konfirmasi Veo.
6. **Pesan untuk perangkat yang diblokir manual admin — sama dengan pesan penolakan otomatis v7, atau pesan berbeda?** Veo tidak menentukan pesan berbeda; yang diminta hanya pemilih diberitahu harus memakai perangkat lain. Default yang disarankan: pakai pesan yang sama dengan Bagian A poin 3, kecuali Veo menentukan lain.
7. **Bagaimana admin mengidentifikasi perangkat yang akan diblokir di log audit?** Perangkat diidentifikasi sistem sebagai `browserId` (bukan nama yang mudah dibaca manusia). Log audit perlu menampilkan pengenal yang cukup bagi admin untuk memilih perangkat yang tepat (misalnya browserId yang dipersingkat + info penggunaan terakhir). Desain tampilannya diserahkan ke Codex, tapi jangan menampilkan data pemilih yang tidak perlu (anonimitas pemilih tetap dijaga — log ini tentang perangkat, bukan tentang siapa pemilihnya).

## Bagian D — Batasan teknis (standing constraints)

- Tanpa mengubah keputusan v1 yang terkunci, kecuali yang diubah v7 ini.
- Tanpa menonaktifkan SSL verification, tanpa migrate di production, tanpa regenerate APP_KEY.
- Bila ada perubahan skema (kolom/tabel/fungsi baru): terapkan ke Supabase live SEBELUM kode di-deploy (aturan: skema dulu, baru kode), dalam satu transaksi.
- Tanpa perubahan file Vite kecuali perlu — CSS/JS baru di `public/css/` / `public/js/`; deploy tetap pull + clear cache tanpa `npm run build` (kecuali `resources/css/app.css` atau `resources/js/app.js` berubah).
- Deploy bisa dilakukan di tengah traffic polling (tidak butuh downtime), idealnya saat tidak ada pemilih yang sedang memilih.
- Branch: dikerjakan Ryn di branch fitur `feat/device-limit-v7`, commit `7b9e110`, Ryn pull + deploy. Live di branch itu (bukan main) atas perintah Veo ("deploy pakai branch itu").

## Verifikasi deploy (2026-11-24 ~11:05 WITA)

- Skema: COMMIT OK (124 statement, satu transaksi); tabel `device_blocks` ada; 6 fungsi v7 ada (`fn_device_open_gate`, `fn_device_use_count`, `fn_device_blocked`, `fn_block_device`, `fn_unblock_device`, `fn_device_limit_since`); view `v_device_usage` ada; marker grandfather `device_limit_v7_since` ter-set (2026-11-24T03:00:55Z = 10:00:55 WITA); 4 pemeriksaan hak DEPLOY.md semua False; grant fungsi voting/staf semua True; data utuh (3 elections, 11 candidates).
- Kode: AWS checkout `feat/device-limit-v7` commit `7b9e110` (push ke GitHub dulu; secret scan bersih; pesan verbatim terverifikasi sama persis di BallotPage/VotingService/schema). Catatan: `git fetch` pertama di AWS gagal karena Ryn sempat men-set `GIT_SSH_COMMAND` ke config VM (`/home/hatch/.ssh/config`, tidak ada di AWS) — diperbaiki dengan membiarkan git AWS memakai SSH config-nya sendiri (`~/.ssh/id_ed25519_evoting`). `php artisan cache:clear` sebagai ubuntu gagal ("Failed to clear cache") karena subdirektori `storage/framework/cache/data/*` milik www-data mode 755 (bukan group-writable) — diperbaiki dengan `sudo -u www-data php artisan cache:clear`. `.env` tetap 640 ubuntu:www-data.
- Publik: homepage 200, /up 200, /paslon 200, `/admin/device-usage` 302 → /login (route ada, middleware admin bekerja; butuh login admin untuk melihat isinya).
- Belum diuji live: alur bilik 3 QR di satu browser (ke-3 ditolak), refresh QR pertama = 1 penggunaan, blokir manual dari `/admin/device-usage`. Test baru `test_device_limit_two_uses_per_election_and_admin_block` belum dijalankan (VM tidak punya PHP/PostgreSQL lokal).

## Perbaikan pasca-deploy (2026-11-24 ~11:40 WITA)

**Gejala:** `/admin/device-usage` error 500 ("Terjadi Kendala pada Sistem"). Log `laravel-2026-11-24.log`: `Database operation failed, sql_state 42501` (insufficient_privilege), 2 kejadian saat Veo membuka halaman itu.

**Akar masalah:** 4 fungsi baca v7 (`fn_device_limit_since`, `fn_device_use_count`, `fn_device_blocked`, `fn_device_open_gate`) dibuat sebagai SECURITY INVOKER (`language sql/plpgsql`, tanpa `security definer`) dan tidak ada GRANT EXECUTE ke `evoting_staff`. Halaman admin query `v_device_usage` via koneksi staff; view itu memanggil `fn_device_limit_since()` yang membaca `evoting.cache` — sebagai evoting_staff (tanpa EXECUTE dan tanpa SELECT ke cache) → 42501. (`fn_block_device`/`fn_unblock_device` sudah SECURITY DEFINER sejak awal; grant EXECUTE-nya ke staff juga sudah ada.)

**Perbaikan (commit `6783d96` di branch yang sama):** keempat fungsi baca dijadikan SECURITY DEFINER; grant EXECUTE ditambahkan — evoting_staff untuk keempat fungsi baca, evoting_app untuk `fn_device_limit_since` + `fn_device_open_gate` (melengkapi `fn_device_use_count`/`fn_device_blocked` yang sudah ada). Skema diterapkan ulang ke Supabase live (124 statement, COMMIT OK). Diverifikasi langsung sebagai evoting_staff: `SELECT v_device_usage` OK (2 baris), EXECUTE `fn_device_limit_since` True, `is_blocked` 0 baris. Kode di AWS ditarik ke `6783d96`, cache di-clear, homepage + /up 200. Veo diminta mencoba lagi `/admin/device-usage`.

## Bagian E — Status implementasi (Ryn, 2026-11-24)

**Penyimpangan dari Bagian D yang dicatat:** Veo memerintahkan Ryn (bukan Codex) yang membangun v7, di branch `feat/device-limit-v7`. Branch sudah dibuat dan di-commit lokal (belum push, belum deploy, skema belum diterapkan ke Supabase live).

**Yang dibangun:**
- Skema (`schema.sql`): tabel baru `evoting.device_blocks` (+ indeks), marker grandfather `device_limit_v7_since` di tabel `cache` (diset sekali saat skema pertama diterapkan, tidak pernah ditulis ulang), fungsi `fn_device_limit_since`, `fn_device_use_count`, `fn_device_blocked`, `fn_device_open_gate` (dengan `pg_advisory_xact_lock` per pasangan pemilihan+perangkat untuk menutup balapan buka-serentak), `require_active_admin`, `fn_block_device` (DB-033), `fn_unblock_device` (DB-034), view `v_device_usage` (DB-035). `fn_open_qr` menolak di titik buka link (kode `device_limit_reached` / `device_blocked`, pesan verbatim Bagian A poin 3) dan mencatat penolakan sebagai `akses_ditolak` + entri audit (`tolak_batas_perangkat` / `tolak_perangkat_diblokir`); `fn_start_voting` dan `fn_confirm_vote` menegakkan sebagai pertahanan berlapis tanpa menolak sesi yang sah; `fn_delete_election` ikut menghapus `device_blocks` (agar penghapusan total pemilihan tidak gagal karena FK). Grant least-privilege: `evoting_app` hanya execute `fn_device_use_count`/`fn_device_blocked`, `evoting_staff` execute block/unblock + select view.
- PHP/Blade: `VotingService::blockDevice()` / `unblockDevice()`; `BallotPage` tahap terkunci baru `ditolak_perangkat` + partial `stage-ditolak-perangkat.blade.php` (pesan verbatim, hanya tombol X, tanpa tombol konfirmasi); halaman admin `DeviceUsagePage` + route `GET /admin/device-usage` + tautan nav "Penggunaan Perangkat".
- Test: `test_device_limit_two_uses_per_election_and_admin_block` di `tests/Feature/EvotingSystemTest.php` (buka link 1x/2x/3x, refresh token yang sama = 1 penggunaan, perangkat lain tidak terpengaruh, persetujuan operator untuk penggunaan ke-2 tetap berlaku, pertahanan berlapis di start/confirm, blokir manual + buka blokir, halaman admin bisa dibuka).

**Asumsi yang dipakai (sesuai default Bagian C):** Q4 ya — training tidak dihitung juga untuk batas; Q5 blokir manual per pemilihan; Q6 pesan blokir manual = pesan verbatim yang sama; Q7 perangkat dikenali lewat label browser/OS + hash 6 karakter, tanpa nama pemilih. Tambahan di luar spec: fungsi unblock ditambahkan (blokir tanpa unblock adalah jebakan); blokir ditegakkan di buka link + mulai memilih + konfirmasi; sesi yang sudah lolos gerbang tidak dicek ulang saat resume.

**Verifikasi yang sudah dilakukan:** `schema.sql` utuh ter-parse (124 pernyataan, pglast); 10 fungsi baru/berubah ter-parse PL/pgSQL; semua file PHP yang diubah/ditambah ter-parse (php-parser); direktif Blade seimbang. **Belum diverifikasi:** test belum dijalankan (VM ini tidak punya PostgreSQL lokal maupun PHP — template test DB tidak tersedia); perilaku live (alur bilik, tampilan blokir) belum diuji — butuh skema diterapkan ke DB test + deploy atas perintah Veo.

**Urutan deploy (saat Veo memerintahkan):** 1) terapkan `schema.sql` ke Supabase live dalam SATU transaksi (script `apply-schema-hardening.py`); 2) baru pull kode + `cache:clear` (tanpa `npm run build` — tidak ada perubahan `resources/css/app.css` / `resources/js/app.js`).
