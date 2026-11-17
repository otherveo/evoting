<div class="container page public-page">
  <section class="total-hero" aria-labelledby="turnout-heading">
    <div class="total-hero-copy">
      <span class="public-status">
        <span class="snapshot-dot"></span>
        <span>Fase Pemilihan: {{ \App\Models\Election::phaseLabel($phase) }}</span>
      </span>

      <div class="eyebrow">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>TRANSPARANSI PARTISIPASI PUBLIK (UI-018)</span>
      </div>

      <h1 id="turnout-heading">{{ $election->name }}</h1>
      <p>
        Halaman ini menampilkan jumlah keseluruhan pemilih yang telah menyelesaikan pemberian suara (berstatus <code>Sudah memilih</code>) secara agregat dan anonim.
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

  <section class="total-privacy-card" aria-label="Informasi Privasi & Pembaruan">
    <div class="total-privacy-copy">
      <span class="total-privacy-icon"><svg class="icon"><use href="#i-lock"></use></svg></span>
      <div>
        <h2>Jaminan Kerahasiaan &amp; Pembaruan Snapshot</h2>
        <p>
          Sesuai aturan <strong>FR-021, BR-031, dan KP-003</strong>, halaman ini hanya menampilkan satu angka total pemilih yang sudah memilih tanpa menampilkan nama pemilih maupun rincian perolehan per pasangan calon. Tekan tombol di samping untuk memuat ulang angka terbaru.
        </p>
      </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <button type="button" class="btn btn-primary" wire:click="refreshSnapshot" wire:loading.attr="disabled">
        <svg class="icon"><use href="#i-refresh"></use></svg>
        <span>Perbarui Sekarang</span>
      </button>
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
