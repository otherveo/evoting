{{-- UI-008: Halaman Detail Pemilihan (Urutan: 1 Identitas -> 2 Paslon -> 3 Jadwal -> 4 Tautan) --}}
<div class="container page view-enter">
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-calendar"></use></svg>
        <span>KONFIGURASI PEMILIHAN · FASE {{ strtoupper(\App\Models\Election::phaseLabel($phase)) }}</span>
      </div>
      <h1>{{ $election->name }}</h1>
      <p class="lede">
        Atur identitas pemilihan, daftar pasangan calon, jadwal pelaksanaan berpresisi menit, serta tautan halaman publik secara berurutan.
      </p>
    </div>
    <div class="heading-actions">
      <a href="{{ route('operator.elections.monitor', $election) }}" wire:navigate class="btn btn-primary">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>Buka Pantau Pemilih</span>
      </a>
      <a href="{{ route('operator.elections.recap', $election) }}" wire:navigate class="btn btn-secondary">
        <svg class="icon"><use href="#i-chart"></use></svg>
        <span>Rekapitulasi</span>
      </a>
      @if($phase !== 'berlangsung')
        <x-action-button
          variant="danger-ghost"
          icon="#i-trash"
          wire:click="openDeleteElectionModal"
          loadingText="Membuka..."
        >
          Hapus Pemilihan
        </x-action-button>
      @endif
    </div>
  </div>

  {{-- Bagian 1 (Identitas), Bagian 2 (Paslon), & Bagian 3 (Jadwal) sesuai hierarki sekuensial --}}
  @include('livewire.operator.partials.detail-info-schedule')

  {{-- Bagian 4: Tautan Halaman Publik & Access Key Rekap Real-Time (Selalu Paling Bawah) --}}
  @include('livewire.operator.partials.detail-public-links')
</div>
