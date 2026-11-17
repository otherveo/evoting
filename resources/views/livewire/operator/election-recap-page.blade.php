{{-- UI-013, FR-020, FR-032, BR-017, BR-019, BR-026: Halaman Rekapitulasi & Pemeriksaan Integritas Operator --}}
<div class="container page view-enter">
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-chart"></use></svg>
        <span>REKAPITULASI &amp; PEMERIKSAAN INTEGRITAS OPERATOR</span>
      </div>
      <h1>{{ $election->name }}</h1>
      <p class="lede">
        Laporan pemeriksaan integritas suara dan rekapitulasi perolehan suara anonim per pasangan calon.
      </p>
    </div>
    <div class="heading-actions">
      <a href="{{ route('operator.elections.monitor', $election) }}" wire:navigate class="btn btn-secondary">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>Pantau Pemilih</span>
      </a>
      <x-action-button
        variant="primary"
        icon="#i-refresh"
        wire:click="refreshData"
        loadingText="Memuat ulang..."
      >
        Muat Ulang Data
      </x-action-button>
    </div>
  </div>

  {{-- FR-032, BR-019, AC-036: Panel Pemeriksaan Integritas Suara --}}
  <section class="panel" style="margin-bottom: 20px;">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-shield"></use></svg>
          <span>Status Integritas Suara</span>
        </h2>
        <p class="panel-sub">Memastikan jumlah suara anonim sama dengan jumlah Hak Pilih berstatus Sudah memilih dan tidak melebihi Hak Pilih terdaftar.</p>
      </div>
      <span class="status-badge {{ $integrity['is_consistent'] ? 'is-sudah' : 'is-sedang' }}">
        <span class="status-dot"></span>
        <span>{{ $integrity['is_consistent'] ? 'Integritas Konsisten' : 'Integritas Tidak Konsisten' }}</span>
      </span>
    </div>

    <div class="result-summary-grid" style="padding: 18px 23px; margin-top: 0;">
      <div class="result-summary-card">
        <span class="result-summary-icon is-teal"><svg class="icon"><use href="#i-check-circle"></use></svg></span>
        <div>
          <strong>{{ $integrity['total_votes'] }}</strong>
          <span>Total Suara Tersimpan (Anonim)</span>
        </div>
      </div>
      <div class="result-summary-card">
        <span class="result-summary-icon"><svg class="icon"><use href="#i-user-check"></use></svg></span>
        <div>
          <strong>{{ $integrity['total_voted'] }}</strong>
          <span>Hak Pilih Berstatus "Sudah memilih"</span>
        </div>
      </div>
      <div class="result-summary-card">
        <span class="result-summary-icon is-amber"><svg class="icon"><use href="#i-users"></use></svg></span>
        <div>
          <strong>{{ $integrity['total_rights'] }} / {{ $election->max_voter_rights }}</strong>
          <span>Hak Pilih Diterbitkan / Batas Kuota</span>
        </div>
      </div>
    </div>
  </section>

  @if($phase !== 'ditutup')
    <x-locked-results-card
      :eyebrow="'FASE ' . strtoupper(\App\Models\Election::phaseLabel($phase)) . ' · HASIL PER PASLON TERKUNCI'"
      heading="Rekapitulasi Per Paslon Belum Tersedia"
      description="Rincian perolehan suara per pasangan calon tidak ditampilkan kepada siapa pun (termasuk Operator) sebelum masa pemilihan berakhir pada Waktu Selesai."
      :scheduleLabel="$election->formattedSchedule($election->ends_at)"
      footnote="Dijaga secara struktural oleh fungsi basis data."
      refreshAction="refreshData"
      :secondaryUrl="route('operator.elections.monitor', $election)"
      secondaryLabel="Kembali ke Pantau Pemilih"
    />
  @else
    <x-results-breakdown
      :election="$election"
      :resultsData="$resultsData ?? []"
      :leadingCandidates="$leadingCandidates"
      :totalVotesCast="$totalVotesCast"
      eyebrow="REKAPITULASI AKHIR OPERATOR"
      subtitle="Masa pemungutan suara telah berakhir. Seluruh angka di bawah dihitung langsung dari tabel suara anonim dan telah terverifikasi terhadap jumlah pemilih yang menyelesaikan sesi."
      metaRightLabel="Status Integritas"
      :metaRightValue="$integrity['is_consistent'] ? 'Konsisten (100%)' : 'Perlu Pemeriksaan'"
      :totalSubtext="'Dari ' . $integrity['total_rights'] . ' hak pilih yang diterbitkan'"
      winnerNote="Dihitung otomatis dari tabel suara anonim setelah Waktu Selesai."
    />
  @endif
</div>
