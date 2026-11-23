{{-- UI-018, FR-021, BR-031, KP-003, KP-008, AC-024: Halaman Publik Jumlah Pemilih --}}
<div class="container page public-page">
  <section class="total-hero" aria-labelledby="turnout-heading">
    <div class="total-hero-copy">
      <span class="public-status">
        <span class="snapshot-dot"></span>
        <span>Fase Pemilihan: {{ \App\Models\Election::phaseLabel($phase) }}</span>
      </span>

      <div class="eyebrow">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>TRANSPARANSI PARTISIPASI PUBLIK</span>
      </div>

      <h1 id="turnout-heading">{{ $election->name }}</h1>
      <p>
        Jumlah ini menunjukkan berapa banyak orang yang sudah memilih. Pilihan setiap orang tetap rahasia.
      </p>

      <div class="total-period">
        <svg class="icon"><use href="#i-calendar"></use></svg>
        <span>
          Jadwal Pemilihan: <strong>{{ $election->formattedSchedule($election->starts_at) }}</strong> s.d. <strong>{{ $election->formattedSchedule($election->ends_at) }}</strong>
        </span>
      </div>
    </div>

    <div class="total-board" aria-live="polite">
      <div class="total-board-label">
        <span class="snapshot-dot" aria-hidden="true"></span>
        <span>TOTAL PEMILIH SUDAH MEMILIH</span>
      </div>
      <div class="total-number">{{ $totalVoted }}</div>
      <div class="total-board-sub">Suara telah masuk dan tercatat secara sah</div>
      <div class="total-board-foot" style="margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.16);">
        <span>Data Snapshot (Muat Ulang Manual)</span>
        <span>{{ $loadedAtLabel }}</span>
      </div>
    </div>
  </section>

  {{-- FR-021, BR-031, KP-003: Hanya menampilkan satu angka total tanpa nama & tanpa rincian paslon; refresh manual --}}
  <section class="total-privacy-card" aria-label="Informasi Privasi & Pembaruan">
    <div class="total-privacy-copy">
      <span class="total-privacy-icon"><svg class="icon"><use href="#i-lock"></use></svg></span>
      <div>
        <h2>Jaminan Kerahasiaan &amp; Pembaruan Snapshot</h2>
        <p>
          Halaman ini hanya menampilkan satu angka total pemilih yang sudah memilih tanpa menampilkan nama pemilih maupun rincian perolehan per pasangan calon. Tekan tombol di samping untuk memuat ulang angka terbaru.
        </p>
      </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <x-action-button
        variant="primary"
        icon="#i-refresh"
        wire:click="refreshSnapshot"
        loadingText="Memperbarui..."
      >
        Perbarui Sekarang
      </x-action-button>

      <a href="{{ route('public.results', $election) }}" class="btn btn-secondary">
        <svg class="icon"><use href="#i-trophy"></use></svg>
        <span>Halaman Hasil</span>
      </a>
    </div>
  </section>

  <div class="public-updated">
    <span>
      <svg class="icon"><use href="#i-clock"></use></svg>
      <span>Waktu pemuatan data terakhir: <strong>{{ $loadedAtLabel }}</strong></span>
    </span>
    <button type="button" class="link-button" wire:click="refreshSnapshot" style="color:var(--brand);">
      Muat ulang manual
    </button>
  </div>
</div>
