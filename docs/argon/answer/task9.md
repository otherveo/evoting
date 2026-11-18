Analyzed update-v4.md:L1-122
Analyzed AuditLogPage.php:L1-74
Analyzed audit-log-page.blade.php:L1-144
Analyzed ElectionIndexPage.php:L1-350
Analyzed election-index-page.blade.php:L1-450
Analyzed election-index-page.blade.php:L450-651
Analyzed ElectionIndexPage.php:L350-493
Analyzed modal.blade.php:L1-56
Analyzed copy-link-box.blade.php:L1-38
Analyzed monitor-modals.blade.php:L1-350
Analyzed election-detail-page.blade.php:L1-41
Analyzed detail-public-links.blade.php:L1-257
Analyzed suarasekolah.css:L1800-1920
Analyzed suarasekolah.css:L1920-1960
Analyzed schema.sql:L1130-1211
Analyzed app.blade.php:L295-331
Analyzed VoterMonitorPage.php:L1-100
Edited VoterMonitorPage.php
Edited VoterMonitorPage.php
Edited VoterMonitorPage.php
Edited VoterMonitorPage.php
Edited voter-monitor-page.blade.php
Edited monitor-table.blade.php
Analyzed EvotingSystemTest.php:L475-525
Edited ElectionIndexPage.php
Analyzed ElectionIndexPage.php:L425-475
Edited ElectionIndexPage.php
Edited modal.blade.php
Edited copy-link-box.blade.php
Edited election-index-page.blade.php
Analyzed election-index-page.blade.php:L520-620
Edited election-index-page.blade.php
Analyzed suarasekolah.css:L1635-1705
Analyzed suarasekolah.css:L6180-6300
Edited suarasekolah.css
Edited suarasekolah.css
Edited suarasekolah.css
Edited suarasekolah.css
Edited app.blade.php
Edited voter.blade.php
Analyzed EvotingSystemTest.php:L640-684
Edited EvotingSystemTest.php
Edited EvotingSystemTest.php
Ran format code and run update-v4 feature tests

Pengujian otomatis untuk seluruh perubahan `update-v4.md` (paginasi monitor 25 per halaman + jeda poll di halaman 2+, perilaku simpan draft wizard, perbaikan scroll/mobile UI wizard, dan penempatan label tautan publik) sedang dijalankan. Saya akan melakukan commit dan push setelah pengujian selesai.

Ran commit and push update-v4 changes to GitHub

Seluruh perubahan sesuai [docs/update-v4.md](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/docs/update-v4.md) telah selesai dikerjakan, diverifikasi dengan pengujian otomatis (72 assertions lulus), dan di-push ke GitHub (`origin/main`, commit `edc396b`).

### Ringkasan Perubahan

1. **Bagian A — Pagination Monitor (25 per Halaman) & Jeda Poll di Halaman 2+ ([VoterMonitorPage.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/app/Livewire/Operator/VoterMonitorPage.php), [voter-monitor-page.blade.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/resources/views/livewire/operator/voter-monitor-page.blade.php), [monitor-table.blade.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/resources/views/livewire/operator/partials/monitor-table.blade.php))**
   - Menampilkan **25 baris per halaman** dengan kontrol halaman (`Sebelumnya`, tombol nomor halaman, indikator `Halaman X / Y`, dan `Berikutnya`) sambil mempertahankan status filter dan pencarian.
   - Urutan baris dibuat deterministik dan stabil (`order by vr.created_at desc, vr.id asc`) agar pemilih tidak berpindah halaman saat statusnya berubah.
   - Direktif `wire:poll.3s.visible` serta daftar popup persetujuan perangkat baru hanya aktif saat operator berada di **halaman 1** (`$isPollingPage === true`). Saat pindah ke halaman 2+, auto-refresh dijeda (snapshot) dan otomatis berlanjut lagi ketika kembali ke halaman 1.

2. **Bagian B — Perilaku Draft & Keluar pada Wizard Pembuatan Pemilihan ([ElectionIndexPage.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/app/Livewire/Operator/ElectionIndexPage.php), [election-index-page.blade.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/resources/views/livewire/operator/election-index-page.blade.php), [modal.blade.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/resources/views/components/modal.blade.php))**
   - **Step 1:** Tombol `"Saya ingin melengkapinya nanti"` disembunyikan (`x-show="step > 1"`).
   - **Step 2, 3, 4:** Tombol `"Saya ingin melengkapinya nanti"` ditampilkan.
   - **Satu aturan keluar (tombol `"Saya ingin melengkapinya nanti"`, `"Batal"`, `X`, `Escape`, klik `backdrop`):**
     - Bila **Step 1 kosong/belum valid** → membatalkan pembuatan tanpa menyimpan apa pun ke server.
     - Bila **Step 1 sudah terisi valid** (nama 3–160 karakter & kuota 3–10000) → menyimpan pemilihan sebagai **draft** dengan data seadanya (nama + kuota + paslon yang namanya lengkap tanpa syarat minimal 2 paslon + jadwal hanya bila masa depan & valid), menampilkan toast singkat `"Draft tersimpan"`, dan mengarahkan operator langsung ke halaman konfigurasi pemilihan (`operator.elections.detail`).

3. **Bagian C — Perbaikan Scroll Modal, Tata Letak Mobile Wizard, Subtitle, & Komponen Kotak Tautan ([suarasekolah.css](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/public/css/suarasekolah.css), [copy-link-box.blade.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/resources/views/components/copy-link-box.blade.php))**
   - **Perbaikan Scroll Popup Wizard:** Memperbaiki rantai flex container di dalam `.modal` (`.modal > .modal-shell`, `.modal > form` diberi `display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden;` dan `.modal-body` diberi `flex: 1 1 auto; min-height: 0; overflow-y: auto;`) sehingga konten wizard dapat digulir mulus di mobile maupun desktop dan `.modal-footer` (tombol `Kembali` / `Lanjut`) selalu terjangkau tanpa zoom-out.
   - **Tata Letak Mobile (360px):** Indikator step (`1 Identitas`, `2 Paslon`, `3 Jadwal`, `4 Tautan`) tetap rapi dalam satu baris, judul tidak terpotong, dan tombol `"Saya ingin melengkapinya nanti"` berukuran ringkas di kanan (`width: auto`).
   - **Subtitle Wizard:** Diperbarui sesuai aturan simpan draft baru.
   - **Judul Tautan di Luar Kotak (`x-copy-link-box`):** Memindahkan judul (`1. ...`, `2. ...`) ke elemen `.copy-link-label` di luar/atas kotak `.token-box` baik di Step 4 wizard maupun di halaman konfigurasi pemilihan.