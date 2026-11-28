Task — pecah file CSS raksasa jadi file-file terpisah

Tujuan: pecah `public/css/suarasekolah.css` (7.096 baris, 124 KB) menjadi beberapa file CSS yang lebih kecil, masing-masing per fitur/halaman, supaya mudah dirawat. Ini refactor murni — BUKAN ubah tampilan.

File yang dipecah: `public/css/suarasekolah.css` (7.096 baris). `resources/css/app.css` hanya 9 baris, tidak perlu disentuh.

Struktur isi suarasekolah.css (berdasarkan komentar section, urut baris). Peta section TINGKAT ATAS ini yang menentukan urutan cascade — jangan sampai ada yang terlewat saat memecah:
- Design tokens & base (1–205, `:root` pertama di baris 5), Shared header (206–428), Buttons & links (429–657), Page layout & operator dashboard (658–1517), Simulator page (1518–1618), Modal & form (1619–2199), Voter experience (2200–3508), Toasts (3509–3594), Public results & live turnout (3595–4944), Animations (4945–5003), Responsive (5004–5595), Production Refinements (5596–5944, `:root` KEDUA di baris 5600 — token di sini menimpa token awal, jadi harus ikut pindah bersama section ini dan tetap dimuat sesudah base), UI Bug Fixes (5945–6400), Update v1 Styles (6401–7096)

Sub-section di dalam section tingkat atas di atas (urut baris):
- Ambient background (159), Voter list (1076), QR modal (1826), Detail modal (2013), Intro (2353), Ballot (2602), Confirmation & success (3221), State pages (3408), Locked results (3670), Results hero (3806), Result summary (3930), Winner (4000), Result rows (4152), Empty/tie/public notes (4337), Live total page (4394), Public responsive (4656)
- Perbaikan aksesibilitas U2–U8, M2–M9 (5612–5696)
- Auth Card (5696), Election Cards & Grid (5736), Staff & Audit Tables (5843), Modal backdrop (5986), Responsive URL/Token Box (6013), Header Brand Logo & QR Mode Card (6073), Alpine cloak & Page Skeleton (6164), Single-Screen Landing (6208), Password Eye Toggle (6350), Section badges (6429), 4-Step Wizard (6504), Shared Device Indicator (6667), Landing 2-Sided Flow (6775), Access Key Panel & Live Recap Banner (7017)
- 25 @media query dan 7 @keyframes tersebar di dalam section masing-masing.

Yang SUDAH terpisah (jangan diduplikasi, jangan diubah isinya):
- `public/css/operator/recap-race.css` dan `public/css/operator/recap-reveal.css` — sudah file sendiri dan dimuat terpisah. Aturan yang sudah ada di sana tidak boleh ikut pindah ke file baru.
- `public/css/voter/ballot.css` — juga sudah file sendiri (dimuat via `@push` di `livewire/voter/ballot-page.blade.php`). Sama: jangan diduplikasi, jangan diubah isinya.

Aturan yang WAJIB diikuti:
1. Hasil tampilan harus IDENTIK — tidak ada satu pun perubahan visual. Ini refactor murni: pindahkan aturan ke file baru tanpa mengubah nilai, selector, atau urutan cascade-nya.
2. Jaga urutan cascade: file-file baru harus dimuat dengan urutan yang sama seperti urutan section di file lama. Kalau urutan berubah, spesifisitas bisa bergeser dan tampilan ikut berubah. @media dan @keyframes ikut pindah bersama section pemiliknya.
3. Ikuti konvensi Option A yang sudah terkunci: muat file CSS lewat @stack/@push di Blade + versioning `?v={{ filemtime(...) }}` (contoh: `layouts/app.blade.php` baris 17 dan `livewire/operator/election-race-page.blade.php` baris 2). Jangan pakai cara lain.
4. File tetap di `public/css/` dan disajikan langsung — JANGAN pindahkan ke `resources/css/` dan JANGAN pakai `npm run build`. (Animasi/CSS di `public/` memang disajikan tanpa build.)
5. Kelompokkan per fitur/halaman (mis. base, auth, operator, voter/ballot, results, landing, responsive, accessibility). Pembagian pastinya terserah kamu — yang penting tiap file kohesif dan namanya jelas.
6. Setelah pecah, file `suarasekolah.css` yang lama harus HILANG (tidak ada lagi yang memuatnya), dan tidak ada aturan yang tertinggal atau terduplikasi. Cek dengan grep bahwa setiap selector dari file lama ada tepat satu kali di file-file baru. Cek "tidak ada lagi referensi" hanya di kode sumber (Blade/PHP) — file di `storage/framework/views/` adalah hasil compile Blade yang dibuat ulang sendiri saat cache di-clear, dan penyebutan nama file lama di `docs/` (riwayat) tidak perlu dihapus.
7. Jangan sentuh `schema.sql` dan jangan ubah mode keamanan. Ini perubahan CSS + Blade saja.

Git:
- Pull dulu: `git pull origin main` (main sekarang di 4e31fb2 — branch hardening sudah di-merge).
- Kerjakan di branch BARU (mis. `refactor/split-suarasekolah-css`), JANGAN langsung di main.
- Commit + push kalau sudah selesai dan tes lulus, supaya Ryn bisa pull + deploy. Ryn yang deploy (pull + clear cache, tanpa build).

Verifikasi sebelum push:
- Suite tes Laravel + tes JS lulus.
- Bandingkan tampilan sebelum/sesudah di halaman-halaman utama (landing, login, daftar pemilihan, bilik pemilih, monitor operator, hasil/rekap): tidak ada perubahan visual, tidak ada error console, dan tidak ada request CSS 404. Cek juga di Safari (desktop/mobile) dan di viewport sempit — soal CSS yang tidak ke-load di Safari masih jadi utas terbuka, jadi pastikan pemecahan ini tidak menambah masalah di sana.
- Pastikan tidak ada lagi referensi ke `suarasekolah.css` di kode sumber (Blade/PHP).
