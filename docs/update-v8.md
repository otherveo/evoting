# Update v8 — Audit keamanan dan penolakan QR ketiga

## Ruang lingkup

- Audit Fase 0 mengikuti `docs/codex/tasks/Task_002-prompt-hardening-codex.md`. Perubahan hardening dari laporan ditentukan setelah tinjauan terpisah. [Laporan audit](security/AUDIT-2026-11.md)
- V8 mencakup batas perangkat untuk pemilihan resmi dan simulasi, halaman penolakan di bilik, dan peringatan merah di monitor operator. Versi ini tidak menggunakan audio.

## Aturan bilik dan monitor

- Batas dihitung per browser dan pemilihan ketika tautan QR sah dibuka. Refresh QR yang sama tetap dihitung sebagai satu penggunaan. Penggunaan kedua memerlukan persetujuan operator penerbit saat pemilih menekan **Mulai memilih**.
- QR ketiga menampilkan halaman penolakan saat dibuka. Server tidak menambah hitungan pemakaian, membuka sesi, atau mengajukan persetujuan. Pemeriksaan server juga berlaku saat mulai memilih, memeriksa persetujuan, menyetujui, dan mengirim suara. QR tetap dapat dibuka pada browser lain yang memiliki jatah.
- Operator penerbit melihat banner dan label merah **Batas perangkat tercapai** pada pembaruan monitor berikutnya, sekitar tiga detik saat polling aktif. Pembukaan yang berhasil pada browser lain membersihkan peringatan aktif. Riwayat penolakan tetap tersimpan dalam audit.
- Pemilihan resmi memakai cutoff v7. Untuk simulasi yang sudah ada saat v8 diaktifkan, cutoff dibekukan saat penerapan skema agar penggunaan lama tidak mengambil jatah baru. Sesi yang sah sebelum cutoff dapat selesai dalam batas 10 menit. Filter perangkat admin tetap mengecualikan simulasi.

## Penerapan dan verifikasi

Perubahan disiapkan untuk ditinjau di branch `feat/device-limit-v8`. Saat rilis, terapkan `schema.sql` dalam satu transaksi sebelum kode. Penerapan ulang mempertahankan cutoff yang sudah tercatat.

- PostgreSQL lokal dan Laravel. Seluruh 19 tes lulus dengan 431 assertion. Kasus v8 mencakup penolakan QR ketiga, refresh, perebutan jatah terakhir dari dua koneksi, persetujuan lama, dan label monitor yang hilang setelah QR dibuka di browser lain.
- PGlite. Migrasi v7 ke v8 dan penerapan ulang lulus, termasuk penyelesaian sesi simulasi yang aktif sebelum cutoff.
- Chrome lokal pada tampilan lebar dan sempit. Halaman penolakan tampil tanpa dialog atau tombol, dan label monitor memakai warna merah.

Setelah rilis, ulangi uji singkat menggunakan tiga QR sintetis pada pemilihan resmi dan simulasi sebelum memakai v8 untuk pemilihan aktif.
