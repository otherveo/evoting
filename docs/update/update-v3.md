# Prompt Update v3 — DRAFT (menunggu persetujuan Veo) — Ringkas tampilan indikator perangkat bersama

**SUPERSEDED 2026-11-18 ~14:49: fitur ini sudah dikerjakan Argon (commit 6b13b0f, "style(monitor): make shared-device peer list collapsible") dan sudah live — draft ini tidak pernah dikirim ke Argon. Disimpan sebagai arsip saja.**

**Repo:** https://github.com/otherveo/evoting
**Live:** https://smpn7gtlo.myeclipse.my.id
**Commit acuan:** 339789b (update-v1 sudah live; update-v2 sudah ditulis, belum tentu sudah dikerjakan Argon)
**Dasar:** update-v1.md §3.1 (indikator perangkat bersama) — perubahan ini merapikan tampilannya, bukan mengubah fungsinya.
**File ini:** hanya prompt, bukan coding. Setelah push, Veo yang memberitahu Ryn, Ryn yang pull + deploy.
**Status:** DRAFT — Veo baru meminta pendapat Ryn (2026-11-18 ~14:19 WITA), belum menyetujui prompt ini. Jangan kirim ke Argon sebelum Veo bilang ya.

## Masalah (temuan Veo, dari screenshot monitor)

