{{-- UI-013, FR-020, FR-032, BR-017, BR-019, Update v5 Bagian B: Halaman Rekapitulasi & Integritas Operator --}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/operator/recap-reveal.css') }}?v={{ filemtime(public_path('css/operator/recap-reveal.css')) }}" />
  <script src="{{ asset('js/operator/recap-reveal.js') }}?v={{ filemtime(public_path('js/operator/recap-reveal.js')) }}"></script>
@endpush

<div
  class="container page view-enter"
  @if(!$isRecapOpen) wire:poll.3s.visible @endif
>
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-chart"></use></svg>
        <span>REKAPITULASI &amp; PEMERIKSAAN INTEGRITAS OPERATOR</span>
      </div>
      <h1>{{ $election->name }}</h1>
      <p class="lede">
        Laporan pemeriksaan integritas suara dan rekapitulasi perolehan suara anonim per pasangan calon yang dikendalikan oleh operator.
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
          <strong>{{ number_format((int) $integrity['total_votes'], 0, ',', '.') }}</strong>
          <span>Total Suara Tersimpan (Anonim)</span>
        </div>
      </div>
      <div class="result-summary-card">
        <span class="result-summary-icon"><svg class="icon"><use href="#i-user-check"></use></svg></span>
        <div>
          <strong>{{ number_format((int) $integrity['total_voted'], 0, ',', '.') }}</strong>
          <span>Hak Pilih Berstatus "Sudah memilih"</span>
        </div>
      </div>
      <div class="result-summary-card">
        <span class="result-summary-icon is-amber"><svg class="icon"><use href="#i-users"></use></svg></span>
        <div>
          <strong>{{ number_format((int) $integrity['total_rights'], 0, ',', '.') }} / {{ number_format((int) $election->max_voter_rights, 0, ',', '.') }}</strong>
          <span>Hak Pilih Diterbitkan / Batas Kuota</span>
        </div>
      </div>
    </div>
  </section>

  @if(!$isRecapOpen)
    {{-- LANGKAH 1 & 2: Halaman Rekapitulasi Terkunci (Kunci Operator) --}}
    <section class="locked-results" aria-label="Rekapitulasi Per Paslon Terkunci">
      <div class="locked-visual" aria-hidden="true">
        <span class="locked-ring"></span>
        <span class="locked-icon">
          <svg class="icon"><use href="#i-lock"></use></svg>
        </span>
      </div>

      <div class="eyebrow">
        <svg class="icon"><use href="#i-lock"></use></svg>
        <span>FASE {{ strtoupper(\App\Models\Election::phaseLabel($phase)) }} · REKAPITULASI TERKUNCI</span>
      </div>

      <h1>Halaman Rekapitulasi Per Paslon Terkunci</h1>

      @if($canUnlockRecap)
        <p class="locked-lede">
          @if($phase === 'ditutup')
            Pemilihan telah memasuki fase <strong>Ditutup</strong>. Tekan <strong>Buka Rekapitulasi</strong> untuk memilih mode tampilan (Mode Biasa atau Mode Animasi) tanpa langsung membocorkan hasil.
          @else
            Seluruh kuota hak pilih (<strong>{{ $integrity['total_voted'] }} / {{ $election->max_voter_rights }} suara</strong>) telah terpakai. Tekan <strong>Buka Rekapitulasi</strong> untuk memilih mode tampilan hasil.
          @endif
        </p>
      @else
        <p class="locked-lede">
          <strong>{{ $integrity['total_voted'] }} / {{ $election->max_voter_rights }} suara terpakai</strong> — seluruh kuota hak pilih harus terpakai, atau akhiri pemilihan lebih awal pada halaman Konfigurasi &amp; Jadwal untuk membuka rekapitulasi.
        </p>
      @endif

      <div class="locked-schedule">
        <span>
          <svg class="icon"><use href="#i-users"></use></svg>
          <span>STATUS SYARAT BUKA REKAPITULASI</span>
        </span>
        <strong>
          @if($canUnlockRecap)
            Syarat Terpenuhi ({{ $integrity['total_voted'] }} / {{ $election->max_voter_rights }} suara{{ $phase === 'ditutup' ? ' · Pemilihan Ditutup' : '' }})
          @else
            {{ $integrity['total_voted'] }} / {{ $election->max_voter_rights }} suara terpakai — atau akhiri pemilihan untuk membuka
          @endif
        </strong>
      </div>

      @error('recapLock')
        <div class="field-error" role="alert" style="justify-content: center; margin-top: 12px;">
          <svg class="icon"><use href="#i-alert"></use></svg>
          <span>{{ $message }}</span>
        </div>
      @enderror

      <div class="locked-actions">
        <x-action-button
          variant="primary"
          icon="#i-sparkles"
          wire:click="openRecap"
          loadingText="Membuka..."
          :disabled="!$canUnlockRecap"
        >
          Buka Rekapitulasi
        </x-action-button>

        @if(!$canUnlockRecap && $phase !== 'ditutup')
          <a href="{{ route('operator.elections.detail', $election) }}" wire:navigate class="btn btn-secondary">
            <svg class="icon"><use href="#i-calendar"></use></svg>
            <span>Konfigurasi &amp; Akhiri Pemilihan</span>
          </a>
        @endif

        <a href="{{ route('operator.elections.monitor', $election) }}" wire:navigate class="btn btn-secondary">
          <svg class="icon"><use href="#i-users"></use></svg>
          <span>Kembali ke Pantau Pemilih</span>
        </a>
      </div>

      <p class="locked-footnote">
        <svg class="icon"><use href="#i-shield"></use></svg>
        <span>Status buka/tutup halaman rekapitulasi tersimpan dan sinkron di seluruh akun operator.</span>
      </p>
    </section>
  @else
    {{-- Bar Kontrol Rekapitulasi Terbuka --}}
    <div class="recap-control-bar">
      <div class="recap-control-left">
        <span class="status-badge is-sudah">
          <span class="status-dot"></span>
          <span>Rekapitulasi Terbuka</span>
        </span>

        @if($activeViewMode === 'biasa')
          <span class="status-badge is-belum">Mode Biasa</span>
        @elseif($activeViewMode === 'animasi')
          <span class="status-badge is-sedang">Mode Animasi (Reveal)</span>
        @else
          <span class="status-badge is-belum">Langkah 3 · Pilih Mode Tampilan</span>
        @endif
      </div>

      <div class="recap-control-right">
        @if($activeViewMode !== null)
          <x-action-button
            variant="soft"
            size="sm"
            icon="#i-rotate"
            wire:click="backToModeChoice"
            loadingText="Kembali..."
          >
            Ganti Mode
          </x-action-button>
        @endif

        <x-action-button
          variant="secondary"
          size="sm"
          icon="#i-lock"
          wire:click="closeRecap"
          loadingText="Menutup..."
        >
          Tutup Rekapitulasi
        </x-action-button>
      </div>
    </div>

    @if($activeViewMode === null)
      {{-- LANGKAH 3: Pilih Mode Tampilan (Biasa vs Animasi) — Hasil BELUM Ditampilkan (Tanpa Spoiler) --}}
      <section class="panel mode-choice-panel" aria-label="Pilih Mode Tampilan Rekapitulasi">
        <div class="mode-choice-head">
          <div class="eyebrow" style="justify-content: center;">
            <svg class="icon"><use href="#i-sparkles"></use></svg>
            <span>LANGKAH 3 · PILIH MODE TAMPILAN HASIL</span>
          </div>
          <h2>Bagaimana Anda ingin menampilkan hasil rekapitulasi?</h2>
          <p>
            Hasil suara per pasangan calon masih tersembunyi agar tidak bocor sebelum waktunya. Pilih <strong>Mode Biasa</strong> untuk langsung melihat tabel lengkap, atau <strong>Mode Animasi</strong> untuk menampilkan suara satu per satu secara dramatis.
          </p>
        </div>

        <div class="mode-choice-grid">
          <button
            type="button"
            class="mode-choice-card"
            wire:click="setRecapMode('biasa')"
          >
            <span class="mode-choice-icon">
              <svg class="icon"><use href="#i-chart"></use></svg>
            </span>
            <h3>Mode Biasa (Tabel Langsung)</h3>
            <p>
              Langsung menampilkan ringkasan pemenang, tabel perolehan suara setiap pasangan calon, dan persentase secara penuh dalam satu halaman.
            </p>
            <span class="mode-choice-cta">
              <span>Tampilkan Mode Biasa</span>
              <svg class="icon"><use href="#i-arrow-right"></use></svg>
            </span>
          </button>

          <button
            type="button"
            class="mode-choice-card is-animasi"
            wire:click="setRecapMode('animasi')"
          >
            <span class="mode-choice-icon">
              <svg class="icon"><use href="#i-sparkles"></use></svg>
            </span>
            <h3>Mode Animasi (Reveal Bertahap)</h3>
            <p>
              Atur jeda antar angka suara, susun urutan tampil paslon tanpa membocorkan jumlah suara, lalu jalankan animasi hitung-naik dan papan peringkat langsung di browser.
            </p>
            <span class="mode-choice-cta">
              <span>Siapkan Mode Animasi</span>
              <svg class="icon"><use href="#i-arrow-right"></use></svg>
            </span>
          </button>
        </div>
      </section>
    @elseif($activeViewMode === 'biasa')
      {{-- LANGKAH 4a: Mode Biasa (Tabel Rekap Langsung) --}}
      <x-results-breakdown
        :election="$election"
        :resultsData="$resultsData ?? []"
        :leadingCandidates="$leadingCandidates"
        :totalVotesCast="$totalVotesCast"
        eyebrow="REKAPITULASI OPERATOR · MODE BIASA"
        subtitle="Seluruh angka di bawah dihitung langsung dari tabel suara anonim dan telah terverifikasi terhadap jumlah pemilih yang menyelesaikan sesi."
        metaRightLabel="Status Integritas"
        :metaRightValue="$integrity['is_consistent'] ? 'Konsisten (100%)' : 'Perlu Pemeriksaan'"
        :totalSubtext="'Dari ' . $integrity['total_rights'] . ' hak pilih yang diterbitkan'"
        winnerNote="Dihitung otomatis dari tabel suara anonim."
      />
    @else
      {{-- LANGKAH 4b: Mode Animasi (Data Diambil SEKALI, 100% Murni Client-Side Mengikuti Referensi vote-simulation.html) --}}
      <div
        class="sim-wrap"
        wire:key="sim-anim-root-{{ $election->id }}"
        wire:ignore
        x-data="window.createRecapSim(@js($revealCandidates), @js($totalVotesCast))"
      >
        {{-- Panel Pengaturan Sekuensial Mode Animasi (100% Client-Side, Tanpa Reveal Suara) --}}
        <section class="panel" aria-label="Pengaturan Mode Animasi Reveal">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">
                <svg class="icon"><use href="#i-sparkles"></use></svg>
                <span>Pengaturan Animasi Perolehan Suara</span>
              </h2>
              <p class="panel-sub">
                Data suara sudah dimuat ke perangkat Anda. Atur jeda, susun urutan tampil (suara tetap tersembunyi), lalu tekan <strong>Play</strong> — seluruh pengaturan dan animasi berjalan instan tanpa jeda server.
              </p>
            </div>
            <span class="status-badge" :class="running ? 'is-sedang' : 'is-sudah'">
              <span class="status-dot"></span>
              <span x-text="running ? 'Animasi Sedang Berjalan' : 'Siap Diputar (Client-Side)'">Siap Diputar (Client-Side)</span>
            </span>
          </div>

          <div class="sim-setup-body">
            {{-- Langkah 4.1: Pilih jeda antar angka suara --}}
            <div class="sim-step-block">
              <div class="sim-step-head">
                <div class="sim-step-title">
                  <span class="section-num-badge">1</span>
                  <div>
                    <h3>Pilih Jeda Antar Angka Suara</h3>
                    <p>Tentukan jeda waktu (dalam milidetik) untuk setiap penambahan angka saat menghitung naik (20 – 10.000 ms).</p>
                  </div>
                </div>
              </div>

              <div class="sim-delay-row">
                <div class="sim-delay-input-group">
                  <input
                    id="sim-delay-input"
                    type="number"
                    min="20"
                    max="10000"
                    step="10"
                    x-model.number="delayMs"
                    x-on:input="validateDelay()"
                    :disabled="running"
                    class="field-input"
                    :class="{ 'is-invalid': delayError !== '' }"
                    aria-label="Jeda antar angka suara dalam milidetik"
                  />
                  <span class="field-hint" style="margin: 0;">ms per angka</span>
                </div>

                <div class="sim-preset-row" aria-label="Pilihan cepat jeda">
                  <button type="button" class="sim-preset-btn" :class="{ 'is-active': delayMs === 25 }" :disabled="running" x-on:click="setDelayPreset(25)">Cepat (25 ms)</button>
                  <button type="button" class="sim-preset-btn" :class="{ 'is-active': delayMs === 50 }" :disabled="running" x-on:click="setDelayPreset(50)">Sedang (50 ms)</button>
                  <button type="button" class="sim-preset-btn" :class="{ 'is-active': delayMs === 150 }" :disabled="running" x-on:click="setDelayPreset(150)">Normal (150 ms)</button>
                  <button type="button" class="sim-preset-btn" :class="{ 'is-active': delayMs === 300 }" :disabled="running" x-on:click="setDelayPreset(300)">Dramatis (300 ms)</button>
                </div>
              </div>

              <div class="field-error" role="alert" x-show="delayError !== ''" x-cloak>
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span x-text="delayError"></span>
              </div>
            </div>

            {{-- Langkah 4.2: Pilih urutan tampil (tanpa mereveal suaranya) --}}
            <div class="sim-step-block">
              <div class="sim-step-head">
                <div class="sim-step-title">
                  <span class="section-num-badge">2</span>
                  <div>
                    <h3>Pilih Urutan Tampil Paslon</h3>
                    <p>Geser pegangan <code>⋮⋮</code> atau gunakan tombol panah untuk mengatur urutan tampil. Perolehan suara disembunyikan hingga tombol <strong>Play</strong> ditekan.</p>
                  </div>
                </div>

                <button
                  type="button"
                  class="btn btn-sm btn-soft"
                  x-on:click="shuffleOrder()"
                  :disabled="running"
                >
                  <svg class="icon"><use href="#i-sparkles"></use></svg>
                  <span>Acak Urutan</span>
                </button>
              </div>

              <ul
                class="sim-setup"
                :class="{ 'locked': running }"
                x-on:pointermove="onSetupMove($event)"
                x-on:pointerup="onSetupUp()"
                x-on:pointercancel="onSetupUp()"
              >
                <template x-for="(cand, idx) in orderedCandidates" :key="cand.id">
                  <li
                    :data-setup-id="cand.id"
                    :class="{ 'dragging': dragId === String(cand.id) }"
                  >
                    <button
                      type="button"
                      class="sim-handle"
                      :disabled="running"
                      x-on:pointerdown="onHandleDown($event, cand.id)"
                      :aria-label="'Geser urutan ' + cand.name"
                      title="Tahan dan geser untuk mengubah urutan"
                    >⋮⋮</button>
                    <span class="sim-seq" x-text="idx + 1"></span>
                    <img class="sim-photo" :src="cand.photo_url" :alt="cand.name" />
                    <div class="sim-who">
                      <div class="name" x-text="cand.name"></div>
                      <div class="meta" x-text="'Paslon No. ' + cand.formatted_number"></div>
                    </div>
                    <div class="sim-move-btns">
                      <button
                        type="button"
                        class="sim-move-btn"
                        :disabled="running || idx === 0"
                        x-on:click="moveOrder(idx, -1)"
                        title="Naikkan urutan"
                        aria-label="Naikkan urutan"
                      >&uarr;</button>
                      <button
                        type="button"
                        class="sim-move-btn"
                        :disabled="running || idx === orderedCandidates.length - 1"
                        x-on:click="moveOrder(idx, 1)"
                        title="Turunkan urutan"
                        aria-label="Turunkan urutan"
                      >&darr;</button>
                    </div>
                  </li>
                </template>
              </ul>
            </div>

            {{-- Langkah 4.3: Play --}}
            <div class="sim-step-block">
              <div class="sim-play-toolbar">
                <div class="sim-step-title">
                  <span class="section-num-badge">3</span>
                  <div>
                    <h3>Mulai Pemutaran Animasi</h3>
                    <p>Tekan <strong>Play</strong> untuk memulai hitung-naik satu per satu. Tekan <code>&times;</code> atau <code>Esc</code> di popup untuk melompati paslon yang sedang dihitung.</p>
                  </div>
                </div>

                <div class="sim-play-actions">
                  <button
                    type="button"
                    class="btn btn-primary"
                    x-on:click="playAnimation()"
                    :disabled="running || orderedCandidates.length === 0"
                  >
                    <svg class="icon"><use href="#i-sparkles"></use></svg>
                    <span>Play (Mulai Animasi)</span>
                  </button>

                  <button
                    type="button"
                    class="btn btn-secondary"
                    x-on:click="resetResults()"
                    :disabled="running || !hasResults"
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
              </div>
            </div>
          </div>
        </section>

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
            <div class="sim-empty" x-show="!hasResults">
              Hasil perhitungan akan muncul di sini setelah Anda menekan tombol <strong>Play</strong>
            </div>
            <ul class="sim-canvas-list" x-ref="simList"></ul>
          </div>
        </section>
      </div>
    @endif
  @endif
</div>
