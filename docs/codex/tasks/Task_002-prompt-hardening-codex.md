# PROMPT UNTUK CODEX — Hardening Keamanan E-Voting (Laravel)

---

## PERAN DAN KONTEKS

Kamu adalah engineer keamanan aplikasi senior yang bekerja di repo `otherveo/evoting`. Ini aplikasi e-voting sekolah (~200 pemilih) yang **sudah live di production**. Satu celah kecil bisa merusak integritas pemilihan, jadi prioritasnya: **jangan merusak alur voting yang berjalan** dan **jangan membuka celah baru**. Kerjakan dengan teliti, bukti di atas asumsi.

Stack (verifikasi sendiri di repo, laporkan kalau berbeda):
- Laravel 13, PHP ^8.3 (server: PHP 8.5.4), Livewire 4.4, Tailwind 4 + Vite, `bacon/bacon-qr-code`
- Nginx + PHP-FPM di AWS EC2, Cloudflare di depan, Supabase PostgreSQL (skema `evoting`, session pooler)
- Session `file`, cache `file`, queue `sync`
- Pemilih masuk lewat QR/token (satu QR per `voter_right`), sesi bilik 10 menit. Admin wajib TOTP, Operator tanpa TOTP.
- Mayoritas Livewire. Tidak ada REST API publik, webhook, atau aplikasi mobile. Semua request sah datang dari UI web.
- Fungsi kritis (`fn_confirm_vote` dll.) sudah menegakkan aturan di sisi server. Lapisan baru ini adalah **defense in depth**, bukan pengganti.

## ATURAN MUTLAK (jangan dilanggar)