Di tabel pantau, kotak kuning "Peringatan Perangkat Bersama" di tiap baris menulis ulang **seluruh daftar nama** anggota grup perangkat itu. Contoh dari screenshot Veo: satu perangkat `Chrome Android · #82AE1F` dipakai 3 pemilih — GITA (#13EA0280), FITRAHHHHHH (#2AAD70D5), NAFIA BENAWAN (#3FD8148C) — dan tiap baris menulis 2 nama lainnya lengkap dengan ID hak pilih. Satu fakta ("grup #82AE1F berisi 3 orang ini") muncul 3 kali, sekali per baris. Kalau nanti 5 orang berbagi satu perangkat, tiap baris menulis 4 nama — 20 sebutan untuk satu fakta — dan barisnya jadi tinggi dua kali lipat karena kotak kuning itu.

Nama-nama itu sendiri **bukan** info yang boleh dibuang: operator butuh tahu siapa saja yang berbagi perangkat itu saat memutuskan setuju/tolak. Yang redundan hanya bentuk penyajiannya.

Catatan dari screenshot yang sama: baris-baris satu grup **tidak berurutan** — MUZNAWATY PILOBU (perangkat lain, #D9E777) terselip di antara FITRAHHHHHH dan NAFIA BENAWAN. Jadi solusi "tulis daftar nama sekali di header grup" tidak bisa dipakai tanpa mengacak ulang urutan tabel. Karena itu tiap baris harus tetap mandiri.

## Aturan baru (pendapat Ryn yang diminta Veo — belum dikunci Veo)

Tiap baris cukup menampilkan badge grup + jumlah anggota lain; daftar nama lengkap hanya muncul saat operator memintanya.

## Fakta kode saat ini (dari update-v1 §3.1 — lokasi sudah diverifikasi saat itu, nomor baris bisa bergeser)

- Indikator ini dibangun di update-v1 §3.1: `app/Livewire/Operator/VoterMonitorPage.php` (~baris 357) + `resources/views/livewire/operator/partials/monitor-table.blade.php` (~baris 148) + `resources/views/livewire/operator/partials/monitor-modals.blade.php` (~baris 216).
- Data daftar anggota grup **sudah dimuat** untuk tiap baris (buktinya: nama + ID hak pilih sudah tampil sekarang). Perubahan ini hanya soal tampilan — tidak ada query baru, tidak ada perubahan skema, tidak ada perubahan fungsi database.
- Badge "Grup #82AE1F" sudah ada dan sudah menandai kelompoknya.

## Perubahan

### 1. Tampilan ringkas (default) — kotak kuning per baris

Ganti isi kotak kuning "Peringatan Perangkat Bersama" menjadi:

- Baris judul tetap: "Peringatan Perangkat Bersama" + badge "Grup #82AE1F" (tidak berubah).
- Ganti daftar nama lengkap dengan satu baris ringkas: **"Perangkat ini juga dipakai oleh N orang lain"** — N = jumlah anggota grup dikurangi 1 (diri sendiri tidak dihitung). Contoh: grup 3 orang → "juga dipakai oleh 2 orang lain"; grup 2 orang → "juga dipakai oleh 1 orang lain".
- Baris status di bawah kotak tetap seperti sekarang: "Perangkat Bersama Diizinkan" (atau status persetujuannya apa adanya) — tidak diubah.
- Tinggi baris kembali normal; kotak kuning tidak lagi membuat baris dua kali lipat.

### 2. Daftar lengkap hanya saat diminta (expand)

- Badge "Grup #82AE1F" (atau teks jumlahnya) bisa diklik/diketuk → daftar nama lengkap muncul **di dalam kotak kuning yang sama** (expand inline): nama + ID hak pilih semua anggota lain, **persis data yang sekarang sudah tampil** — tidak ada yang ditambah atau dikurangi.
- Klik/ketuk lagi → daftar tertutup kembali ke tampilan ringkas.
- State expand per baris (membuka satu baris tidak membuka baris lain). Tidak ada perubahan urutan tabel dan tidak ada pengelompokan ulang baris — baris tetap mandiri walau tabel di-sort atau difilter, dan baris perangkat lain yang terselip di tengah grup tetap di tempatnya.

### 3. Yang TIDAK berubah

- Modal detail pemilih (update-v1 §3.1) tetap menampilkan daftar lengkap seperti sekarang.
- Gerbang persetujuan operator (update-v1 §3.2 + update-v2): popup persetujuan, polling 3 detik, tombol "Cek izin", dan aturan "hanya operator penerbit QR" tetap persis seperti sebelumnya. Perubahan ini murni tampilan indikator.
- Tidak ada perubahan skema (`schema.sql`), fungsi database, atau logika deteksi perangkat bersama.

## Batasan yang tetap berlaku

- Jangan pernah matikan verifikasi SSL.
- Koneksi database lewat session pooler 5432, user `evoting_app.<ref>`, schema `evoting`. Tidak ada `php artisan migrate` di production. Perubahan skema ditulis di `schema.sql` dan diterapkan langsung ke Supabase (cara yang sama seperti update-v1) — tapi perubahan ini **tidak butuh perubahan skema sama sekali**.
- Jangan pernah regenerate APP_KEY server. TOTP hanya untuk Admin. Sesi pemilih 10 menit + bisa lanjut di browser yang sama.
- Setelah push, Veo yang memberitahu Ryn; Ryn yang pull + deploy. Jangan sentuh file `.env` di server.

## Acceptance checklist

1. Grup 3 orang (contoh: #82AE1F): tiap baris menampilkan "juga dipakai oleh 2 orang lain", tanpa daftar nama — baris tidak lagi dua kali lipat tingginya.
2. Ketuk badge/jumlah di satu baris → daftar lengkap muncul di kotak itu (2 nama + ID hak pilih, sama persis dengan yang tampil sebelum perubahan). Ketuk lagi → tertutup.
3. Membuka satu baris tidak membuka baris lain dalam grup yang sama.
4. Grup 2 orang: tertulis "juga dipakai oleh 1 orang lain".
5. Baris perangkat tunggal (tanpa peringatan, contoh: MUZNAWATY PILOBU #D9E777) tidak berubah sama sekali.
6. Urutan baris tabel tidak berubah; tidak ada pengelompokan ulang.
7. Modal detail tetap menampilkan daftar lengkap anggota grup.
8. Alur persetujuan perangkat bersama (popup operator, polling pemilih, tombol "Cek izin") tetap bekerja seperti update-v1 + update-v2 — tidak ada regresi.
