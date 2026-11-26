**PHP: ya, segera update, tapi cukup ke patch terbaru di cabang 8.5, tidak perlu loncat ke 8.6.0.**

Versi 8.5.4 yang Anda pakai rentan terhadap php2shell. PHP 8.5.x di bawah 8.5.12 terdampak, dan perbaikannya ada di 8.5.12. PHP menyatakan tidak ada perubahan konfigurasi yang bisa memitigasi kedua celah ini sepenuhnya, jadi patch adalah satu-satunya solusi nyata. Rilis keamanan tersebut keluar 21 Oktober 2026, sehingga server Anda sudah terbuka sekitar lima minggu.

- **Target update:** versi 8.5.x terbaru. Salah satu sumber menyebut 8.5.13 dirilis bersamaan dengan 8.6.0 dan memuat patch keamanan tambahan, tapi cek angka pastinya di php.net/downloads.
- **Kenapa tidak langsung 8.6.0:** itu rilis mayor. Salah satu sumber menyebut 8.6 menghapus fungsi regex mbstring, dan kompatibilitasnya dengan Laravel 13 serta Livewire 4 perlu diuji dulu. Untuk aplikasi pemilihan, patch minor lebih aman daripada upgrade mayor mendadak.
- **Cek kemungkinan sudah dibobol:** Exploit ini bisa dijalankan penyerang tanpa autentikasi. Percobaan eksploitasi tercatat sejak 17 Oktober, sebelum pengumuman publik. Periksa log web dan proses anak yang mencurigakan dari PHP-FPM atau Apache sejak Oktober. Jika ada indikasi, rotasi `APP_KEY`, kredensial database, dan semua secret, lalu verifikasi integritas data suara.

Prompt Anda sudah punya arah yang bagus, tapi masih terlalu ringkas untuk dieksekusi AI tanpa menebak-nebak. Ini pertanyaan yang saya perlukan:

**1. Cara prompt akan dipakai**
- Alat apa yang akan menjalankannya (Claude Code, Cursor, lainnya), dan apakah ia punya akses ke seluruh repo?
- Hasil yang diinginkan: laporan audit saja, atau langsung perubahan kode (middleware, migrasi, test)?

**2. Model pemilihan**
- Pemilih login dengan apa (NIK, kode unik/token, akun biasa)? Apakah suara harus anonim (tidak bisa ditelusuri ke pemilih)?
- Bagaimana aturan "satu orang satu suara" saat ini ditegakkan?
- Berapa perkiraan jumlah pemilih, berapa lama periode voting, dan apakah web ini publik atau hanya internal? Ini penting untuk menentukan angka rate-limit.

**3. Peran dan autentikasi**
- Ada peran admin dan operator (terlihat dari `seed:admin` dan `seed:operator`). Apa saja yang boleh dilakukan masing-masing?
- `bacon-qr-code` biasanya untuk 2FA TOTP. Apakah 2FA sudah dipakai, dan untuk peran mana?
- Apakah semua form memakai Livewire, atau ada juga controller/route biasa dan endpoint API?

**4. Infrastruktur**
- Web server (Nginx/Apache), PHP-FPM atau mod_php, dan hosting (VPS, shared, Docker)?
- Apakah ada Cloudflare atau WAF di depannya? Database dan driver session/cache/queue apa (MySQL, PostgreSQL, Redis)?
- Apakah ada reverse proxy? Ini menentukan apakah IP asli pengunjung terbaca benar untuk rate-limit.

**5. Maksud "request palsu yang tidak datang dari web"**
Saya menduga ini mencakup CSRF, pengecekan Origin/Referer, dan pemblokiran skrip otomatis (curl/bot). Apakah benar, atau ada juga endpoint yang memang harus bisa dipanggil dari luar (webhook, aplikasi mobile)?

**6. Perilaku whitelist**
- Request di luar whitelist sebaiknya ditolak saja (422/403), atau juga memicu pemblokiran IP/akun otomatis? Risiko pemblokiran otomatis adalah pemilih sah ikut terkunci di tengah periode voting.
- Whitelist per route yang berisi field, tipe, dan panjang maksimum yang diizinkan, dengan field tak dikenal ditolak, cocok dengan maksud Anda?

**7. Audit log**
- Disimpan di mana (tabel database, file, layanan eksternal) dan berapa lama?
- Perlu bukti anti-manipulasi (misalnya hash berantai)?
- Perlu notifikasi real-time (Telegram/email) saat ada anomali?
- Saya sarankan log juga mencatat peristiwa penting yang normal (login admin, perubahan data, pembukaan/penutupan voting), bukan hanya request abnormal. Setuju? Pilihan suara sendiri tidak boleh masuk log.

**8. Cakupan tambahan**
Apakah ingin sekalian dicakup: security header dan CSP, konfigurasi session/cookie, `composer audit`, keamanan `.env` dan backup, serta test otomatis untuk fitur keamanan?

**9. Status proyek**
Kapan pemilihan berlangsung, dan apakah web sudah di production? Ini menentukan prioritas dan seberapa agresif perubahannya.

Setelah jawaban Anda masuk, saya susun prompt yang terstruktur: konteks, ancaman yang dituju, batasan, format keluaran, dan kriteria selesai. Jawaban singkat saja cukup, dan yang belum pasti boleh Anda lewati.

Sources:
- [PHP2Shell FAQ (watchTowr)](https://watchtowr.com/intelligence/php-cve-2026-109618-faq/)
- [PHP2Shell (Wikipedia)](https://en.wikipedia.org/wiki/PHP2Shell)
- [Rilis PHP 8.6.0 dan patch keamanan](https://www.linuxcompatible.org/story/php-86-launches-with-coordinated-security-patch-across-all-supported-branches/)
- [Arsip berita PHP.net 2026](https://www.php.net/archive/2026.php?inf_ver=2)