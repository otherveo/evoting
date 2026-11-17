@props([
    'eyebrow' => 'HASIL PEMILIHAN TERKUNCI',
    'heading' => 'Hasil Pemilihan Belum Tersedia',
    'description' => '',
    'scheduleLabel' => 'Belum diatur',
    'footnote' => '',
    'refreshAction' => 'refreshSnapshot',
    'secondaryUrl' => null,
    'secondaryLabel' => 'Kembali',
])

{{-- FR-020, FR-022, BR-026, AC-023, AC-025: Sebelum Waktu Selesai, rincian suara per paslon terkunci tanpa angka --}}
<section class="locked-results" aria-label="{{ $heading }}">
  <div class="locked-visual" aria-hidden="true">
    <span class="locked-ring"></span>
    <span class="locked-icon"><svg class="icon"><use href="#i-lock"></use></svg></span>
  </div>

  <div class="eyebrow">
    <svg class="icon"><use href="#i-clock"></use></svg>
    <span>{{ $eyebrow }}</span>
  </div>

  <h1>{{ $heading }}</h1>
  <p class="locked-lede">{!! $description !!}</p>

  <div class="locked-schedule">
    <span>
      <svg class="icon"><use href="#i-calendar"></use></svg>
      DIBUKA SETELAH WAKTU SELESAI
    </span>
    <strong>{{ $scheduleLabel }}</strong>
  </div>

  <div class="locked-actions">
    <x-action-button
      variant="primary"
      icon="#i-refresh"
      wire:click="{{ $refreshAction }}"
      loadingText="Memeriksa jadwal..."
    >
      Periksa &amp; Muat Ulang
    </x-action-button>

    @if($secondaryUrl)
      <a href="{{ $secondaryUrl }}" class="btn btn-secondary">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>{{ $secondaryLabel }}</span>
      </a>
    @endif
  </div>

  @if($footnote !== '')
    <div class="locked-footnote">
      <svg class="icon"><use href="#i-shield"></use></svg>
      <span>{{ $footnote }}</span>
    </div>
  @endif
</section>
