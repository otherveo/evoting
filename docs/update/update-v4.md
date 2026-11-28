# Prompt Update v4 — Pagination monitor + koreksi perilaku wizard pembuatan pemilihan

**Repo:** https://github.com/otherveo/evoting
**Live:** https://smpn7gtlo.myeclipse.my.id
**Commit acuan:** 2cfe3f7 (update-v2 sudah live)
**Catatan penomoran:** v3 sudah selesai — yaitu fitur collapsible shared-device peer list (meringkas daftar nama perangkat bersama di monitor). Fitur itu sudah dikerjakan Argon (commit `6b13b0f`) dan live sejak 2026-11-18 ~14:49. Jadi prompt ini langsung v4.
**Dasar:** update-v1.md §2.2 (wizard sekuensial) + perilaku monitor (`VoterMonitorPage`, `wire:poll` 3 detik).
**File ini:** hanya prompt, bukan coding. Setelah push, Veo yang memberitahu Ryn, Ryn yang pull + deploy.

## Bagian A — Pagination di monitor + jeda poll

### Masalah

Halaman monitor memuat **semua** hak pilih sekaligus (`->get()`, tanpa pagination). Dengan ~200 pemilih, tabelnya sangat panjang. Satu-satunya daftar yang memakai pagination di aplikasi ini adalah log audit admin (25 per halaman).

### Aturan baru (keputusan Veo)

1. Monitor diberi pagination, **25 baris per halaman** (sama seperti log audit).
2. **Real-time hanya bekerja di halaman 1.** Saat operator pindah ke halaman 2 dan seterusnya, poll otomatis (`wire:poll` 3 detik) **dijeda** — data di halaman itu menjadi snapshot dan tidak berubah sendiri. Saat operator kembali ke halaman 1, polling **berlanjut lagi**.
3. Konsekuensi yang diterima: selama di halaman 2+, pemilih baru yang terdaftar, perubahan status, dan permintaan persetujuan perangkat baru **tidak** muncul secara real-time — operator harus kembali ke halaman 1 untuk melihat data terbaru. Popup persetujuan (yang digerakkan oleh poll yang sama) juga tidak muncul selama di halaman 2+.

### Detail implementasi

- Poll hanya dipasang saat halaman yang aktif adalah halaman 1 (mis. direktif `wire:poll` kondisional pada `page == 1`); pindah halaman me-render ulang tanpa poll, kembali ke halaman 1 memasangnya lagi.
- Urutan baris harus **deterministik dan stabil** antar refresh (mis. `created_at` lalu `id`) supaya baris tidak pindah-pindah halaman saat data berubah.
- Pagination harus mempertahankan filter/pencarian yang sedang aktif di monitor (jika ada).
- Cakupan: hanya halaman monitor (`VoterMonitorPage`). Daftar pemilihan tidak diubah (jumlah pemilihan sedikit, tidak butuh pagination).

## Bagian B — Koreksi perilaku wizard pembuatan pemilihan

### Latar: kesalahpahaman yang dikoreksi

Di update-v1.md §2.2, tombol "Saya ingin melengkapinya nanti" ditulis sebagai keluar + batalkan semuanya (tidak ada yang disimpan). Itu **salah** — Veo mengoreksinya dengan 4 aturan di bawah. Aturan-aturan ini **menggantikan** kalimat "keluar = batalkan semuanya" di update-v1.md §2.2. Semua hal lain dari §2.2 (urutan step, validasi per step, tombol Lanjut, tidak ada yang ditulis ke server sebelum konfirmasi akhir — kecuali penyimpanan draft di bawah) tetap berlaku.

### Fakta kode saat ini (sudah diverifikasi)

- Tombol "Saya ingin melengkapinya nanti" saat ini memanggil `closeCreateModal` — handler yang **sama** dengan tombol "Batal" — yaitu menutup wizard tanpa menyimpan apa pun. (State wizard bersifat client-side sampai konfirmasi akhir.)
- Tombol itu tampil di **semua** step, termasuk step 1 (terlihat di screenshot Veo).
- Tombol "Lanjut ke Data Paslon" di step 1 sudah terkunci sampai step 1 valid (`canProceedStep1`).

### 4 aturan baru (keputusan Veo, verbatim)

