{{-- UI-013, FR-020, FR-032, BR-017, BR-019, Update v5 Bagian B: Halaman Rekapitulasi & Integritas Operator --}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/operator/recap-reveal.css') }}?v={{ filemtime(public_path('css/operator/recap-reveal.css')) }}" />
@endpush

@assets
  <link rel="stylesheet" href="{{ asset('css/operator/recap-reveal.css') }}?v={{ filemtime(public_path('css/operator/recap-reveal.css')) }}" />
  <link rel="stylesheet" href="{{ asset('css/operator/recap-race.css') }}?v={{ filemtime(public_path('css/operator/recap-race.css')) }}" />
  <script src="{{ asset('js/operator/recap-reveal.js') }}?v={{ filemtime(public_path('js/operator/recap-reveal.js')) }}"></script>
@endassets

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
        <p class="panel-sub">Memastikan jumlah suara anonim sama dengan jumlah Hak Pilih yang terpakai dan tidak melebihi Hak Pilih terdaftar.</p>
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
          <span>Hak Pilih Terpakai</span>
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
          <strong>{{ $integrity['total_voted'] }} / {{ $election->max_voter_rights }} suara terpakai</strong> — seluruh kuota hak pilih harus terpakai, atau akhiri pemilihan lebih awal pada halaman Detail Pemilihan &amp; Jadwal untuk membuka rekapitulasi.
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
            <span>Akhiri Pemilihan</span>
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
      <section
        class="panel mode-choice-panel"
        aria-label="Pilih Mode Tampilan Rekapitulasi"
        x-data="{
          choosingMode: null,
          animationStep: 'type',
          raceDelay: '3000',
          savingRace: false,
          openAnimationPicker() {
            this.animationStep = 'type';
            this.$refs.animationPicker.showModal();
          },
          saveRace(mode) {
            if (this.savingRace) return;
            this.savingRace = true;
            $wire.saveRaceSetup(mode, mode === 'auto' ? this.raceDelay : null).finally(() => {
              this.savingRace = false;
            });
          },
          selectMode(mode) {
            if (this.choosingMode !== null) return;
            this.choosingMode = mode;
            $wire.setRecapMode(mode).catch(() => {
              this.choosingMode = null;
            });
          }
        }"
      >
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
            :class="{ 'is-loading-card': choosingMode === 'biasa' }"
            :disabled="choosingMode !== null"
            x-on:click="selectMode('biasa')"
          >
            <span class="mode-choice-icon">
              <svg class="icon"><use href="#i-chart"></use></svg>
            </span>
            <h3>Mode Biasa (Tabel Langsung)</h3>
            <p>
              Langsung menampilkan ringkasan pemenang, tabel perolehan suara setiap pasangan calon, dan persentase secara penuh dalam satu halaman.
            </p>
            <span class="mode-choice-cta" x-show="choosingMode !== 'biasa'">
              <span>Tampilkan Mode Biasa</span>
              <svg class="icon"><use href="#i-arrow-right"></use></svg>
            </span>
            <span class="mode-choice-cta" x-show="choosingMode === 'biasa'" x-cloak>
              <span class="spinner mode-choice-spinner" aria-hidden="true"></span>
              <span>Memuat Mode Biasa...</span>
            </span>
          </button>

          <button
            type="button"
            class="mode-choice-card is-animasi"
            :class="{ 'is-loading-card': choosingMode === 'animasi' }"
            :disabled="choosingMode !== null"
            x-on:click="openAnimationPicker()"
          >
            <span class="mode-choice-icon">
              <svg class="icon"><use href="#i-sparkles"></use></svg>
            </span>
            <h3>Mode Animasi (Reveal Bertahap)</h3>
            <p>
              Pilih penghitungan bergantian per paslon atau balapan satu suara demi satu.
            </p>
            <span class="mode-choice-cta" x-show="choosingMode !== 'animasi'">
              <span>Siapkan Mode Animasi</span>
              <svg class="icon"><use href="#i-arrow-right"></use></svg>
            </span>
            <span class="mode-choice-cta" x-show="choosingMode === 'animasi'" x-cloak>
              <span class="spinner mode-choice-spinner" aria-hidden="true"></span>
              <span>Menyiapkan Mode Animasi...</span>
            </span>
          </button>
        </div>

        <dialog class="race-picker" aria-labelledby="recap-animation-picker-title" x-ref="animationPicker"
          x-on:cancel="if (savingRace) $event.preventDefault()">
          <div class="modal-header">
            <h2 id="recap-animation-picker-title" x-text="animationStep === 'type' ? 'Pilih Tipe Animasi' : 'Tipe Balapan'">Pilih Tipe Animasi</h2>
            <button type="button" class="icon-btn" aria-label="Tutup pilihan animasi" :disabled="savingRace"
              x-on:click="$refs.animationPicker.close()">
              <svg class="icon"><use href="#i-x"></use></svg>
            </button>
          </div>
          <div class="modal-body">
            <div class="race-picker-options" x-show="animationStep === 'type'">
              <button type="button" class="btn btn-secondary"
                x-on:click="$refs.animationPicker.close(); selectMode('animasi')">Tipe Sekuensial</button>
              <button type="button" class="btn btn-primary" x-on:click="animationStep = 'race'; $nextTick(() => $refs.raceManual.focus())">Tipe Balapan</button>
            </div>
            <div x-show="animationStep === 'race'">
              <p class="field-hint">Buka satu suara per klik atau jalankan reveal dengan jeda yang Anda tentukan.</p>
              <div class="race-picker-options">
                <button type="button" class="btn btn-secondary" x-ref="raceManual" :disabled="savingRace"
                  x-on:click="saveRace('manual')">Reveal Manual</button>
                <button type="button" class="btn btn-primary" :disabled="savingRace"
                  x-on:click="animationStep = 'auto'; $nextTick(() => $refs.initialRaceDelay.focus())">Reveal Otomatis</button>
              </div>
            </div>
            <form x-show="animationStep === 'auto'" x-on:submit.prevent.stop="saveRace('auto')">
              <label class="field-label" for="initial-race-delay">Jeda antar suara (ms)</label>
              <input id="initial-race-delay" class="field-input" type="text" inputmode="numeric"
                required pattern="[0-9]+" x-ref="initialRaceDelay" :value="raceDelay"
                x-on:input="raceDelay = $event.target.value = $event.target.value.replace(/[^0-9]/g, '')"
                aria-describedby="initial-race-delay-hint" />
              <p class="field-hint" id="initial-race-delay-hint">20–10.000 ms. Jeda dapat diubah saat reveal berjalan.</p>
              <button type="submit" class="btn btn-primary"
                :disabled="savingRace || Number(raceDelay) < 20 || Number(raceDelay) > 10000"
                x-text="savingRace ? 'Menyimpan...' : 'Simpan'">Simpan</button>
            </form>
          </div>
          <div class="modal-footer" x-show="animationStep !== 'type'">
            <button type="button" class="btn btn-secondary" :disabled="savingRace"
              x-on:click="animationStep = animationStep === 'auto' ? 'race' : 'type'">Kembali</button>
          </div>
        </dialog>

        {{-- Popup Loading Instan Saat Memproses Pilihan Mode (Teleport ke body agar blur penuh 100% layar) --}}
        <template x-teleport="body">
          <div class="recap-loading-backdrop" x-show="choosingMode !== null" x-cloak role="status" aria-live="polite">
            <div class="recap-loading-popup">
              <span class="recap-loading-ring" aria-hidden="true"></span>
              <strong x-text="choosingMode === 'animasi' ? 'Menyiapkan Mode Animasi...' : 'Memuat Rekapitulasi Mode Biasa...'">Memproses...</strong>
              <p x-text="choosingMode === 'animasi' ? 'Mengambil daftar pasangan calon (tanpa angka suara) dan menyiapkan panel pengaturan animasi.' : 'Menghitung perolehan suara anonim dan menyusun tabel rekapitulasi lengkap.'"></p>
            </div>
          </div>
        </template>
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
      {{-- LANGKAH 4b: Mode Animasi (Hanya metadata paslon dikirim awal; angka suara baru ditarik saat tombol Play ditekan) --}}
      <div
        class="sim-wrap"
        wire:key="sim-anim-root-{{ $election->id }}"
        wire:ignore
        data-candidates="{{ json_encode($revealCandidatesMeta, JSON_UNESCAPED_UNICODE) }}"
        x-data="Object.assign(window.createRecapSim($el, $wire), @js($revealSetup), { savingSetup: false })"
      >
        {{-- Panel Pengaturan Sekuensial Mode Animasi (100% Client-Side, Tanpa Data Suara Sebelum Play) --}}
        <section class="panel" aria-label="Pengaturan Mode Animasi Reveal">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">
                <svg class="icon"><use href="#i-sparkles"></use></svg>
                <span>Pengaturan Animasi Perolehan Suara</span>
              </h2>
              <p class="panel-sub">
                Atur jeda dan susun urutan tampil paslon secara instan di perangkat Anda. Demi keamanan, data perolehan suara <strong>belum dikirim ke browser</strong> dan baru ditarik sekali saat tombol <strong>Play</strong> ditekan.
              </p>
            </div>
            <span class="status-badge" :class="(running || loadingVotes) ? 'is-sedang' : 'is-sudah'">
              <span class="status-dot"></span>
              <span x-text="loadingVotes ? 'Memuat Data Suara...' : (running ? 'Animasi Sedang Berjalan' : 'Siap Diputar')">Siap Diputar</span>
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

            <div class="sim-step-block">
              <div class="sim-play-actions">
                <button
                  type="button"
                  class="btn btn-primary"
                  x-on:click="if (validateDelay()) {
                    savingSetup = true;
                    $wire.saveRevealSetup(delayMs, orderIds).finally(() => { savingSetup = false; });
                  }"
                  :disabled="savingSetup || orderedCandidates.length === 0"
                >
                  <span x-text="savingSetup ? 'Menyimpan...' : 'Simpan'">Simpan</span>
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>
    @endif
  @endif
</div>
