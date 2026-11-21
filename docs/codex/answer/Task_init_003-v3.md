**Di bilik suara DigiVoting, pemilih sekarang bisa membuka visi-misi, memilih paslon, melihat konfirmasi, lalu kembali dan mengganti pilihan tanpa menunggu server.** Draf pilihan hanya berada di memori halaman. Saat pemilih menekan **Konfirmasi pilihan**, browser mengirim paslon yang tampil di layar konfirmasi.

Saya mengukur alurnya langsung di Chrome pada branch `fix/hardening-voting-v1` dengan PostgreSQL lokal.

| Interaksi yang diuji | Request aplikasi |
|---|---:|
| Buka visi-misi A dan B, pilih A, lihat konfirmasi, kembali, lalu ganti ke B | **0** |
| Konfirmasi pilihan B hingga halaman selesai | **1** |
| Ketik nama pemilih dan centang konfirmasi nama kembar | **0** |
| Terbitkan QR setelah form registrasi lengkap | **1** |
| Ketik dan koreksi nama pada konfirmasi hapus pemilihan | **0** |

Database uji mencatat **tepat satu suara untuk B**, sesuai pilihan terakhir. Filter log audit juga sekarang dikirim bersama saat admin menekan **Terapkan filter**.

:codex-annotation{index="1" id="695cb57f-776c-4bd7-bdb6-48fb9cfddb4b"} Pemeriksaan peran, sesi staf, dan status TOTP berjalan ketika ada permintaan staf ke server, misalnya saat menerbitkan QR. Mengetik nama atau mencentang kotak konfirmasi cukup diproses browser.

:codex-annotation{index="2" id="9a0a759b-019a-4194-8367-3c337cff646e"} Server memeriksa `browserId` saat QR dibuka, sesi dimulai, izin perangkat diperiksa, dan suara dikirim. Saat submit, server memeriksa sesi, kelayakan paslon, dan kelengkapan daftar visi-misi yang dilaporkan browser. Daftar itu merupakan pernyataan dari browser bahwa tiap visi-misi telah dibuka.

**Verifikasi:** 16 tes lulus dengan 303 assertion, dan Pint lulus. Beranda live merespons HTTP 200. Pengukuran interaksi dilakukan pada branch lokal. Database live tidak disentuh, dan perubahan belum di-push atau dideploy.