1. **Di step 1, tombol "Saya ingin melengkapinya nanti" TIDAK ADA.** Tombol itu baru muncul di step selain 1 (step 2, 3, 4).
2. **Jika step 1 kosong, semua operasi/kejadian selain tombol Lanjut akan membatalkan pembuatannya.** Artinya: tombol Batal, tombol X, Escape, klik backdrop, atau navigasi pergi — semuanya membatalkan, tidak ada yang disimpan.
3. **Jika step 1 sudah terisi, tombol "Saya ingin melengkapinya nanti" berfungsi sebagai tombol "draft"** — seperti versi awal yang hanya membuat pemilihan kosong tanpa paslon dll.
4. **Berdasarkan nomor 3: kalau sudah ada isi setidaknya step 1, tombol itu membuat pemilihan dengan data seadanya sesuai yang diinput.** Selanjutnya operator hanya perlu melengkapi data dari halaman konfigurasi.

### Definisi "step 1 sudah terisi"

Step 1 dianggap terisi jika dan hanya jika memenuhi syarat yang sama dengan `canProceedStep1`: **nama pemilihan valid** (wajib, min 3, maks 160 karakter) **dan batas hak pilih valid** (3–10000). Jika salah satunya tidak valid, step 1 dianggap kosong dan berlaku aturan 2.

### Perilaku tombol draft ("Saya ingin melengkapinya nanti", step 2–4)

- Menyimpan pemilihan ke server sebagai **draft** dengan data seadanya: nama + batas hak pilih (wajib valid, sesuai definisi di atas) + **baris paslon yang lengkap** (nama terisi) jika ada yang sudah diinput di step 2 — baris paslon yang kosong/tidak lengkap diabaikan, dan **tidak** ada syarat minimal 2 paslon untuk menyimpan draft.
- Jadwal (step 3) disimpan hanya jika **tanggal mulai dan tanggal selesai keduanya terisi, valid, dan tanggal mulai masih di masa depan**. Jika tidak memenuhi syarat itu, jadwal **tidak** disimpan (dilengkapi nanti dari halaman konfigurasi). Draft **tidak pernah** memulai pemilihan secara otomatis — pilihan "Mulai sekarang" hanya berlaku di konfirmasi akhir step 4, bukan di tombol draft.
- Draft **bukan** status baru: draft hanyalah pemilihan biasa di fase Persiapan dengan data yang belum lengkap (kemungkinan 0 paslon dan tanpa jadwal). Tidak ada kolom/flag draft tambahan.
- Setelah draft tersimpan: tutup wizard dan arahkan operator ke **halaman konfigurasi pemilihan itu** (`operator.elections.detail`), supaya bisa langsung melengkapi data. Tampilkan popup hasil yang singkat (sesuai aturan tombol update-v1: 1–2 kata, mis. "Draft tersimpan").
- Pembuatan draft memakai jalur pembuatan pemilihan yang sama dengan konfirmasi akhir (termasuk kunci anti-duplikat dan audit log dengan actor operator yang membuatnya).

### Perilaku keluar lainnya (Batal, X, Escape, backdrop) — satu aturan yang sama

- Jika step 1 **kosong** → keluar = batalkan, tidak ada yang disimpan (aturan 2).
- Jika step 1 **sudah terisi** → keluar = **simpan sebagai draft** dengan data seadanya, sama seperti tombol "Saya ingin melengkapinya nanti", lalu arahkan ke halaman konfigurasi pemilihan itu. Tidak ada jalan keluar yang membuang data yang sudah terisi. (Draft di fase Persiapan bisa dihapus nanti dari halaman konfigurasi jika ternyata tidak jadi dipakai.)

## Bagian C — Perbaikan UI wizard (popup tidak bisa discroll + layout mobile)

### Masalah (laporan Veo, dari screenshot step 2 di HP)

1. **Popup wizard tidak bisa discroll — di mobile maupun desktop.** Isi wizard (form paslon dst.) melebihi tinggi layar, tapi popup tidak bergulir, sehingga footer (tombol Kembali / Lanjut) tidak terjangkau dan prosesnya mati/tersendat. Veo baru bisa melihat footer itu setelah zoom-out tampilan.
2. **Layout mobile kacau:** indikator step (1 Identitas, 2 Paslon, 3 Jadwal, 4 Tautan) terlipat berantakan jadi 2 baris, judul "Panduan Pembuatan Pemilihan Baru" terpotong/terlipat, tombol "Saya ingin melengkapinya nanti" memenuhi lebar layar.

