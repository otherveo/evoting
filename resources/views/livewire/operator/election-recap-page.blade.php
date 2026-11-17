<div class="container page view-enter">
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-chart"></use></svg>
        <span>REKAPITULASI &amp; PEMERIKSAAN INTEGRITAS OPERATOR (UI-013)</span>
      </div>
      <h1>{{ $election->name }}</h1>
      <p class="lede">
        Laporan pemeriksaan integritas suara dan rekapitulasi perolehan suara anonim per pasangan calon.
      </p>
    </div>
    <div class="heading-actions">
      <a href="{{ route('operator.elections.monitor', $election) }}" class="btn btn-secondary">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>Pantau Pemilih</span>
      </a>
      <button type="button" class="btn btn-primary" wire:click="refreshData">
        <svg class="icon"><use href="#i-refresh"></use></svg>
        <span>Muat Ulang Data</span>
      </button>
    </div>
  </div>

  {{-- Panel Pemeriksaan Integritas Suara (FR-032, AC-036) --}}
  <section class="panel" style="margin-bottom: 20px;">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-shield"></use></svg>
          <span>Status Integritas Suara (FR-032)</span>
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

  {{-- Sebelum Waktu Selesai: angka per paslon terkunci (BR-026, AC-023) --}}
  @if($phase !== 'ditutup')
    <section class="locked-results" aria-label="Rekapitulasi Belum Tersedia">
      <div class="locked-visual" aria-hidden="true">
        <span class="locked-ring"></span>
        <span class="locked-icon"><svg class="icon"><use href="#i-lock"></use></svg></span>
      </div>

      <div class="eyebrow">
        <svg class="icon"><use href="#i-clock"></use></svg>
        <span>FASE {{ strtoupper(\App\Models\Election::phaseLabel($phase)) }} · HASIL PER PASLON TERKUNCI</span>
      </div>

      <h1>Rekapitulasi Per Paslon Belum Tersedia</h1>
      <p class="locked-lede">
        Sesuai aturan <strong>BR-026</strong>, rincian perolehan suara per pasangan calon tidak ditampilkan kepada siapa pun (termasuk Operator) sebelum masa pemilihan berakhir pada Waktu Selesai.
      </p>

      <div class="locked-schedule">
        <span>
          <svg class="icon"><use href="#i-calendar"></use></svg>
          WAKTU SELESAI PEMILIHAN
        </span>
        <strong>{{ $election->formattedSchedule($election->ends_at) }}</strong>
      </div>

      <div class="locked-actions">
        <button type="button" class="btn btn-primary" wire:click="refreshData">
          <svg class="icon"><use href="#i-refresh"></use></svg>
          <span>Periksa Status Waktu Selesai</span>
        </button>
        <a href="{{ route('operator.elections.monitor', $election) }}" class="btn btn-secondary">
          <svg class="icon"><use href="#i-users"></use></svg>
          <span>Kembali ke Pantau Pemilih</span>
        </a>
      </div>

      <div class="locked-footnote">
        <svg class="icon"><use href="#i-lock"></use></svg>
        <span>Dijaga oleh fungsi basis data <code>evoting.fn_election_results</code>.</span>
      </div>
    </section>
  @else
    {{-- Setelah Waktu Selesai: tampilkan hasil lengkap (FR-020, AC-023) --}}
    <section class="results-hero">
      <div class="results-hero-copy">
        <span class="public-status is-closed">
          <span class="public-status-dot"></span>
          <span>Masa Pemilihan Selesai · Rekapitulasi Sah</span>
        </span>
        <div class="eyebrow">
          <svg class="icon"><use href="#i-trophy"></use></svg>
          <span>REKAPITULASI AKHIR OPERATOR</span>
        </div>
        <h1>{{ $election->name }}</h1>
        <p class="results-lede">
          Masa pemungutan suara telah berakhir. Seluruh angka di bawah dihitung langsung dari tabel suara anonim dan telah terverifikasi terhadap jumlah pemilih yang menyelesaikan sesi.
        </p>
        <div class="results-hero-meta">
          <span>
            <svg class="icon"><use href="#i-calendar"></use></svg>
            Ditutup: <strong>{{ $election->formattedSchedule($election->ends_at) }}</strong>
          </span>
          <span>
            <svg class="icon"><use href="#i-shield"></use></svg>
            Status Integritas: <strong>{{ $integrity['is_consistent'] ? 'Konsisten (100%)' : 'Perlu Pemeriksaan' }}</strong>
          </span>
        </div>
      </div>

      <div class="results-hero-total">
        <span>TOTAL SUARA SAH MASUK</span>
        <strong>{{ $totalVotesCast }}</strong>
        <small>Dari {{ $integrity['total_rights'] }} hak pilih yang diterbitkan</small>
      </div>
    </section>

    @if($totalVotesCast === 0)
      <div class="public-empty-result">
        <svg class="icon"><use href="#i-info"></use></svg>
        <div>
          <strong>Belum ada suara yang masuk pada pemilihan ini</strong>
          <p>Masa pemilihan telah berakhir dengan total 0 suara tersimpan.</p>
        </div>
      </div>
    @elseif(count($leadingCandidates) > 1)
      <div class="public-tie-result">
        <svg class="icon"><use href="#i-trophy"></use></svg>
        <div>
          <strong>Perolehan Suara Tertinggi Seri ({{ $leadingCandidates[0]['vote_count'] }} suara)</strong>
          <p>
            Terdapat {{ count($leadingCandidates) }} pasangan calon dengan perolehan suara tertinggi yang sama:
            <strong>{{ implode(' & ', array_map(fn ($c) => 'Paslon ' . $c['formatted_number'] . ' (' . $c['name'] . ')', $leadingCandidates)) }}</strong>.
          </p>
        </div>
      </div>
    @elseif(count($leadingCandidates) === 1)
      @php $winner = $leadingCandidates[0]; @endphp
      <section class="winner-card" aria-label="Perolehan Suara Tertinggi">
        <div class="winner-copy">
          <span class="winner-kicker">
            <svg class="icon"><use href="#i-trophy"></use></svg>
            <span>PEROLEHAN SUARA TERTINGGI</span>
          </span>
          <div class="winner-label">PASANGAN CALON {{ $winner['formatted_number'] }}</div>
          <h2>{{ $winner['name'] }}</h2>
          <div class="winner-count">
            <strong>{{ $winner['vote_count'] }} Suara</strong>
            <span>({{ number_format($winner['share'], 1, ',', '.') }}% dari total suara sah)</span>
          </div>
          <div class="winner-note">
            <svg class="icon"><use href="#i-shield"></use></svg>
            <span>Dihitung otomatis dari tabel suara anonim setelah Waktu Selesai.</span>
          </div>
        </div>
        <div class="winner-media">
          <img src="{{ $winner['photo_url'] }}" alt="Foto {{ $winner['name'] }}" />
          <span class="winner-stamp">
            <svg class="icon"><use href="#i-check-circle"></use></svg>
            <span>Paslon #{{ $winner['formatted_number'] }}</span>
          </span>
        </div>
      </section>
    @endif

    <section class="panel results-panel" aria-label="Rincian Perolehan Suara Per Paslon">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">
            <svg class="icon"><use href="#i-chart"></use></svg>
            <span>Rincian Perolehan Suara Per Pasangan Calon</span>
          </h2>
          <p class="panel-sub">Diurutkan berdasarkan nomor urut pasangan calon.</p>
        </div>
        <span class="result-total-chip">
          <svg class="icon"><use href="#i-check-circle"></use></svg>
          <span>Total {{ $totalVotesCast }} Suara Sah</span>
        </span>
      </div>

      <div class="result-list">
        @php
          $maxVotes = collect($resultsData ?? [])->max('vote_count') ?? 0;
        @endphp
        @foreach($resultsData ?? [] as $row)
          @php
            $isLeading = $maxVotes > 0 && $row['vote_count'] === $maxVotes;
          @endphp
          <div class="result-row {{ $isLeading ? 'is-leading' : '' }}">
            <div class="result-candidate">
              <span class="result-number">{{ $row['formatted_number'] }}</span>
              <img src="{{ $row['photo_url'] }}" alt="" class="result-thumb" />
              <div class="result-name">
                <span class="result-kicker">PASLON {{ $row['formatted_number'] }}</span>
                <strong>{{ $row['name'] }}</strong>
              </div>
            </div>

            <div class="result-share-block">
              <div class="result-bar" aria-label="Persentase suara {{ $row['name'] }}">
                <span style="width: {{ $row['share'] }}%;"></span>
              </div>
              <span class="result-share-label">{{ number_format($row['share'], 1, ',', '.') }}%</span>
            </div>

            <div class="result-tally">
              <strong>{{ $row['vote_count'] }}</strong>
              <span>suara</span>
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif
</div>
