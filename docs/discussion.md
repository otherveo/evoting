# Discussion — Keputusan Desain Sistem Pemilu

## 1. Model Pemilihan
- Sistem menggunakan **online voting berbasis QR token sekali pakai**.
- Pemilih tidak memerlukan akun atau password permanen.
- Operator menjadi validator identitas pemilih secara langsung.

## 2. Registrasi oleh Operator
- Operator login menggunakan akun khusus.
- Operator memasukkan nama lengkap pemilih.
- Sistem membuat satu record hak pilih dengan ID unik dan QR token.
- Status awal setelah QR dibuat: `Belum memilih`.
- Nama yang sudah didaftarkan dikunci agar tidak dibuatkan hak pilih kedua secara tidak sengaja.
- Jika terdapat nama yang sama, operator harus memastikan bahwa keduanya merupakan orang berbeda sebelum membuat record terpisah.
- Tidak ada kewajiban import daftar pemilih atau integrasi data sekolah.

## 3. QR dan Proses Voting
1. Operator membuat hak pilih; sistem menerbitkan QR dengan status `Belum memilih`.
2. Pemilih memindai QR, membuka halaman voting, lalu menekan tombol `Mulai memilih`.
3. Sistem memvalidasi token dan mengubah status menjadi `Sedang memilih`.
4. Pemilih memilih satu paslon dan menekan tombol `Konfirmasi pilihan`.
5. Sistem menyimpan suara dan mengubah status menjadi `Sudah memilih`.
- QR mengarah ke URL dengan token acak yang sulit ditebak; token tidak memuat nama atau pilihan paslon.
- QR tidak dapat menghasilkan suara lebih dari satu kali.

## 4. Status Hak Pilih
- `Belum memilih`: QR sudah diterbitkan, tetapi pemilih belum memulai proses voting.
- `Sedang memilih`: pemilih sudah menekan `Mulai memilih` dan proses voting telah dimulai.
- `Sudah memilih`: pilihan telah dikonfirmasi dan suara berhasil disimpan.
- Perubahan status harus divalidasi di backend.
- Perubahan `Belum memilih` menjadi `Sedang memilih` harus atomik agar satu token tidak dapat memulai sesi voting secara bersamaan dari beberapa perangkat.
- Penyimpanan suara dan perubahan status menjadi `Sudah memilih` harus dilakukan dalam satu transaksi database.

## 5. Validasi Perangkat
- Sistem mencatat User-Agent, cookie/browser identifier, session, IP address, dan waktu akses yang relevan.
- Jika perangkat/browser terdeteksi pernah digunakan, dashboard operator menampilkan peringatan dan riwayat penggunaan yang tersedia.
- Data perangkat hanya menjadi **indikator/audit**, bukan bukti identitas seseorang dan bukan dasar tunggal untuk menolak pemilih.
- Penggunaan satu perangkat oleh beberapa pemilih tetap diperbolehkan setelah operator melakukan verifikasi langsung.
- Cookie dapat dihapus, browser dapat diganti, dan IP dapat dipakai bersama; karena itu identifikasi perangkat tidak dijamin unik.

## 6. Keamanan Akun Operator
- Hanya operator yang berwenang yang dapat membuat hak pilih dan menangani akses bermasalah.
- Akun operator wajib dilindungi autentikasi yang kuat.
- Aktivitas penting operator dicatat dalam audit log: login, pembuatan hak pilih, penerbitan ulang QR, dan tindakan koreksi.
- Hak akses operator dibatasi sesuai tugasnya.

## 7. Privasi dan Kerahasiaan Suara
- Data identitas/hak pilih dipisahkan dari data pilihan suara.
- QR token tidak memuat identitas maupun pilihan pemilih.
- Operator dapat melihat status hak pilih, tetapi tidak dapat melihat pilihan paslon setiap pemilih.
- Hasil suara hanya dapat dihitung dari data suara anonim.

## 8. Batas Pemilih dan Gelombang
- Admin menetapkan batas maksimum jumlah hak pilih untuk setiap pemilihan.
- Sistem menolak pembuatan hak pilih setelah batas tercapai.
- Gelombang/urutan kelas dikelola secara operasional oleh panitia; tidak harus menjadi fitur khusus sistem pada MVP.
- Jumlah suara sah tidak boleh melebihi jumlah hak pilih yang berhasil dibuat.

## 9. Prinsip Teknis Wajib
- Seluruh keputusan validasi dilakukan di backend, bukan hanya di frontend.
- Token QR dibuat secara kriptografis acak, disimpan secara aman, dan kedaluwarsa/dapat dicabut sesuai kebijakan.
- Endpoint `Mulai memilih` harus memastikan token valid, belum digunakan untuk memulai sesi, dan status masih `Belum memilih`.
- Endpoint konfirmasi harus memastikan status `Sedang memilih` dan pilihan belum pernah disimpan.
- Setiap token hanya boleh menghasilkan satu suara.