### Fakta kode saat ini (sudah diverifikasi)

- Komponen modal (`x-modal`, `resources/views/components/modal.blade.php`) **sudah dirancang** untuk bergulir: `.modal` punya `max-height: calc(100dvh - 48px)` + `display: flex; flex-direction: column`, dan `.modal-body` punya `overflow-y: auto`. Tapi dalam praktiknya — di wizard, di mobile maupun desktop — popup tidak bisa discroll. Penyebab pastinya harus dicari di implementasi (kemungkinan: penempatan konten/footer wizard di dalam modal, atau masalah flex/overflow di konten wizard) — bukan sekadar menambah `overflow` baru.
- **Tombol Kembali SUDAH ADA** — tidak perlu dibuat baru. Step 2 punya tombol "Kembali" (`x-on:click="step = 1"`), step 3 punya tombol "Kembali" (`x-on:click="step = 2"`). Veo awalnya mengira tidak ada hanya karena tombol itu tidak terlihat (footer tidak terjangkau karena popup tidak bisa discroll) — dia menemukannya sendiri setelah zoom-out. Step 4 tidak butuh tombol Kembali (pemilihan sudah terbentuk; pengeditan lewat halaman konfigurasi).
- Teks subtitle wizard saat ini berbunyi: "Data tidak disimpan ke server sampai Anda menyelesaikan Langkah 3. Keluar di tengah jalan akan membatalkan seluruh isian." — teks ini **sudah tidak benar** di bawah aturan draft Bagian B (keluar setelah step 1 terisi justru menyimpan draft; hanya keluar saat step 1 kosong yang membatalkan).

### Perubahan

1. **Perbaiki scroll popup wizard (mobile + desktop):** konten wizard harus bisa digulir sampai footer selalu terjangkau tanpa zoom-out. Cari dan perbaiki akar masalahnya (penempatan konten/footer di dalam modal / flex / overflow), jangan hanya menambal dengan CSS baru di atas yang lama. Footer (Kembali / Lanjut / Batal) harus selalu bisa dicapai dengan scroll di semua ukuran layar.
2. **Rapikan layout mobile:** indikator step tidak boleh terlipat berantakan (buat tetap satu baris yang rapi — mis. label dipersingkat atau ukuran diperkecil di layar kecil); judul tidak terpotong; tombol "Saya ingin melengkapinya nanti" tidak memenuhi lebar layar secara janggal. Verifikasi di lebar HP (mis. 360px) dan desktop.
3. **Jangan buat tombol Kembali baru** — yang sudah ada di step 2 dan 3 dipertahankan apa adanya; yang diperbaiki hanya keterjangkauannya (poin 1). Step 4 tetap tanpa tombol Kembali.
4. **Perbarui teks subtitle wizard** agar sesuai aturan draft Bagian B. Makna barunya: keluar setelah Identitas terisi akan menyimpan draft (bisa dilengkapi nanti dari halaman konfigurasi); hanya keluar saat Identitas masih kosong yang membatalkan pembuatan. (Kalimat persisnya disusun Argon, maknanya harus seperti itu.)
5. **Judul tautan keluar dari kotak tautan (step 4).** Saat ini komponen `x-copy-link-box` meletakkan judul (`label`, mis. "1. TAUTAN PUBLIK JUMLAH PEMILIH (TERBUKA KAPAN SAJA)") **di dalam** kotak `.token-box`, bertumpuk langsung di atas URL di dalam `.token-box-copy` yang sama — judul panjang huruf kapital + URL yang membungkus 2 baris berdesakan dalam satu kotak sehingga susah dibaca (apalagi di layar HP). Perbaiki: pindahkan judul **ke luar** kotak, sebagai label/baris tersendiri di atas kotak (dengan jarak yang jelas); kotak hanya berisi URL + tombol aksi (salin / buka di tab baru). Nomor ("1." / "2.") tetap menempel pada judulnya. Komponen ini juga dipakai di halaman konfigurasi (bagian Tautan Halaman Publik) — terapkan perubahan yang sama di semua pemakaiannya supaya konsisten.

## Batasan yang tetap berlaku

