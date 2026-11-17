<div class="container page public-page">
  @if($phase !== 'ditutup')
    {{-- Sebelum Waktu Selesai: Hasil Terkunci Tanpa Angka Apa Pun (FR-022, BR-026, AC-025) --}}
    <section class="locked-results" aria-labelledby="locked-heading">
      <div class="locked-visual" aria-hidden="true">
        <span class="locked-ring"></span>
        <span class="locked-icon"><svg class="icon"><use href="#i-lock"></use></svg></span>
      </div>

      <div class="eyebrow">
        <svg class="icon"><use href="#i-clock"></use></svg>
        <span>HALAMAN PUBLIK HASIL PEMILIHAN (UI-019)</span>
      </div>

      <h1 id="locked-heading">Hasil Pemilihan Belum Tersedia</h1>
      <p class="locked-lede">
        Hasil perolehan suara untuk <strong>{{ $election->name }}</strong> hanya dibuka setelah masa pemilihan berakhir pada Waktu Selesai di bawah ini (FR-022, BR-026).
      </p>

      <div class="locked-schedule">
        <span>
          <svg class="icon"><use href="#i-calendar"></use></svg>
          DIBUKA SETELAH WAKTU SELESAI
        </span>
        <strong>{{ $election->formattedSchedule($election->ends_at) }}</strong>
      </div>

      <div class="locked-actions">
        <button type="button" class="btn btn-primary" wire:click="refreshSnapshot" wire:loading.attr="disabled">
          <svg class="icon"><use href="#i-refresh"></use></svg>
          <span>Muat Ulang Halaman</span>
        </button>
        <a href="{{ route('public.turnout', $election) }}" class="btn btn-secondary">
          <svg class="icon"><use href="#i-users"></use></svg>
          <span>Lihat Jumlah Pemilih</span>
        </a>
      </div>

      <div class="locked-footnote">
        <svg class="icon"><use href="#i-shield"></use></svg>
        <span>Waktu pengecekan: {{ $loadedAtLabel }} · Tidak ada angka suara yang ditampilkan sebelum Waktu Selesai.</span>
      </div>
    </section>
  @else
    {{-- Setelah Waktu Selesai: Tampilkan Hasil Per Paslon & Total Suara (FR-022, AC-025) --}}
    <section class="results-hero" aria-labelledby="results-heading">
      <div class="results-hero-copy">
        <span class="public-status is-closed">
          <span class="public-status-dot"></span>
          <span>Masa Pemilihan Selesai · Hasil Terbuka</span>
        </span>

        <div class="eyebrow">
          <svg class="icon"><use href="#i-trophy"></use></svg>
          <span>HASIL PEMILIHAN PUBLIK (UI-019)</span>
        </div>

        <h1 id="results-heading">{{ $election->name }}</h1>
        <p class="results-lede">
          Masa pemungutan suara telah berakhir. Berikut adalah rekapitulasi perolehan suara sah untuk setiap pasangan calon yang dihitung dari data suara anonim.
        </p>

        <div class="results-hero-meta">
          <span>
            <svg class="icon"><use href="#i-calendar"></use></svg>
            Waktu Selesai: <strong>{{ $election->formattedSchedule($election->ends_at) }}</strong>
          </span>
          <span>
            <svg class="icon"><use href="#i-clock"></use></svg>
            Dimuat pada: <strong>{{ $loadedAtLabel }}</strong>
          </span>
        </div>
      </div>

      <div class="results-hero-total">
        <span>TOTAL SUARA SAH</span>
        <strong>{{ $totalVotesCast }}</strong>
        <small>Akumulasi seluruh suara anonim yang masuk</small>
      </div>
    </section>

    <div class="result-summary-grid">
      <div class="result-summary-card">
        <span class="result-summary-icon is-teal"><svg class="icon"><use href="#i-check-circle"></use></svg></span>
        <div>
          <strong>{{ $totalVotesCast }} Suara</strong>
          <span>Total Suara Sah Masuk</span>
        </div>
      </div>
      <div class="result-summary-card">
        <span class="result-summary-icon"><svg class="icon"><use href="#i-users"></use></svg></span>
        <div>
          <strong>{{ count($resultsData ?? []) }} Paslon</strong>
          <span>Peserta Pemilihan</span>
        </div>
      </div>
      <div class="result-summary-card">
        <span class="result-summary-icon is-amber"><svg class="icon"><use href="#i-calendar"></use></svg></span>
        <div>
          <strong>Ditutup</strong>
          <span>{{ $election->formattedSchedule($election->ends_at) }}</span>
        </div>
      </div>
    </div>

    @if($totalVotesCast === 0)
      <div class="public-empty-result">
        <svg class="icon"><use href="#i-info"></use></svg>
        <div>
          <strong>Tidak ada suara tercatat</strong>
          <p>Masa pemilihan berakhir tanpa ada suara yang masuk.</p>
        </div>
      </div>
    @elseif(count($leadingCandidates) > 1)
      <div class="public-tie-result">
        <svg class="icon"><use href="#i-trophy"></use></svg>
        <div>
          <strong>Perolehan Suara Tertinggi Seri ({{ $leadingCandidates[0]['vote_count'] }} Suara)</strong>
          <p>
            Pasangan calon dengan perolehan suara tertinggi yang sama:
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
            <span>({{ number_format($winner['share'], 1, ',', '.') }}% dari total {{ $totalVotesCast }} suara sah)</span>
          </div>
          <div class="winner-note">
            <svg class="icon"><use href="#i-shield"></use></svg>
            <span>Rekapitulasi sistem dari data suara anonim; penetapan resmi mengikuti berita acara panitia.</span>
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

    <section class="panel results-panel" aria-label="Rincian Perolehan Suara">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">
            <svg class="icon"><use href="#i-chart"></use></svg>
            <span>Perolehan Suara Per Pasangan Calon</span>
          </h2>
          <p class="panel-sub">Data dihitung secara agregat tanpa kaitan dengan identitas pemilih.</p>
        </div>
        <button type="button" class="btn btn-xs btn-secondary" wire:click="refreshSnapshot">
          <svg class="icon"><use href="#i-refresh"></use></svg>
          <span>Muat Ulang</span>
        </button>
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

    <div class="public-note">
      <svg class="icon"><use href="#i-lock"></use></svg>
      <span>
        Seluruh suara disimpan secara anonim pada tabel terpisah tanpa waktu pemungutan dan tanpa relasi ke daftar hak pilih (NFR-005, BR-016).
      </span>
    </div>
  @endif
</div>
