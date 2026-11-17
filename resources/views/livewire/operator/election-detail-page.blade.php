{{-- UI-008: Halaman Detail Pemilihan, Jadwal, Paslon, & Tautan Publik --}}
<div class="container page view-enter">
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-calendar"></use></svg>
        <span>KONFIGURASI PEMILIHAN · FASE {{ strtoupper(\App\Models\Election::phaseLabel($phase)) }}</span>
      </div>
      <h1>{{ $election->name }}</h1>
      <p class="lede">
        Atur identitas pemilihan, batas maksimum hak pilih, jadwal pelaksanaan berpresisi menit, serta daftar pasangan calon.
      </p>
    </div>
    <div class="heading-actions">
      <a href="{{ route('operator.elections.monitor', $election) }}" class="btn btn-primary">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>Buka Pantau Pemilih</span>
      </a>
      <a href="{{ route('operator.elections.recap', $election) }}" class="btn btn-secondary">
        <svg class="icon"><use href="#i-chart"></use></svg>
        <span>Rekapitulasi</span>
      </a>
    </div>
  </div>

  {{-- Bagian 1: Identitas Pemilihan, Kuota Hak Pilih, & Pengaturan Jadwal --}}
  @include('livewire.operator.partials.detail-info-schedule')

  {{-- Bagian 2: Tautan Halaman Publik --}}
  @include('livewire.operator.partials.detail-public-links')

  {{-- Bagian 3: Daftar & Modal Pengelolaan Pasangan Calon --}}
  @include('livewire.operator.partials.detail-candidates')
</div>
