# Tipe Balapan untuk Rekapitulasi DigiVoting

## Alur operator

Saat operator memilih **Mode Animasi**, buka popup dengan pilihan **Tipe Sekuensial** dan **Tipe Balapan**. Sekuensial meneruskan alur konfigurasi, **Simpan**, dan **Play** yang sudah ada, dengan animasi saat ini. Balapan membuka pilihan **Manual** atau **Otomatis** sebelum masuk ke layar khusus balapan.

- **Manual** langsung membuka layar balapan tanpa pengaturan jeda. Tombol utamanya **Buka Suara**.
- **Otomatis** meminta jeda awal, lalu operator menekan **Simpan**. Layar balapan menunggu operator menekan **Mulai Reveal**.
- Layar balapan mengikuti `example.png`. Semua paslon tampil sekaligus dalam baris berisi nomor urut, foto, nama, progress bar, persentase, dan jumlah suara. Urutan awal mengikuti nomor paslon. Semua jumlah dan persentase mulai dari nol. Bagian atas menampilkan **Total Suara** yang tetap dan **Suara Tersisa** yang berkurang saat reveal.

## Pemuatan dan pengacakan suara

- Gunakan halaman khusus `/operator/elections/{election}/recap/balapan`. Simpan pilihan manual atau otomatis dan jeda awal di sesi operator untuk pemilihan tersebut. Pembukaan halaman dan pemuatan hasil memakai pemeriksaan akses operator serta syarat rekap terbuka yang sudah ada.
- Setelah halaman balapan terbuka, ambil hasil agregat sekali dan siapkan antrean di browser sebelum mengaktifkan kontrol. Jika pemuatan gagal, tampilkan tombol **Coba Lagi**. Setelah siap, reveal, perpindahan cara pemutaran, dan perubahan kecepatan berjalan di browser.
- Bangun antrean dengan mengulang ID setiap paslon sebanyak suara sahnya, lalu acak dengan Fisher–Yates. Setiap langkah mengambil satu entri. Urutannya sintetis, tanpa waktu masuk atau identitas pemilih. Rekap dengan nol suara langsung menampilkan kondisi selesai.
- Hitung persentase dari **suara paslon yang sudah ditampilkan ÷ total suara sah pemilihan**, tampilkan hingga dua angka desimal dengan format Indonesia, dan gunakan nilai yang sama untuk progress bar. Ketika suara mendarat, paslon naik hanya jika jumlahnya melampaui paslon lain. Jika seri, pertahankan posisi relatif saat itu.

## Animasi dan kontrol pemutaran

- **Manual.** Tiap klik **Buka Suara** mengambil satu suara dan mengurangi **Suara Tersisa**. Kartu tengah muncul dengan ikon orang bertanda tanya. Setelah satu detik, ikon berganti menjadi nomor urut paslon dan teks seperti **Satu Sah!**. Siapkan pelafalan 1–16, termasuk **Sebelas** dan **Enam Belas**, dengan format **Nomor 17 Sah!** dan seterusnya untuk nomor yang lebih besar. Kartu mengecil menjadi lingkaran `+1` dan terbang ke baris tujuan. Setelah mendarat, jumlah, bar, dan peringkat diperbarui. Klik berikutnya tersedia setelah langkah selesai.
- **Otomatis.** Setiap interval mengambil satu suara dan melempar `+1` dari kartu penghitung menuju paslon, tanpa popup tengah. Pada interval cepat, beberapa `+1` boleh terbang bersamaan. Masing-masing tetap mendarat satu kali. Baris berpindah dengan transisi posisi halus, dan `+1` mengikuti baris tujuan yang sedang bergeser.
- **Kecepatan.** Jeda awal berupa digit `0–9` dan divalidasi sebagai bilangan bulat 20–10.000 ms, termasuk untuk input tempel. Sediakan `1×`, `2×`, `4×`, `8×`, dan `16×`. Interval efektif adalah jeda awal dibagi pengali, dibulatkan ke milidetik, dengan **20 ms sebagai batas tercepat**. Perubahan jeda atau pengali berlaku pada suara berikutnya.
- **Ganti cara reveal.** Manual dan otomatis memakai antrean dan papan yang sama. Saat berganti, hentikan penjadwalan lama, selesaikan suara yang sedang bergerak, lalu aktifkan kontrol cara baru. Peralihan pertama dari manual ke otomatis memakai **3000 ms**. Peralihan berikutnya mengingat jeda dan pengali terakhir pada layar tersebut. Balapan selesai setelah antrean kosong dan seluruh `+1` mendarat.
- Jika efek animasi perangkat dimatikan, gunakan gerakan minimal pada Tipe Balapan. Setiap suara tetap mengikuti langkah reveal satu per satu, termasuk jeda satu detik di mode manual.

## Verifikasi dan pengiriman

- Uji dengan hasil `22/34/44`, nilai seri, paslon nol suara, dan pemilihan nol suara. Pastikan setiap suara dialokasikan tepat sekali dan hasil akhir sama dengan snapshot server.
- Uji klik manual berulang, percepatan yang mencapai batas 20 ms, perubahan kecepatan, dan perpindahan manual ↔ otomatis ketika `+1` masih bergerak. Pastikan penghitung, persentase, dan peringkat tetap konsisten.
- Uji di Chrome desktop dan HP, termasuk saat efek gerakan perangkat dimatikan. Periksa satu permintaan pemuatan hasil, kontrol siap setelah data dimuat, dan akses ditolak saat rekap terkunci. Jalankan regresi Sekuensial untuk konfirmasi alur **Simpan → Play** tetap berfungsi.
- Kerjakan di branch baru `feat/recap-race` untuk ditinjau sebelum merge dan push. Konfigurasi memakai sesi dan hasil memakai agregat yang sudah tersedia, sehingga fitur ini tidak memerlukan migrasi database.