1. **Anonimitas suara:** tabel `votes` hanya berisi `id`, `election_id`, `candidate_id`. Jangan tambah identitas pemilih atau timestamp. `candidate_id`, token mentah, sandi, kode TOTP, dan isi payload penuh **tidak boleh masuk log apa pun**. Keterbatasan transaction ID (temuan audit #6) sudah diterima, jangan didesain ulang.
2. **Tidak ada pemblokiran IP otomatis.** Banyak pemilih berbagi satu IP (NAT sekolah). Pelanggaran → tolak request (422/403/429) dan catat. Kalau perlu pembatasan, per sesi/token, bukan per IP.
3. **Zero-downtime & kompatibel mundur.** Migrasi hanya aditif (kolom nullable / tabel baru), tanpa drop/rename. Jangan ubah perilaku `fn_confirm_vote`, `voter_rights`, atau alur QR kecuali ada bug terbukti, dan laporkan dulu.
4. **Jangan push ke `main`.** Kerja di branch `hardening/security-2026-11`, commit kecil dan bermakna, satu PR dengan ringkasan, catatan rollback per perubahan.
5. **Jangan sentuh production/server secara langsung** kecuali diizinkan eksplisit. Untuk perubahan di luar repo (nginx, php.ini, firewall, Cloudflare) hasilkan file konfigurasi + runbook di `docs/security/` agar pemilik yang menerapkan.
6. **Jangan mencetak atau meng-commit secret** (`.env`, `APP_KEY`, kredensial DB/Supabase).
7. Jangan menebak. Kalau ada hal ambigu atau berisiko, tulis sebagai pertanyaan/asumsi di laporan, bukan keputusan diam-diam.
8. Perubahan yang bisa memutus fungsi (whitelist, CSP, Origin check) harus punya **saklar darurat via `.env`** (mis. `SECURITY_WHITELIST_MODE=enforce|report|off`) yang bisa dibalik tanpa deploy ulang kode, dengan default `enforce` di kode dan test.

## FASE 0 — AUDIT (baca saja, belum ubah apa pun)

Hasilkan `docs/security/AUDIT-2026-11.md`: daftar temuan dengan severity (Kritis/Tinggi/Sedang/Rendah), bukti (file:baris), dampak, dan perbaikan yang diusulkan. Periksa minimal:
- Semua route (`php artisan route:list`) dan semua komponen Livewire: method publik yang bisa dipanggil klien, properti publik yang bisa diubah klien (cek `#[Locked]`, mass assignment, authorization per method), apakah ada IDOR (akses data via id yang bisa ditebak/dimanipulasi).
- Pengecualian CSRF, middleware per route, guard admin/operator/pemilih, penegakan TOTP admin.
- Alur token/QR: entropi token, kedaluwarsa, penggunaan ulang, perbandingan aman (timing-safe), apa yang terjadi pada token tidak valid.
- Pengaturan session/cookie, `APP_DEBUG`, `APP_ENV`, halaman error (jangan bocorkan stack trace), logging.
- **Riwayat git repo publik:** scan seluruh history untuk secret (`.env`, kunci Supabase/service role, `APP_KEY`, kredensial). Jika ada yang pernah ter-commit, tandai Kritis dan sertakan langkah rotasi.
- `composer audit`, `npm audit`, versi paket yang punya advisory.
- Akses Supabase: apakah kunci service-role/anon bisa terekspos, hak akses user DB aplikasi (least privilege), dan RLS jika dipakai.
- File upload (jika ada: foto paslon dll.): validasi tipe/ukuran, lokasi penyimpanan, eksekusi file.

**Berhenti dan ringkas temuan Kritis/Tinggi di awal laporan** sebelum lanjut ke implementasi.

## FASE 1 — IMPLEMENTASI (urut prioritas)

### 1. IP klien asli (prasyarat rate-limit)
Masalah terverifikasi: Nginx melihat IP range Cloudflare, tidak ada `real_ip`, Laravel hanya trust `127.0.0.1`. Perbaiki:
- Nginx: `set_real_ip_from` untuk seluruh range Cloudflare (IPv4+IPv6) + `real_ip_header CF-Connecting-IP`. Simpan snippet di `docs/security/nginx/` beserta skrip pembaruan daftar range.
- Laravel: konfigurasi trusted proxies yang hanya mempercayai range Cloudflare (dan 127.0.0.1), bukan `*`. `CF-Connecting-IP` hanya dipercaya jika request memang datang dari range Cloudflare.
- Rekomendasikan (di runbook) agar origin hanya menerima koneksi dari range Cloudflare (security group/firewall), karena kalau tidak, header IP bisa dipalsukan dengan mengakses origin langsung.
- Test yang membuktikan `request()->ip()` benar di kedua kasus (dari Cloudflare dan dari sumber tak tepercaya).

### 2. Rate limiting (tanpa blokir IP)
Gunakan `RateLimiter::for()` bernama, kunci berlapis. Karena NAT, batas per-IP harus **longgar** (plafon anti-banjir), sedangkan batas ketat dipasang pada kunci yang lebih spesifik (sesi/token/browserId/username):
- Login admin & operator: ketat per username dan per IP (brute force), respons seragam.
- Verifikasi TOTP admin: sangat ketat per akun.
- Pembukaan link QR/token: batas per token dan plafon per IP (anti-enumerasi token).
- Konfirmasi pilihan, permintaan perangkat bersama, aksi admin/operator sensitif: batas per sesi/pengguna.
- Endpoint Livewire (`/livewire/.../update`): plafon per sesi dan per IP. Cek dokumentasi Livewire 4.4 untuk cara resmi memasang middleware/throttle di route update, jangan mengakali.
- Respons 429 dengan `Retry-After`, tanpa bocoran informasi. Setiap trigger dicatat ke audit (lihat poin 5), dengan pembatasan frekuensi logging agar penyerang tidak bisa memenuhi tabel.
- Dokumentasikan rekomendasi aturan rate-limit/WAF tambahan di Cloudflare (di `docs/security/cloudflare.md`), sebagai lapisan kedua.

### 3. Keaslian request (CSRF, Origin/Referer, anti-skrip)
- Pastikan CSRF aktif di semua route state-changing dan tidak ada pengecualian yang tidak perlu.
- Middleware `EnsureSameOriginRequest`: untuk POST/PUT/PATCH/DELETE (termasuk `/livewire/*`), `Origin` (fallback `Referer`) harus cocok dengan host aplikasi dari konfigurasi. Tolak 403 jika tidak cocok atau tidak ada. Gunakan `Sec-Fetch-Site` sebagai sinyal tambahan.
- Catat dengan jujur di dokumentasi: header ini bisa dipalsukan klien non-browser, jadi ini penyaring bot/skrip sederhana. Kontrol penentu tetap sesi, `voter_right`, dan validasi server-side.

### 4. Whitelist bentuk request (default-deny)
- Buat `config/request_whitelist.json` (atau PHP array bila lebih aman) yang mendefinisikan per route (nama + method): content-type yang diizinkan, ukuran body maksimum, field yang diizinkan beserta tipe, panjang maksimum, dan pola. Field di luar whitelist → ditolak.
- Middleware `EnforceRequestWhitelist` dipasang global untuk grup web. Route/method yang tidak terdaftar di whitelist → 403 (default-deny). Pelanggaran struktur → 403, pelanggaran isi field → 422. Batasi juga kedalaman JSON, jumlah field, panjang query string.
- **Livewire:** payload `/livewire/update` berstruktur (snapshot, updates, calls). Whitelist harus berbasis nama komponen + method yang boleh dipanggil + properti yang boleh di-update, divalidasi dari payload yang terurai. Pelajari struktur payload Livewire 4.4 dari kode vendor dan dokumentasi, dan buktikan dengan test bahwa seluruh alur sah (login, bilik, konfirmasi pilihan, aksi admin/operator) **tetap lolos**.
- Command `php artisan security:whitelist-check` yang gagal (exit ≠ 0) bila ada route di aplikasi yang belum punya entri whitelist; jalankan di CI.
- Saklar darurat `SECURITY_WHITELIST_MODE` (aturan #8): `enforce` (tolak), `report` (hanya catat), `off`.

### 5. Audit log
Gunakan tabel `audit_logs` yang ada (migrasi aditif bila perlu).
- **Abnormal (wajib):** pelanggaran whitelist, gagal CSRF/Origin, rate-limit terpicu, gagal login/TOTP, token tidak valid/kedaluwarsa/dipakai ulang, `tolak_batas_perangkat`, pola enumerasi.
- **Penting yang normal:** login/logout admin & operator, perubahan data (pemilihan, paslon, operator), buka/tutup voting, penerbitan QR, blokir perangkat. (Login admin saat ini kemungkinan belum tercatat, verifikasi.)
- Isi yang dicatat: kode kejadian, route, method, IP asli, hash sesi (bukan ID sesi mentah), user-agent terpotong, alasan penolakan, `request_id`. **Dilarang:** `candidate_id`, token mentah, sandi, kode TOTP, payload penuh. Buat fungsi sanitasi terpusat + test yang membuktikan larangan ini.
- **Anti-manipulasi:** tambah hash berantai (kolom nullable `prev_hash`, `hash`) dan command `php artisan audit:verify` untuk memverifikasi rantai. Perhatikan bahwa `fn_delete_election` menghapus baris audit pemilihan: usulkan desain yang menjaga rantai tetap valid (mis. rantai terpisah untuk kejadian keamanan global yang tidak ikut terhapus) dan tulis keputusannya di laporan.
- Lindungi dari log flooding (batas frekuensi/dedup per kunci).
- Notifikasi real-time (Telegram/email): **hanya rancang hook/antarmuka**, jangan hubungkan layanan eksternal tanpa persetujuan. Retensi: usulkan kebijakan di laporan.

### 6. Security header & CSP
- Saat ini hanya ada `X-Content-Type-Options` dan `X-Frame-Options`. Tambah: HSTS (mulai `max-age` moderat, tanpa `preload`/`includeSubDomains` kecuali dipastikan aman), `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, dan **CSP**.
- CSP harus kompatibel dengan Livewire 4 + Vite + Tailwind (pakai nonce bila perlu). Rilis dulu sebagai `Content-Security-Policy-Report-Only`, uji seluruh alur voting, baru ubah ke enforce. Tentukan satu tempat penetapan header (Nginx atau Laravel) agar tidak duplikat.

### 7. Session, cookie, konfigurasi
- Cookie: `Secure`, `HttpOnly`, `SameSite` (Lax atau Strict sesuai alur QR; uji dengan alur buka-link-dari-QR). Regenerasi session saat login, invalidasi sesi bilik setelah suara terkonfirmasi, batas absolut 10 menit ditegakkan di server.
- `APP_DEBUG=false`, `APP_ENV=production`, halaman error tidak bocor. Jangan ganti driver session/cache/queue tanpa persetujuan (cukup rekomendasi di laporan).
- Izin file session/storage, pastikan `.env`, `storage`, `.git`, dan file dot tidak bisa diakses lewat web (snippet Nginx `deny`).

### 8. Dependensi, `.env`, backup
- `composer audit` dan `npm audit`: perbaiki yang aman, laporkan sisanya. Tambahkan ke CI.
- `.env`: pastikan tidak ada di repo/history, `.env.example` bebas nilai asli, izin 640. Rencana rotasi secret bila ada yang pernah bocor.
- Backup: dokumentasikan strategi (backup Supabase, enkripsi, lokasi di luar webroot, uji restore). Verifikasi tidak ada backup/dump yang bisa diunduh lewat web.

## FASE 2 — PHP (di luar repo, wajib ada runbook)

Server berjalan PHP 8.5.4, yang **rentan terhadap php2shell (CVE-2026-109618 dan CVE-2026-109619)**, RCE tanpa autentikasi. Versi aman minimal 8.5.12, gunakan 8.5.x terbaru yang tersedia (verifikasi di php.net, jangan percaya angka dari prompt ini). Buat `docs/security/php-upgrade-runbook.md`:
- Langkah upgrade ke 8.5.x terbaru di Ubuntu (paket yang dipakai server), restart PHP-FPM, verifikasi `php -v` dan `php-fpm -v`, smoke test alur voting, rencana rollback. Bukan lompat ke 8.6.0, kecuali kompatibilitas Laravel 13/Livewire 4 sudah terbukti.
- Hardening tambahan (bukan pengganti patch): pertimbangkan `session.upload_progress.enabled=Off` bila aplikasi tidak memakainya, `client_max_body_size` ketat per route di Nginx, `memory_limit` wajar, dan aturan WAF Cloudflare untuk request multipart mencurigakan. Verifikasi setiap rekomendasi ke advisory resmi PHP.
- **Checklist deteksi kompromi sejak 17 Oktober 2026:** log Nginx untuk request multipart/POST tidak wajar (terutama ke `index.php`), proses anak tak lazim dari PHP-FPM/Nginx, file baru/berubah di webroot dan `storage`, crontab/service/SSH key baru, koneksi keluar tak dikenal. Jika ada indikasi: rotasi `APP_KEY`, kredensial DB/Supabase, semua secret, dan verifikasi integritas data suara.

## TEST (wajib, jalankan seluruhnya sebelum PR)

PHPUnit, termasuk regresi alur sah:
- Alur penuh sah tetap berhasil: operator terbitkan QR → pemilih buka bilik → pilih → konfirmasi; admin login + TOTP; aksi admin/operator utama.
- IP asli: benar dari Cloudflare, tidak bisa dipalsukan dari sumber tak tepercaya.
- Rate limit: 429 + `Retry-After`, batas per-IP longgar tidak mengunci pengguna sah dari satu IP bersama (simulasikan banyak pemilih satu IP).
- Origin/CSRF: ditolak bila tidak sesuai, lolos bila sah.
- Whitelist: field tak dikenal ditolak, route tak terdaftar ditolak, Livewire sah lolos, `security:whitelist-check` gagal bila ada route tanpa entri, mode `report`/`off` berfungsi.
- Audit: kejadian abnormal tercatat, `candidate_id`/token/sandi/TOTP **tidak pernah** muncul di log, hash berantai valid dan `audit:verify` mendeteksi perubahan.
- Header keamanan hadir.

## DELIVERABLES

1. `docs/security/AUDIT-2026-11.md` (Fase 0)
2. Branch `hardening/security-2026-11` + PR: kode, migrasi aditif, test, middleware, config whitelist
3. `docs/security/nginx/` (snippet real_ip, deny, header, batas body), `docs/security/cloudflare.md`, `docs/security/php-upgrade-runbook.md`
4. `docs/security/ROLLOUT.md`: urutan deploy zero-downtime, saklar darurat, cara rollback tiap perubahan, checklist uji pasca-deploy di staging/pra-hari-H
5. Ringkasan akhir: apa yang diubah, apa yang sengaja tidak diubah, risiko tersisa, pertanyaan terbuka untuk pemilik

## KRITERIA SELESAI

- Seluruh test lulus, termasuk regresi alur voting.
- Tidak ada `candidate_id`, token mentah, sandi, atau TOTP di log/audit.
- Tidak ada blokir IP otomatis di kode maupun dokumentasi.
- Semua perubahan bisa dibalik lewat saklar `.env` atau rollback yang terdokumentasi.
- Temuan Kritis/Tinggi sudah diperbaiki atau dijelaskan alasannya jika belum.
