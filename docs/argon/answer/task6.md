Edited app.blade.php
Edited app.blade.php
Edited voter.blade.php
Edited voter.blade.php
Edited suarasekolah.css
Ran clear cache, commit and push form submit button lock fix to GitHub
Listed 0 tasks
Running start local Laravel development server

Penyebabnya sudah ditemukan, diperbaiki, dan langsung di-push ke GitHub (`origin/main`, commit `339789b`).

### Penyebab Masalah
1. Pada implementasi kuncian tombol sebelumnya di [layouts/app.blade.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/resources/views/layouts/app.blade.php), atribut `btn.disabled = true` diset melalui `queueMicrotask` tepat pada event `click`.
2. Menurut spesifikasi HTML/DOM di browser, *microtask* dari event `click` dieksekusi **sebelum** browser menjalankan aksi bawaan tombol `<button type="submit">` (yaitu memicu event `submit` pada `<form wire:submit="login">`).
3. Akibatnya, saat browser hendak men-submit form login, browser melihat tombol submit sudah berstatus `disabled = true` sehingga **membatalkan pengiriman event `submit` secara diam-diam** (Livewire tidak pernah menerima event submit, request tidak pernah dikirim ke server, dan log server tetap kosong).

### Perbaikan yang Dilakukan
1. **Pemisahan Listener `click` vs `submit` ([layouts/app.blade.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/resources/views/layouts/app.blade.php#L218-L270) & [layouts/voter.blade.php](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/resources/views/layouts/voter.blade.php#L95-L120)):**
   - Untuk tombol `<button type="submit">` di dalam `<form>`, penguncian (`data-locked="true"`, `data-loading="true"`, dan `disabled = true` via `setTimeout(..., 0)`) dijalankan pada event **`submit`** setelah Livewire menangkap submit form, sementara klik ke-2 dan seterusnya langsung diblokir pada *capture phase*.
   - Untuk tombol aksi biasa (`type="button"` dengan `wire:click`), penguncian dijalankan pada *bubble phase* tepat setelah Livewire menerima klik pertama.
2. **Indikator Spinner Instan ([suarasekolah.css](file:///c:/Users/Public/Downloads/nmpng/Asobi/project/e-voting/public/css/suarasekolah.css#L6102-L6110)):**
   - Menambahkan aturan CSS `.btn[data-loading] > .btn-label-idle { display: none !important; }` dan `.btn[data-loading] > .btn-label-loading { display: inline-flex !important; }` sehingga begitu tombol ditekan, *spinner* + teks `"Memverifikasi kredensial..."` langsung muncul seketika.