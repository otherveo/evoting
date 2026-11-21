**Pemeriksaan keamanan berjalan di dalam request yang memang dikirim ke server.** Memilih kartu calon, membuka tampilan, atau menjalankan animasi di browser tidak otomatis memicu pengecekan TOTP atau identitas browser. Saya cek implementasinya, dan ada satu tombol yang memang mendapat tambahan request setelah audit: **Ubah pilihan**.

Annotation 1 **Untuk staf**, Admin memasukkan TOTP saat login. Ketika Admin kemudian mereset sandi Operator, misalnya, tombol tersebut memang perlu mengirim request untuk mengubah akun. Dalam **request yang sama**, Laravel memeriksa apakah Admin masih aktif, sesinya masih berlaku, dan sesi itu sudah lolos 2FA. Admin tidak diminta memasukkan kode lagi. Jika akses dicabut ketika halaman masih terbuka, aksi berikutnya ditolak. Pada reveal v5, angka diambil saat **Play** ditekan, lalu animasi berjalan di browser tanpa request per angka.

Annotation 2 **Untuk pemilih**, frasa saya sebelumnya, “pada setiap aksi”, terlalu luas. Lebih tepatnya, saat **membuka QR, memulai sesi, mengecek izin perangkat, atau menyimpan suara**, server membaca ID browser dari cookie `HttpOnly` atau sesi yang menyertai request tersebut. ID yang sama digunakan kembali. Tidak ada request khusus untuk menanyakan identitas browser.

| Tombol di bilik suara | Perilaku pada branch sekarang |
|---|---|
| **Pilih calon** atau pindah ke calon lain | Sorotan kartu dan pilihan sementara berubah langsung di browser. Klik ini sendiri tidak mengirim request atau menyimpan suara. |
| **Lihat visi & misi** | Popup langsung terbuka dari data yang sudah dimuat. Ada satu request di belakang layar untuk menandai calon sudah dibaca, termasuk saat popup yang sama dibuka ulang. Menutup popup sepenuhnya lokal. |
| **Lanjut ke konfirmasi** | Jika ada visi dan misi yang belum dibuka, browser langsung memberi tahu. Jika lengkap, satu request memvalidasi syarat v5 dan mengikat calon yang akan dikonfirmasi. |
| **Ubah pilihan** | Saat ini mengirim satu request untuk membatalkan konfirmasi sebelumnya. Sebelum hardening, perpindahan tampilan ini lokal. Di jaringan lambat, perbedaannya bisa terasa. |
| **Konfirmasi pilihan** | Satu request menyimpan suara. Server memeriksa calon yang ditinjau, token, browser, masa sesi, dan status hak pilih sebelum menerima suara. |

Untuk pengalaman pengguna yang Anda inginkan, **Ubah pilihan** paling layak dikembalikan menjadi navigasi lokal. Server cukup mengikat ulang calon saat pemilih menekan **Lanjut ke konfirmasi** lagi. Membuka ulang visi dan misi yang penanda bacaannya sudah diterima server juga bisa sepenuhnya lokal. Keduanya tetap memungkinkan server menolak penyimpanan suara jika calon yang dikirim berbeda dari calon yang terakhir dikonfirmasi untuk ditinjau.

Jawaban ini berdasarkan pembacaan kode pada branch saat ini. Dua penyederhanaan terakhir tersebut belum diterapkan, dan saya belum mengukur latensi produksi.