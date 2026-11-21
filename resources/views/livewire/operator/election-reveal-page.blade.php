@push('styles')
  <link rel="stylesheet" href="{{ asset('css/operator/recap-reveal.css') }}?v={{ filemtime(public_path('css/operator/recap-reveal.css')) }}" />
@endpush

@assets
  <link rel="stylesheet" href="{{ asset('css/operator/recap-reveal.css') }}?v={{ filemtime(public_path('css/operator/recap-reveal.css')) }}" />
  <script src="{{ asset('js/operator/recap-reveal.js') }}?v={{ filemtime(public_path('js/operator/recap-reveal.js')) }}"></script>
@endassets

<div class="container page">
  <div class="page-heading">
    <div>
      <h1>{{ $election->name }}</h1>
      <p class="lede">Tekan <strong>Play</strong> untuk memulai perhitungan. Tekan <kbd>Esc</kbd> atau tombol &times; di popup untuk melewati hitungan paslon yang sedang tampil.</p>
    </div>
    <a href="{{ route('operator.elections.recap', ['election' => $election, 'konfigurasi' => 1]) }}" class="btn btn-secondary">
      Kembali ke konfigurasi
    </a>
  </div>

  <div
    class="sim-wrap"
    wire:ignore
    data-candidates="{{ json_encode($revealCandidatesMeta, JSON_UNESCAPED_UNICODE) }}"
    x-data="Object.assign(window.createRecapSim($el, $wire), @js($revealSetup), { statusText: 'Siap diputar' })"
  >
    {{-- Kanvas Hasil Perhitungan (Daftar Peringkat Animasi FLIP) --}}
    <section class="panel" aria-label="Hasil Perhitungan Peringkat">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">
            <svg class="icon"><use href="#i-trophy"></use></svg>
            <span>Hasil Perhitungan &amp; Peringkat</span>
          </h2>
          <p class="panel-sub">Setiap paslon yang selesai dihitung otomatis mendarat dan menempati peringkat sesuai jumlah suara terbanyak.</p>
        </div>
        <span class="sim-status" x-text="statusText"></span>
      </div>

      <div class="sim-results-body">
        <div class="sim-play-actions">
          <button
            type="button"
            class="btn btn-primary"
            x-on:click="playAnimation()"
            :disabled="running || loadingVotes || orderedCandidates.length === 0"
            :data-loading="loadingVotes ? 'true' : null"
          >
            <span class="btn-label-idle" x-show="!loadingVotes" style="display: inline-flex; align-items: center; gap: 6px;">
              <svg class="icon"><use href="#i-sparkles"></use></svg>
              <span>Play (Mulai Animasi)</span>
            </span>
            <span class="btn-label-loading" x-show="loadingVotes" x-cloak style="display: inline-flex; align-items: center; gap: 6px;">
              <span class="spinner" aria-hidden="true"></span>
              <span>Memuat Suara...</span>
            </span>
          </button>

          <button
            type="button"
            class="btn btn-secondary"
            x-on:click="resetResults()"
            :disabled="running || loadingVotes || !hasResults"
          >
            <svg class="icon"><use href="#i-rotate"></use></svg>
            <span>Reset Hasil</span>
          </button>

          <button
            type="button"
            class="btn btn-danger-ghost"
            x-show="running"
            x-cloak
            x-on:click="abortAnimation()"
          >
            <svg class="icon"><use href="#i-x"></use></svg>
            <span>Hentikan</span>
          </button>
        </div>

        <div class="sim-empty" x-show="!hasResults">
          Hasil perhitungan akan muncul di sini setelah Anda menekan tombol <strong>Play</strong>
        </div>
        <ul class="sim-canvas-list" x-ref="simList"></ul>
      </div>
    </section>
  </div>
</div>
