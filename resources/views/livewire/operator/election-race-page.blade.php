@assets
  <link rel="stylesheet" href="{{ asset('css/operator/recap-race.css') }}?v={{ filemtime(public_path('css/operator/recap-race.css')) }}" />
  <script src="{{ asset('js/operator/recap-race.js') }}?v={{ filemtime(public_path('js/operator/recap-race.js')) }}"></script>
@endassets

<div class="container page">
  <div class="page-heading">
    <div>
      <div class="eyebrow"><svg class="icon"><use href="#i-activity"></use></svg> Mode Animasi</div>
      <h1>Tipe Balapan</h1>
      <p class="lede">{{ $election->name }}</p>
    </div>
    <a href="{{ route('operator.elections.recap', $election) }}" class="btn btn-secondary">
      <svg class="icon"><use href="#i-arrow-left"></use></svg>
      <span>Kembali ke Rekapitulasi</span>
    </a>
  </div>

  <div wire:ignore x-data="window.createRecapRace(@js($candidates), @js($raceSetup), $wire)">
    <div class="race-overview">
      <div class="race-number panel">
        <span>Total Suara</span>
        <strong x-text="ready ? formatNumber(total) : '–'">–</strong>
      </div>
      <div class="race-number panel race-number-remaining" x-ref="source">
        <span>Suara Tersisa</span>
        <strong x-text="ready ? formatNumber(remaining) : '–'">–</strong>
      </div>
    </div>

    <section class="panel race-controls" aria-label="Kontrol reveal">
      <p class="race-status" role="status" x-text="status">Memuat data suara…</p>
      <button type="button" class="btn btn-secondary" x-show="failed" x-cloak x-on:click="load()">Coba Lagi</button>

      <div x-show="ready && !complete" x-cloak>
        <div class="race-control-row race-mode-grid" role="group" aria-label="Cara reveal">
          <button type="button" class="btn btn-secondary" x-on:click="switchMode('manual')" :aria-pressed="mode === 'manual'" :disabled="switching">Manual</button>
          <button type="button" class="btn btn-secondary" x-on:click="switchMode('auto')" :aria-pressed="mode === 'auto'" :disabled="switching">Otomatis</button>
        </div>
        <div class="race-control-row" x-show="mode === 'auto'" x-cloak>
          <div class="race-delay">
            <label class="field-label" for="race-delay">Jeda dasar (ms)</label>
            <input id="race-delay" class="field-input" type="text" inputmode="numeric" :value="delayInput"
              :aria-invalid="!validDelay" aria-describedby="race-interval" x-on:input="updateDelay($event)" />
          </div>
          <div class="race-speed" role="group" aria-label="Pengali kecepatan">
            <template x-for="factor in [1, 2, 4, 8, 16]" :key="factor">
              <button type="button" class="btn btn-secondary" :aria-pressed="speed === factor"
                x-text="factor + '×'" x-on:click="setSpeed(factor)"></button>
            </template>
          </div>
          <span id="race-interval" class="field-hint"
            x-text="validDelay ? 'Interval: ' + formatNumber(interval) + ' ms' : 'Masukkan 20–10.000 ms'"></span>
        </div>
        <div class="race-control-row race-action-grid">
          <button type="button" class="btn btn-primary" x-show="mode === 'manual'" x-on:click="openVote()"
            :disabled="switching || inFlight > 0 || remaining === 0">Buka Surat Suara</button>
          <button type="button" class="btn btn-primary" x-show="mode === 'auto'" x-on:click="toggleAuto()"
            :disabled="switching || remaining === 0"
            x-text="autoRunning ? 'Jeda Reveal' : 'Mulai Reveal'">Mulai Reveal</button>
        </div>
      </div>
    </section>

    <section class="panel results-panel" aria-labelledby="race-ranking-title">
      <div class="panel-head">
        <div>
          <h2 id="race-ranking-title" class="panel-title">
            <svg class="icon"><use href="#i-chart"></use></svg>
            <span>Papan Peringkat</span>
          </h2>
        </div>
      </div>
      <ol class="result-list race-list" x-ref="ranking">
        <template x-for="(candidate, index) in ranking" :key="candidate.id">
          <li class="result-row race-row" :class="{ 'is-leading': index === 0 && candidate.votes > 0 }" :data-candidate="candidate.id">
            <div class="result-candidate">
              <span class="result-number" x-text="candidate.formatted_number"></span>
              <img class="result-thumb" :src="candidate.photo_url" alt="" />
              <div class="result-name">
                <span class="result-kicker" x-text="'Paslon ' + candidate.formatted_number"></span>
                <strong x-text="candidate.name"></strong>
              </div>
            </div>
            <div class="result-share-block">
              <div class="result-bar" aria-hidden="true">
                <span :style="{ width: (share(candidate.votes) * 100) + '%' }"></span>
              </div>
              <span class="result-share-label" x-text="formatShare(candidate.votes)"></span>
            </div>
            <div class="result-tally" data-race-target>
              <strong x-text="formatNumber(candidate.votes)">0</strong>
              <span>suara</span>
            </div>
          </li>
        </template>
      </ol>
      <p class="race-empty field-hint" x-show="ranking.length === 0" x-cloak>Belum ada pasangan calon.</p>
    </section>

    <template x-ref="ticket">
      <div class="race-ticket">
        <div class="race-ticket-content">
          <svg class="race-ticket-icon" viewBox="0 0 80 80" fill="none" aria-hidden="true">
            <circle cx="32" cy="24" r="13" fill="currentColor" opacity=".2"/>
            <path d="M8 66v-5a24 24 0 0 1 48 0v5H8Z" fill="currentColor" opacity=".2"/>
            <circle cx="55" cy="42" r="21" fill="currentColor"/>
            <path d="M49 36a6 6 0 0 1 12 0c0 5-6 5-6 10m0 7h.01" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/>
          </svg>
          <strong class="race-ticket-candidate" hidden></strong>
          <span class="race-ticket-label">Suara untuk siapa?</span>
        </div>
      </div>
    </template>
  </div>
</div>
