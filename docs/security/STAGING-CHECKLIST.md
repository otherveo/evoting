# Checklist pengujian staging DigiVoting

Gunakan branch `hardening/security-2026-11`, hostname HTTPS staging, dan database serta bucket foto terpisah. Gunakan akun, calon, dan QR uji. Versi PHP-FPM staging harus sudah ditambal. [Panduan rollout](ROLLOUT.md)

## 1. Siapkan staging

- [ ] Catat commit yang diuji dengan `git rev-parse --short HEAD`. Pastikan konfigurasi aplikasi dan kedua koneksi database menunjuk ke staging.
- [ ] Cadangkan database staging. Terapkan `schema.sql` dari commit tersebut dalam satu transaksi memakai koneksi pemilik skema, lalu rilis kode.
- [ ] Atur `APP_URL` ke URL HTTPS yang Anda buka di browser dan `APP_DEBUG=false`. Mulai dengan pengaturan berikut.

```dotenv
SECURITY_ORIGIN_MODE=report
SECURITY_WHITELIST_MODE=report
SECURITY_CSP_MODE=report
```

- [ ] Bangun ulang cache konfigurasi dengan `php artisan config:cache`. Muat ulang worker sesuai setup. Pastikan halaman beranda bisa dibuka, lalu jalankan perintah berikut di staging.

```sh
php artisan security:whitelist-check
php artisan audit:verify
```

## 2. Uji alur di Chrome

- [ ] Masuk sebagai admin dengan TOTP, buat atau aktifkan operator uji, lalu logout dan masuk sebagai operator.
- [ ] Buat pemilihan resmi dan pemilihan simulasi. Tambah paslon, unggah satu foto, dan pastikan foto tampil. Terbitkan tiga QR berbeda per pemilihan.
- [ ] Untuk tiap pemilihan, buka ketiga QR dari **browser dan profil yang sama**. Refresh QR pertama sebelum memilih. QR pertama bisa memilih dan mengirim suara. QR kedua meminta persetujuan operator penerbit setelah **Mulai memilih**, lalu bisa mengirim suara setelah disetujui.
- [ ] QR ketiga langsung menampilkan halaman penolakan tanpa tombol memilih. Monitor operator penerbit menampilkan label merah **Batas perangkat tercapai** pada polling berikutnya, sekitar tiga detik saat monitor aktif. QR ketiga tidak masuk daftar persetujuan.
- [ ] Buka QR ketiga di browser lain. QR bisa dipakai dan peringatan aktif hilang dari monitor setelah polling. Periksa bahwa suara yang sah tercatat tepat sekali dan rekap sesuai jumlah suara yang dikonfirmasi.
- [ ] Uji rekap, logout, dan penghapusan satu pemilihan sintetis. Jalankan kembali `php artisan audit:verify`. Cek konsol Chrome untuk pelanggaran CSP dan cek audit atau log staging untuk request sah yang ditandai oleh Origin atau whitelist.

## 3. Ulangi dengan penegakan aktif

Jika alur sah lolos di mode `report`, ubah `SECURITY_ORIGIN_MODE` dan `SECURITY_WHITELIST_MODE` ke `enforce`. Pertahankan CSP di `report`. Bangun ulang cache konfigurasi, muat ulang worker, lalu ulangi checklist Chrome dengan data uji baru.

Catat commit, tanggal uji, dan hasil tiap skenario. Jika alur sah gagal dengan 403, 419, 422, atau 429, catat aksi, status HTTP, dan header `X-Request-ID`. Hindari menyertakan URL QR lengkap, cookie, sandi, kode TOTP, atau body request. Kembalikan kontrol yang memicu kegagalan ke `report` di staging, lalu ulangi uji setelah perbaikan. Setelah CI dan staging lulus, sampaikan hasilnya untuk keputusan merge.
