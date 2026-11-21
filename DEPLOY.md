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

- Untuk upgrade instalasi lama, jadwalkan jeda layanan di luar pemungutan suara. Siapkan backup dan simpan dua kredensial di pengelola rahasia.
- Terapkan isi `schema.sql` dalam satu transaksi lewat koneksi pemilik schema pada port 5432. File ini juga memperbarui fungsi, grant lama, dan kolom data yang sudah ada. Password role yang sudah ada dipertahankan. Tetapkan password role baru melalui kanal administrasi rahasia.
- Pasang aplikasi bersama konfigurasi kedua login. `DB_USERNAME` dan `DB_PASSWORD` adalah kredensial `evoting_app`. `STAFF_DB_USERNAME` dan `STAFF_DB_PASSWORD` adalah kredensial `evoting_staff`. Pada Supavisor Session Mode sertakan akhiran proyek pada kedua username. Host, database, port, `DB_SEARCH_PATH=evoting`, dan `DB_SSLMODE=require` digunakan bersama. Jika memakai URL koneksi, atur `DB_URL` dan `STAFF_DB_URL` secara terpisah. Untuk sesi/cache berbasis database, pilih koneksi `staff`.
- Atur `TRUSTED_PROXIES` ke daftar alamat atau CIDR proxy yang benar, dipisahkan koma. Proxy harus mengganti header forwarding dari klien. Redaksi bagian token pada path `/v/{token}` di log proxy, CDN, dan APM. Hindari pencatatan isi request Livewire dan parameter SQL.
- Bangun ulang cache konfigurasi dan muat ulang worker. Semua sesi staf lama perlu login kembali. Sebelum membuka layanan, periksa login, aktivasi, akses publik, dan izin di bawah dengan data uji staging. Rollback aplikasi versi sebelumnya juga memerlukan pemulihan skema/grant yang kompatibel.

```sql
select has_table_privilege('evoting_app', 'evoting.staff_accounts', 'UPDATE'),
       has_function_privilege('evoting_app', 'evoting.fn_operator_election_results(uuid)', 'EXECUTE'),
       has_function_privilege('evoting_app', 'evoting.fn_delete_election(uuid,uuid,text)', 'EXECUTE'),
       has_table_privilege('evoting_staff', 'evoting.votes', 'SELECT');
```

Keempat pemeriksaan harus menghasilkan `false`.

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
