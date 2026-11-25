# Rilis hardening Fase 1

1. Cadangkan database lalu uji pemulihannya di lingkungan terisolasi. Terapkan skema dalam satu transaksi sebelum rilis kode. Gunakan build PHP-FPM yang sudah diverifikasi terhadap [advisori resmi PHP](https://www.php.net/archive/2026.php/).
2. Setel `APP_URL` ke origin kanonis, aktifkan cookie Secure dan HttpOnly, serta gunakan `APP_ENV=production` dan `APP_DEBUG=false`. Pertahankan driver sesi dan cache yang sudah dipakai. Periksa kepemilikan storage, izin berkas .env 640 pada Linux, dan gunakan public/ sebagai root web. Uji bahwa berkas titik, source, dan cadangan tidak dapat diunduh.
3. Terapkan konfigurasi [Nginx dan Cloudflare](cloudflare.md) di staging. Redaksi token pada tiap hop. Jangan rekam query bertanda tangan atau body Livewire. Verifikasi TLS pooler dengan CA dan hostname sebelum mengubah pengaturan koneksi efektif.
4. Jalankan Origin dan whitelist di mode `report` selama smoke test, lanjutkan ke `enforce` setelah alur sah lolos. Uji login, aktivasi, TOTP, penerbitan QR, bilik, persetujuan perangkat, upload foto, rekap, logout dan penghapusan pemilihan uji.
5. Jalankan `php artisan security:whitelist-check` dan `php artisan audit:verify`. Simpan hash terakhir bersama cadangan terenkripsi yang aksesnya terpisah dari database. Verifikasi internal mendeteksi perubahan isi dan sambungan. Pembandingan hash eksternal diperlukan untuk mendeteksi penghilangan ekor atau penulisan ulang oleh pemilik database.

Default `SECURITY_ORIGIN_MODE` dan `SECURITY_WHITELIST_MODE` adalah `enforce`. Mode `report` mencatat pelanggaran dan `off` mematikan lapisan tambahan. Bangun ulang cache config dan reload worker sesuai setup setelah mengubah nilai. CSRF dan checksum Livewire tetap berlaku. Gunakan pengujian Chrome desktop dan ponsel sebelum rilis.

CSP default `report` dan bisa dimatikan dengan `SECURITY_CSP_MODE=off`. Kompatibilitas Blade dan Alpine masih membutuhkan skrip inline dan evaluasi dinamis. Tinjau konsol browser sebelum mengaktifkan CSP enforce. Header hanya ditetapkan di Laravel. HSTS awal satu hari di HTTPS, tanpa preload atau subdomain. Nilai `SECURITY_HSTS_MAX_AGE=0` mengirim pencabutan pada kunjungan berikutnya.

Plafon Livewire per IP memperhitungkan sekitar 200 perangkat pada NAT yang sama dengan polling tiga detik. Jika batas trafik keliru, kembalikan kode limiter atau rilis sebelumnya. Periksa kenaikan 403, 419, 422 dan 429 setelah rollout dan ulangi satu alur QR sampai selesai. Jika kode di-rollback, pertahankan tabel audit aditif. Untuk Nginx, kembalikan include terakhir yang teruji, jalankan `nginx -t`, reload, lalu periksa pemulihan IP dan redaksi.

Rantai keamanan dipisahkan dari audit operasional agar penghapusan total pemilihan tidak memotong rantainya. Kenaikan kejadian `qr_invalid` menjadi sinyal enumerasi untuk ditinjau. Integrasi notifikasi kelak dapat membaca ringkasan dari rantai setelah persetujuan terpisah. Sebagai titik awal tinjauan retensi, pertimbangkan 30 hari dan selaraskan dengan kebijakan sekolah. Arsipkan serta verifikasi hash sebelum merancang pemangkasan. Lakukan pemulihan cadangan di luar produksi dan periksa grant, login, fungsi serta integritas suara.

## Hasil pemeriksaan lokal

Seluruh 27 tes lulus dengan 519 assertion pada PostgreSQL lokal. Uji HTTP mencakup login, unggah sementara Livewire, konfirmasi suara, CSRF, perbedaan origin, dan 429 dengan token lain tetap bisa diakses dari IP yang sama. Route whitelist lulus dengan dan tanpa route cache. Audit Composer dan npm melaporkan nol advisori pada lockfile yang diperiksa. CI GitHub dan smoke test staging masih menunggu publikasi branch serta lingkungan staging yang diotorisasi.
