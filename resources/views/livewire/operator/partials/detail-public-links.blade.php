{{-- KP-008, OD-014, FR-021, FR-022: Panel Tautan Halaman Publik --}}
<section class="panel" style="margin-bottom: 18px;">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">
        <svg class="icon"><use href="#i-external"></use></svg>
        <span>Tautan Halaman Publik</span>
      </h2>
      <p class="panel-sub">Bagikan kedua tautan ini kepada publik/warga sekolah untuk memantau partisipasi dan hasil akhir.</p>
    </div>
  </div>

  <div class="grid-2col" style="padding: 18px 23px;">
    <x-copy-link-box
      label="1. HALAMAN PUBLIK JUMLAH PEMILIH (TERBUKA KAPAN SAJA)"
      :url="route('public.turnout', $election)"
      copyToast="Tautan Halaman Jumlah Pemilih berhasil disalin."
      style="margin-top: 0;"
    />

    <x-copy-link-box
      label="2. HALAMAN PUBLIK HASIL (TERBUKA SETELAH WAKTU SELESAI)"
      :url="route('public.results', $election)"
      copyToast="Tautan Halaman Hasil berhasil disalin."
      style="margin-top: 0;"
    />
  </div>
</section>
