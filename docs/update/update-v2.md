# Prompt Update v2 — Persetujuan perangkat hanya ke operator penerbit QR

**Repo:** https://github.com/otherveo/evoting
**Live:** https://smpn7gtlo.myeclipse.my.id
**Commit acuan:** 339789b (update-v1 sudah live)
**Dasar:** update-v1.md §3.2 (gerbang persetujuan operator) — perubahan ini memperbaiki perilaku popup persetujuan di sana.
**File ini:** hanya prompt, bukan coding. Setelah push, Veo yang memberitahu Ryn, Ryn yang pull + deploy.

## Masalah (laporan Veo)

Saat perangkat yang sama terdeteksi digunakan ulang, popup perizinan muncul di **semua** akun operator yang sedang membuka halaman monitor pemilihan itu. Akibatnya operator lain terganggu (popup yang bukan urusannya) dan bisa salah menyetujui/menolak permintaan yang bukan tanggung jawabnya.

## Aturan baru (keputusan Veo)

Permintaan persetujuan perangkat **hanya** dikirim ke operator yang menerbitkan QR itu. Operator lain tidak melihat popup-nya dan tidak bisa memutuskannya.

## Fakta kode saat ini (sudah diverifikasi)

- Halaman monitor (`VoterMonitorPage`) menampilkan **semua** hak pilih dengan `device_approval_status = 'menunggu'` untuk pemilihan itu — tanpa filter penerbit.
- `fn_create_voter_right` **sudah menerima** `p_operator_id`, tapi hanya dipakai untuk baris audit_logs (`buat_hak_pilih`); baris `voter_rights` **tidak** menyimpan siapa penerbitnya.
- `fn_decide_device_approval` **sudah menerima** `p_operator_id` (dipakai untuk audit), tapi **tidak** memeriksa apakah operator itu penerbitnya — operator mana pun bisa memutuskan.
- Jadi yang kurang: satu kolom penerbit di `voter_rights` + filter di monitor + penegakan di fungsi. Tidak ada perubahan arsitektur.

## Perubahan

### 1. Skema — catat penerbit di `voter_rights`

Tambah kolom `issued_by uuid NULL` ke `evoting.voter_rights` (referensi ke `staff_accounts`; NULL diizinkan). Tulis di `schema.sql` (sumber kebenaran tunggal) **dan** terapkan ke Supabase live dengan cara yang sama seperti update-v1 (jalankan SQL-nya langsung ke Supabase, bukan lewat migrate).

### 2. `fn_create_voter_right` — simpan penerbit

Saat insert baris `voter_rights`, isi `issued_by` dengan `p_operator_id` yang sudah diterima fungsi itu. Tidak ada perubahan lain pada fungsi ini (kunci `FOR UPDATE`, cek fase, cek kuota, token — semua tetap).

### 3. Monitor operator — filter daftar persetujuan pending

Daftar permintaan persetujuan yang memicu popup di halaman monitor difilter: hanya yang `issued_by = id operator yang sedang login` **atau** `issued_by IS NULL`. Baris lama (diterbitkan sebelum perubahan ini, `issued_by` NULL) tetap terlihat oleh **semua** operator supaya tidak ada permintaan yang macet tanpa penanggung jawab.

### 4. `fn_decide_device_approval` — tolak keputusan dari bukan penerbit

Tambah pemeriksaan di awal, setelah cek `not_found`/`wrong_status`: jika `issued_by IS NOT NULL DAN issued_by <> p_operator_id`, kembalikan kode hasil `bukan_penerbit` dengan pesan "Permintaan ini hanya dapat diputuskan oleh operator yang menerbitkan QR." dan jangan ubah apa pun. Jika `issued_by` NULL, operator mana pun boleh memutuskan (kompatibilitas baris lama). Keputusan yang ditolak karena bukan penerbit **tidak** perlu baris audit (itu bukan aksi, hanya penolakan); keputusan yang berhasil tetap tercatat seperti sekarang (actor = operator yang memutuskan).

### 5. Sisi pemilih tidak berubah

Popup "Menunggu persetujuan operator" + polling 3 detik + tombol "Cek izin" tetap seperti update-v1. Tidak ada perubahan di `BallotPage` / `fn_check_device_approval`.

## Perilaku saat penerbit tidak online (sudah diputuskan — jangan diubah sendiri)

**Tidak ada eskalasi otomatis.** Kalau operator penerbit sedang tidak login, permintaan menunggu sampai sesi pemilih 10 menit berakhir (perilaku yang sudah ada di update-v1), lalu pemilih kembali ke awal dan operator bisa menerbitkan QR baru. Eskalasi ke semua operator setelah waktu tertentu **tidak** dibuat di versi ini — itu keputusan terpisah.

## Batasan yang tetap berlaku

- Jangan pernah matikan verifikasi SSL.
- Koneksi database lewat session pooler 5432, user `evoting_app.<ref>`, schema `evoting`. Tidak ada `php artisan migrate` di production. Perubahan skema ditulis di `schema.sql` dan diterapkan langsung ke Supabase (cara yang sama seperti update-v1).
- Jangan pernah regenerate APP_KEY server. TOTP hanya untuk Admin. Sesi pemilih 10 menit + bisa lanjut di browser yang sama.
- Setelah push, Veo yang memberitahu Ryn; Ryn yang pull + deploy. Jangan sentuh file `.env` di server.

## Acceptance checklist

1. Operator A menerbitkan QR; pemilih memakai perangkat bersama → popup persetujuan muncul **hanya** di monitor operator A (saat A membuka monitor pemilihan itu).
2. Operator B membuka monitor pemilihan yang sama → tidak ada popup/daftar untuk permintaan itu.
3. Operator B mencoba memutuskan permintaan A lewat jalur lain (mis. request langsung) → ditolak dengan kode `bukan_penerbit`, status tidak berubah, tidak ada baris audit.
4. Operator A menyetujui → pemilih terbuka (ballot unlock); A menolak → pemilih diarahkan ke halaman dengan teks yang sama seperti update-v1.
5. Baris lama (`issued_by` NULL): permintaan pending-nya terlihat oleh semua operator dan bisa diputuskan siapa pun.
6. Alur satu operator (tanpa operator lain) tetap bekerja seperti sebelumnya — tidak ada regresi.
7. `schema.sql` di repo memuat kolom + perubahan fungsi; perubahan sudah diterapkan ke DB live dan terverifikasi (cek lewat `information_schema` / `pg_proc`).