- Jangan pernah matikan verifikasi SSL.
- Koneksi database lewat session pooler 5432, user `evoting_app.<ref>`, schema `evoting`. Tidak ada `php artisan migrate` di production. Perubahan skema (jika ada) ditulis di `schema.sql` dan diterapkan langsung ke Supabase (cara yang sama seperti update-v1/v2).
- Jangan pernah regenerate APP_KEY server. TOTP hanya untuk Admin. Sesi pemilih 10 menit + bisa lanjut di browser yang sama.
- Setelah push, Veo yang memberitahu Ryn; Ryn yang pull + deploy. Jangan sentuh file `.env` di server.

## Acceptance checklist

### Pagination monitor

1. Monitor menampilkan 25 baris per halaman dengan kontrol halaman (sebelum/berikut + nomor halaman).
2. Di halaman 1, data me-refresh otomatis tiap 3 detik (pemilih baru muncul tanpa reload manual).
3. Di halaman 2+, **tidak ada** auto-refresh: pemilih baru yang didaftarkan saat operator di halaman 2 tidak muncul sampai operator kembali ke halaman 1.
4. Kembali ke halaman 1 → polling berlanjut lagi (data terbaru muncul).
5. Baris tidak pindah halaman sendiri saat data me-refresh (urutan stabil).
6. Alur persetujuan perangkat di halaman 1 tetap bekerja seperti sebelumnya — tidak ada regresi.

### Wizard

7. Step 1: tombol "Saya ingin melengkapinya nanti" **tidak tampil**. Hanya ada Batal + Lanjut ke Data Paslon.
8. Step 2, 3, 4: tombol "Saya ingin melengkapinya nanti" tampil.
9. Step 1 kosong + klik Batal / X / Escape / backdrop → pembuatan dibatalkan, **tidak ada** pemilihan baru yang terbentuk.
10. Step 1 terisi (nama + kuota valid) + klik "Saya ingin melengkapinya nanti" di step 2/3/4 → pemilihan terbentuk sebagai draft (nama + kuota + paslon lengkap yang sudah diinput; tanpa jadwal kecuali memenuhi syarat di atas), operator diarahkan ke halaman konfigurasi pemilihan itu, popup singkat "Draft tersimpan".
11. Step 1 terisi + klik Batal / X / Escape / backdrop → **sama seperti nomor 10** (draft tersimpan, bukan dibuang).
12. Draft yang hanya berisi step 1 (tanpa paslon, tanpa jadwal) bisa dilengkapi dari halaman konfigurasi (tambah paslon, atur jadwal) dan tetap bisa dihapus (fase Persiapan).
13. Alur wizard lengkap sampai step 4 (konfirmasi akhir) tetap bekerja seperti sebelumnya — tidak ada regresi.

### UI wizard

14. Popup wizard bisa discroll di mobile maupun desktop: dari step 2 yang panjang, operator bisa menggulir sampai footer dan menekan Kembali / Lanjut tanpa zoom-out.
15. Tombol Kembali di step 2 dan 3 terjangkau dan berfungsi (step 2 → kembali ke step 1; step 3 → kembali ke step 2). Tidak ada tombol Kembali ganda/baru.
16. Di lebar HP (mis. 360px): indikator step rapi (tidak terlipat berantakan), judul tidak terpotong, tombol "Saya ingin melengkapinya nanti" tidak memenuhi lebar layar secara janggal.
17. Teks subtitle wizard sudah sesuai aturan draft: menyebut bahwa keluar setelah Identitas terisi menyimpan draft, dan hanya keluar saat Identitas kosong yang membatalkan. Tidak ada lagi kalimat "keluar di tengah jalan akan membatalkan seluruh isian".
18. Tidak ada regresi pada modal lain (modal hapus, modal QR, modal detail) — perbaikan scroll tidak merusak popup lain.
19. Step 4: judul tiap tautan ("1. TAUTAN PUBLIK JUMLAH PEMILIH (TERBUKA KAPAN SAJA)" dan "2. TAUTAN PUBLIK HASIL PEMILIHAN (TERBUKA SETELAH WAKTU SELESAI)") tampil sebagai label tersendiri **di luar** kotak tautan, dengan jarak yang jelas dari kotaknya; kotak hanya berisi URL + tombol salin/buka.
20. Perubahan judul tautan juga berlaku di halaman konfigurasi (bagian Tautan Halaman Publik) — tampilannya konsisten dengan step 4, tombol salin/buka tetap berfungsi.
