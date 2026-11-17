# Panduan & Checklist Penyebaran Produksi (Deploy Checklist) — E-Voting

Dokumen ini memuat daftar periksa wajib sebelum dan sesudah penyebaran aplikasi **E-Voting** ke lingkungan produksi (AWS Ubuntu + PHP 8.4 + Nginx/PHP-FPM + Supabase PostgreSQL & Storage).

---

## 1. Prasyarat Infrastruktur & Lingkungan Server (AWS Ubuntu, PHP 8.4)

- [ ] **Paket Sistem & Sertifikat TLS (`ca-certificates`):**
  Pastikan paket `ca-certificates` terpasang dan mutakhir agar ekstensi `curl` serta `openssl` pada PHP 8.4 dapat memverifikasi sertifikat TLS `*.supabase.co` tanpa galat `cURL error 60`:
  ```bash
  sudo apt-get update && sudo apt-get install -y ca-certificates curl
  sudo update-ca-certificates
  ```
- [ ] **Verifikasi Konfigurasi CA Bundle di PHP (CLI & FPM):**
  Untuk pengembangan lokal Windows, pastikan `curl.cainfo` dan `openssl.cafile` di `php.ini` menunjuk ke berkas `cacert.pem` Mozilla yang valid. Untuk AWS Ubuntu (PHP 8.4), pastikan PHP mengenali CA bundle sistem (`/etc/ssl/certs/ca-certificates.crt`):
  ```bash
  php -r "var_dump(openssl_get_cert_locations());"
  php -I "https://pmxutgykqrruffmggajk.supabase.co"
  ```
  > **Catatan Keamanan (Terkunci):** Dilarang menonaktifkan verifikasi SSL (`Http::withoutVerifying()`, `CURLOPT_SSL_VERIFYPEER => false`, atau `'verify' => false`) karena akan membuka celah serangan *Man-in-the-Middle* (MitM) terhadap `SUPABASE_SECRET_KEY`.

---

## 2. Database Supabase PostgreSQL

- [ ] **Jalankan `schema.sql` Sekali pada Project Supabase:**
  Jalankan berkas `schema.sql` melalui Supabase SQL Editor atau koneksi langsung port `5432` (bukan transaction pooler `6543`).
- [ ] **Koneksi Aplikasi (`evoting_app` via Supavisor Session Mode):**
  Aplikasi Laravel wajib terhubung menggunakan:
  - `DB_PORT=5432` (Supavisor *session mode* — mendukung *prepared statements* dan state sesi).
  - `DB_USERNAME=evoting_app` (atau `evoting_app.<project-ref>` pada pooler).
  - `DB_SEARCH_PATH=evoting`.
  - `DB_SSLMODE=require`.
  - Jangan pernah menjalankan `php artisan migrate` di produksi.
- [ ] **Verifikasi Hak Akses Minimum (`least privilege`):**
  Pastikan kueri berikut mengembalikan `false` (`f`):
  ```sql
  select has_table_privilege('evoting_app', 'evoting.votes', 'select');
  ```

---

## 3. Penyimpanan Foto Paslon (Supabase Storage Bucket `paslon-foto`)

- [ ] **Konfigurasi Variabel Lingkungan (`.env`):**
  - `SUPABASE_URL=https://<project-ref>.supabase.co`
  - `SUPABASE_SECRET_KEY=<service_role_or_secret_key>` (hanya di server Laravel, tidak pernah dikirim ke browser).
  - `SUPABASE_STORAGE_BUCKET=paslon-foto`
- [ ] **Uji Unggah Foto Paslon Pasca-Deploy (Wajib):**
  Setelah penyebaran selesai dan PHP-FPM/web server dimulai ulang:
  1. Masuk sebagai Operator pada pemilihan berfase `Persiapan`.
  2. Tambahkan pasangan calon baru disertai unggahan berkas foto (`.jpg` / `.png` / `.webp`).
  3. Pastikan penyimpanan berhasil tanpa galat TLS/cURL, kolom `candidates.photo_path` berisi `<election_id>/<candidate_id>-<random8>.<ext>`, dan foto tampil melalui URL publik bucket `paslon-foto`.

---

## 4. Keamanan Aplikasi, Log, & Provisi Akun Awal

- [ ] **Konfigurasi Produksi Laravel (`.env`):**
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `SESSION_SECURE_COOKIE=true`
  - `SESSION_HTTP_ONLY=true`
  - `LOG_CHANNEL=daily` (menyimpan log galat teknis ber-timestamp di `storage/logs/laravel-YYYY-MM-DD.log` tanpa membocorkannya ke antarmuka pengguna).
- [ ] **Provisi Akun Staf Awal (`KP-006`):**
  ```bash
  php artisan seed:admin admin "Administrator Sistem"
  php artisan seed:operator operator1 "Operator Pemilihan"
  ```
- [ ] **Pemeriksaan Kesehatan (`/up`):**
  Pastikan endpoint `/up` mengembalikan HTTP `200 OK` untuk pemantau uptime eksternal.
