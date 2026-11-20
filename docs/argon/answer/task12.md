Checked task Check JS syntax, clear views, format, and run update-v5 security/UI test
Ran check modified files status
Checked task Check JS syntax, clear views, format, and run update-v5 security/UI test

Semua perubahan kode, pengecekan sintaks JS (`node --check`), pembersihan cache view, dan formatting (`pint`) sudah selesai — saat ini hanya tinggal menunggu pengujian PHPUnit terhadap database Supabase selesai (sedang berjalan di latar belakang) sebelum saya commit dan push ke GitHub.

Jika ingin langsung mencoba di `localhost:8000`, perubahan sudah aktif sekarang (silakan refresh halaman). Begitu pengujian selesai, saya akan langsung commit & push.

Ran commit and push recap-reveal fix and security hardening

Pengujian selesai (83 assertions lulus) dan perbaikan sudah di-commit serta di-push ke GitHub (`origin/main`, commit `5ae487e`).

### Penyebab Daftar Paslon Kosong & Perbaikannya
1. **Pemuatan Skrip pada `wire:navigate` ([election-recap-page.blade.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/resources/views/livewire/operator/election-recap-page.blade.php)):**
   - Sebelumnya `<script src=".../recap-reveal.js">` hanya ditaruh di dalam `@push('styles')`, yang tidak dieksekusi ulang oleh Livewire saat berpindah halaman via `wire:navigate` sehingga `window.createRecapSim` belum terdefinisi dan daftar paslon (`orderedCandidates`) tampil kosong.
   - Kini `recap-reveal.js` (beserta `recap-reveal.css`) dimuat melalui direktif resmi Livewire `@assets ... @endassets` (sambil tetap mempertahankan `@push('styles')` untuk CSS), serta data paslon diteruskan secara aman lewat atribut `data-candidates` + `x-data="window.createRecapSim($el, $wire)"` sehingga selalu berjalan baik pada kunjungan langsung maupun perpindahan `wire:navigate`.

2. **Perlindungan Keamanan & Anti-Kebocoran Data Suara ([ElectionRecapPage.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/app/Livewire/Operator/ElectionRecapPage.php) & [recap-reveal.js](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/public/js/operator/recap-reveal.js)):**
   - **Saat Mode Animasi dibuka (sebelum tombol Play ditekan):** server **hanya** mengirim metadata paslon (`id`, `number`, `formatted_number`, `name`, `photo_url`) ke browser — **tidak ada** `vote_count` maupun persentase suara di HTML, JSON, maupun memori klien. Pengaturan delay dan urutan paslon (*drag & drop* / panah / acak) tetap berjalan instan `0ms` di klien.
   - **Saat tombol Play ditekan:** klien memanggil `fetchRevealResults()` **sekali saja** ke server. Di sisi server, method ini memverifikasi ulang bahwa akun yang login adalah **Operator aktif**, `recap_open === true`, dan syarat pembukaan (`total_voted >= max_voter_rights` atau fase `ditutup`) benar-benar terpenuhi sebelum menarik data dari `evoting.fn_operator_election_results`.
   - **Proteksi Method Livewire:** `openRecap`, `closeRecap`, `setRecapMode`, `backToModeChoice`, dan `fetchRevealResults` seluruhnya dijaga pemeriksaan operator aktif + status kunci rekap, sehingga permintaan palsu saat rekap masih terkunci selalu ditolak tanpa mengembalikan data suara.
   - Seluruh skenario keamanan ini sudah diverifikasi pada [EvotingSystemTest.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/tests/Feature/EvotingSystemTest.php) (`83 assertions passed`).