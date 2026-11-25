# Runbook pembaruan PHP-FPM DigiVoting

PHP memperbaiki CVE-2026-109618 dan CVE-2026-109619 pada PHP 8.5.12 dan 8.4.27. Seri 8.5 sudah memiliki 8.5.13 saat runbook ini disiapkan. Versi aktif PHP-FPM produksi belum terverifikasi. Pilih patch keamanan terkini dari sumber paket yang disetujui untuk seri yang benar-benar menjalankan situs. Jika distributor melakukan backport, cocokkan revisi paket terhadap buletinnya. [Advisori PHP](https://www.php.net/archive/2026.php/) · [Changelog PHP 8](https://www.php.net/ChangeLog-8.php#8.5.13)

## 1. Identifikasi runtime dan siapkan pemulihan

- Jalankan `php -v` untuk CLI. Temukan layanan aktual dengan `systemctl list-units 'php*-fpm.service'` dan cocokkan socket atau alamat FastCGI pada konfigurasi vhost Nginx. Catat `systemctl show -p MainPID -p ExecStart php8.5-fpm` dan versi binary yang tercantum. Ganti `php8.5-fpm` dengan layanan yang benar bila berbeda. Cocokkan paket FPM dan ekstensi melalui `dpkg-query -W 'php*'` dan `apt-cache policy`.
- Simpan konfigurasi Nginx dan FPM, daftar revisi paket, serta snapshot host sesuai prosedur pemulihan. Cadangkan database dengan prosedur terpisah. Identifikasi build bertambal yang bisa dipakai jika rollback diperlukan.
- Uji di staging dengan konfigurasi pool, driver sesi, dan ekstensi yang sama. Jadwalkan perubahan produksi saat tidak ada pemilihan aktif.

## 2. Perbarui dan verifikasi

1. Segarkan indeks paket dari repositori yang sudah dipakai host. Tinjau paket dan candidate version menggunakan simulasi `apt-get -s install --only-upgrade` sebelum menerapkan. Perbarui paket FPM, CLI, common, opcache, serta ekstensi yang terpasang pada seri yang sama. Pastikan ekstensi `pdo_pgsql`, `openssl`, `curl`, `mbstring`, dan `fileinfo` tetap tersedia.
2. Jalankan pemeriksaan konfigurasi dengan binary FPM yang digunakan, misalnya `php-fpm8.5 -t`, dan validasi Nginx dengan `nginx -t`. Mulai ulang layanan FPM agar semua worker lama diganti. Periksa status layanan, PID baru, dan binary aktif melalui `/proc/<PID>/exe`. Verifikasi versi binary aktif dan CLI secara terpisah.
3. Periksa `/up`, beranda, login admin + TOTP, login operator, unggah foto, penerbitan QR, konfirmasi suara anonim, dan banner perangkat di monitor. Gunakan data sintetis untuk tes voting. Jalankan `php artisan audit:verify`. [Checklist staging](STAGING-CHECKLIST.md)
4. Jika smoke test gagal, pindahkan trafik ke instance sebelumnya yang sudah bertambal atau aktifkan halaman pemeliharaan selagi memulihkan paket dan konfigurasi. Setelah pemulihan, verifikasi worker serta ulangi smoke test sebelum trafik dibuka.

Setelah pembaruan, verifikasi batas body di Nginx dan ukuran unggahan Laravel sesuai kebutuhan foto. Pertimbangkan menonaktifkan `session.upload_progress.enabled` setelah memastikan alur unggah tetap berfungsi. Pertahankan `memory_limit` yang telah diuji untuk runtime yang sudah bertambal. Untuk kontrol tambahan, evaluasi aturan WAF di mode pencatatan dan tinjau dampaknya pada trafik NAT. [Panduan Cloudflare](cloudflare.md)

## 3. Tinjau indikasi kompromi

Tinjau sejak 17 Oktober 2026 bila log masih tersedia. Cocokkan perubahan file di webroot dan storage, proses anak PHP-FPM/Nginx, koneksi keluar, service dan cron baru, serta perubahan kunci SSH dengan aktivitas yang sah. Periksa anomali volume POST atau multipart tanpa mengekspor body, token QR, maupun isi pilihan.

Jika ada indikasi, amankan bukti dan isolasi host melalui prosedur insiden. Verifikasi grant dan integritas suara dari lingkungan yang bersih. Siapkan rotasi kredensial database, Supabase, dan APP_KEY dengan rencana untuk material TOTP yang terenkripsi dan pencabutan sesi. Lakukan verifikasi ulang sebelum membuka layanan.
