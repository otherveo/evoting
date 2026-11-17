@props([
    'election',
    'resultsData' => [],
    'leadingCandidates' => [],
    'totalVotesCast' => 0,
    'eyebrow' => 'HASIL PEMILIHAN',
    'subtitle' => '',
    'metaRightLabel' => '',
    'metaRightValue' => '',
    'totalSubtext' => 'Akumulasi seluruh suara anonim yang masuk',
    'winnerNote' => 'Dihitung otomatis dari data suara anonim setelah masa pemilihan selesai.',
    'refreshAction' => null,
])

{{-- FR-020, FR-022, AC-023, AC-025: Rekapitulasi perolehan suara per paslon setelah masa pemilihan Ditutup --}}
<section class="results-hero" aria-labelledby="results-hero-title">
  <div class="results-hero-copy">
    <span class="public-status is-closed">
      <span class="public-status-dot"></span>
      <span>Masa Pemilihan Selesai · Hasil Terbuka</span>
    </span>

    <div class="eyebrow">
      <svg class="icon"><use href="#i-trophy"></use></svg>
      <span>{{ $eyebrow }}</span>
    </div>

    <h1 id="results-hero-title">{{ $election->name }}</h1>
    <p class="results-lede">{{ $subtitle }}</p>

    <div class="results-hero-meta">
      <span>
        <svg class="icon"><use href="#i-calendar"></use></svg>
        Waktu Selesai: <strong>{{ $election->formattedSchedule($election->ends_at) }}</strong>
      </span>
      @if($metaRightValue !== '')
        <span>
          <svg class="icon"><use href="#i-shield"></use></svg>
          {{ $metaRightLabel }}: <strong>{{ $metaRightValue }}</strong>
        </span>
      @endif
    </div>
  </div>

  <div class="results-hero-total">
    <span>TOTAL SUARA SAH</span>
    <strong>{{ $totalVotesCast }}</strong>
    <small>{{ $totalSubtext }}</small>
  </div>
</section>

@if($totalVotesCast === 0)
  <div class="public-empty-result">
    <svg class="icon"><use href="#i-info"></use></svg>
    <div>
      <strong>Belum ada suara yang tercatat pada pemilihan ini</strong>
      <p>Masa pemilihan telah berakhir dengan total 0 suara tersimpan.</p>
    </div>
  </div>
@elseif(count($leadingCandidates) > 1)
  <div class="public-tie-result">
    <svg class="icon"><use href="#i-trophy"></use></svg>
    <div>
      <strong>Perolehan Suara Tertinggi Seri ({{ $leadingCandidates[0]['vote_count'] }} Suara)</strong>
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
        <span>({{ number_format($winner['share'], 1, ',', '.') }}% dari total {{ $totalVotesCast }} suara sah)</span>
      </div>
      <div class="winner-note">
        <svg class="icon"><use href="#i-shield"></use></svg>
        <span>{{ $winnerNote }}</span>
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
      <p class="panel-sub">Diurutkan berdasarkan nomor urut pasangan calon dari data suara anonim.</p>
    </div>

    @if($refreshAction)
      <x-action-button
        variant="secondary"
        size="xs"
        icon="#i-refresh"
        wire:click="{{ $refreshAction }}"
        loadingText="Memuat..."
      >
        Muat Ulang
      </x-action-button>
    @else
      <span class="result-total-chip">
        <svg class="icon"><use href="#i-check-circle"></use></svg>
        <span>Total {{ $totalVotesCast }} Suara Sah</span>
      </span>
    @endif
  </div>

  <div class="result-list">
    @php
      $maxVotes = collect($resultsData)->max('vote_count') ?? 0;
    @endphp
    @foreach($resultsData as $row)
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
