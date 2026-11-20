{{-- UI-013, FR-020, FR-032, BR-017, BR-019, Update v5 Bagian B: Halaman Rekapitulasi & Integritas Operator --}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/operator/recap-reveal.css') }}?v={{ filemtime(public_path('css/operator/recap-reveal.css')) }}" />
@endpush

<div
  class="container page view-enter"
  @if(!($isRecapOpen && $recapMode === 'animasi' && $revealLocked && $isLocalRevealRunner)) wire:poll.3s.visible @endif
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
    {{-- Update v5 B.1: Halaman Rekapitulasi Terkunci (Kunci Operator) --}}
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
            Pemilihan telah memasuki fase <strong>Ditutup</strong>. Anda sekarang dapat membuka halaman rekapitulasi secara bebas dan menutupnya kembali kapan saja.
          @else
            Seluruh kuota hak pilih (<strong>{{ $integrity['total_voted'] }} / {{ $election->max_voter_rights }} suara</strong>) telah terpakai. Anda sekarang dapat membuka halaman rekapitulasi secara bebas.
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
    {{-- Update v5 B.1 & B.2: Bar Kontrol Rekapitulasi Terbuka (Tutup + Pilih Mode Biasa / Animasi) --}}
    <div class="recap-control-bar">
      <div class="recap-control-left">
        <span class="status-badge is-sudah">
          <span class="status-dot"></span>
          <span>Rekapitulasi Terbuka</span>
        </span>

        <div class="recap-mode-switch" role="tablist" aria-label="Mode Tampilan Rekapitulasi">
          <button
            type="button"
            role="tab"
            aria-selected="{{ $recapMode === 'biasa' ? 'true' : 'false' }}"
            class="recap-mode-btn {{ $recapMode === 'biasa' ? 'is-active' : '' }}"
            wire:click="setRecapMode('biasa')"
          >
            <svg class="icon"><use href="#i-chart"></use></svg>
            <span>Mode Biasa</span>
          </button>
          <button
            type="button"
            role="tab"
            aria-selected="{{ $recapMode === 'animasi' ? 'true' : 'false' }}"
            class="recap-mode-btn {{ $recapMode === 'animasi' ? 'is-active' : '' }}"
            wire:click="setRecapMode('animasi')"
          >
            <svg class="icon"><use href="#i-sparkles"></use></svg>
            <span>Mode Animasi (Reveal)</span>
          </button>
        </div>
      </div>

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

    @if($recapMode === 'biasa')
      {{-- Update v5 B.2: Mode Biasa (x-results-breakdown live) --}}
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
      {{-- Update v5 B.3–B.8: Mode Animasi (Reveal) --}}
      <section class="panel reveal-config-panel" aria-label="Konfigurasi Animasi Reveal">
        <div class="panel-head">
          <div>
            <h2 class="panel-title">
              <svg class="icon"><use href="#i-sparkles"></use></svg>
              <span>Konfigurasi Animasi Reveal</span>
            </h2>
            <p class="panel-sub">
              Susun urutan tampil paslon, pilih mode reveal, dan atur durasi hitung-naik (20–10.000 ms). Setelah dimulai, konfigurasi terkunci hingga ditekan Reset.
            </p>
          </div>
          <span class="status-badge {{ $revealLocked ? 'is-sedang' : 'is-belum' }}">
            <span class="status-dot"></span>
            <span>{{ $revealLocked ? 'Konfigurasi Terkunci (Reveal Aktif)' : 'Siap Dikonfigurasi' }}</span>
          </span>
        </div>

        <div class="reveal-config-body">
          {{-- B.3.1: Urutan tampil paslon --}}
          <div>
            <div class="reveal-config-section-title">
              <div>
                <strong>1. Urutan Tampil Paslon</strong>
                <span>Klik paslon sesuai urutan tampil yang diinginkan, atau gunakan tombol Acak Urutan Tampil.</span>
              </div>
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <x-action-button
                  variant="soft"
                  size="xs"
                  icon="#i-sparkles"
                  wire:key="btn-shuffle-{{ md5(json_encode($savedOrder)) }}-{{ $revealLocked ? '1' : '0' }}"
                  wire:click="shuffleRevealOrder"
                  loadingText="Mengacak..."
                  :disabled="$revealLocked"
                >
                  Acak Urutan Tampil
                </x-action-button>
                <x-action-button
                  variant="secondary"
                  size="xs"
                  icon="#i-rotate"
                  wire:key="btn-clear-order-{{ count($savedOrder) }}-{{ $revealLocked ? '1' : '0' }}"
                  wire:click="clearRevealOrder"
                  loadingText="Mereset..."
                  :disabled="$revealLocked || empty($savedOrder)"
                >
                  Kosongkan Pilihan
                </x-action-button>
              </div>
            </div>

            <div class="reveal-order-grid">
              @foreach($candidatesList as $cand)
                @php
                  $pickedIdx = array_search((string) $cand->id, $savedOrder, true);
                  $isPicked = $pickedIdx !== false;
                @endphp
                <button
                  type="button"
                  class="reveal-order-card {{ $isPicked ? 'is-picked' : '' }}"
                  wire:click="toggleCandidateOrder('{{ $cand->id }}')"
                  wire:key="rev-ord-{{ $cand->id }}"
                  @if($revealLocked) disabled @endif
                >
                  <span class="reveal-order-num">{{ $cand->formattedNumber() }}</span>
                  <div class="reveal-order-info">
                    <strong>{{ $cand->name }}</strong>
                    <small>{{ $isPicked ? 'Dipilih untuk urutan #' . ($pickedIdx + 1) : 'Klik untuk menambahkan ke urutan' }}</small>
                  </div>
                  @if($isPicked)
                    <span class="reveal-order-badge">#{{ $pickedIdx + 1 }}</span>
                  @endif
                </button>
              @endforeach
            </div>
          </div>

          {{-- B.3.3 & B.3.4: Mode Reveal & Delay (20 - 10000 ms) --}}
          <div class="reveal-settings-row">
            <div>
              <div class="reveal-config-section-title" style="margin-bottom: 8px;">
                <strong>2. Mode Reveal</strong>
              </div>
              <div class="reveal-mode-options">
                <button
                  type="button"
                  class="btn btn-sm {{ $revealMode === 'satu' ? 'btn-primary' : 'btn-secondary' }}"
                  wire:click="setRevealMode('satu')"
                  @if($revealLocked) disabled @endif
                >
                  <span>Satu per Satu Paslon</span>
                </button>
                <button
                  type="button"
                  class="btn btn-sm {{ $revealMode === 'semua' ? 'btn-primary' : 'btn-secondary' }}"
                  wire:click="setRevealMode('semua')"
                  @if($revealLocked) disabled @endif
                >
                  <span>Langsung Semua Paslon</span>
                </button>
              </div>
            </div>

            <div>
              <div class="reveal-config-section-title" style="margin-bottom: 8px;">
                <strong>3. Durasi Hitung-Naik per Paslon (20 – 10.000 ms)</strong>
              </div>
              <form wire:submit="saveRevealDelay" class="reveal-delay-box" novalidate>
                <input
                  id="reveal-delay-input"
                  type="number"
                  min="20"
                  max="10000"
                  step="10"
                  wire:model="revealDelayInput"
                  class="field-input @error('revealDelayInput') is-invalid @enderror"
                  @if($revealLocked) disabled @endif
                  aria-label="Durasi delay animasi dalam milidetik"
                />
                <span class="field-hint" style="margin: 0; white-space: nowrap;">ms (maks 10 detik)</span>
                <x-action-button
                  type="submit"
                  variant="secondary"
                  size="sm"
                  target="saveRevealDelay"
                  wire:key="btn-save-delay-{{ $revealDelayMs }}-{{ $revealLocked ? '1' : '0' }}"
                  loadingText="Menyimpan..."
                  :disabled="$revealLocked"
                >
                  Simpan Delay
                </x-action-button>
              </form>
              @error('revealDelayInput')
                <div class="field-error" role="alert">
                  <svg class="icon"><use href="#i-alert"></use></svg>
                  <span>{{ $message }}</span>
                </div>
              @enderror
            </div>
          </div>

          {{-- B.3.5: Tombol Mulai Animasi & Reset --}}
          <div class="reveal-actions-bar">
            <div class="field-hint" style="margin: 0;">
              @if($revealLocked)
                Konfigurasi terkunci selama reveal aktif. Tekan <strong>Reset</strong> untuk membuka kunci dan kembali ke konfigurasi.
              @else
                Urutan aktif: <strong>{{ count($effectiveOrder) }} paslon</strong> · Mode: <strong>{{ $revealMode === 'semua' ? 'Langsung Semua' : 'Satu per Satu' }}</strong> · Delay: <strong>{{ $revealDelayMs }} ms</strong>
              @endif
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
              <x-action-button
                variant="secondary"
                icon="#i-rotate"
                wire:key="btn-reset-reveal-{{ $revealLocked ? '1' : '0' }}"
                wire:click="resetReveal"
                loadingText="Mereset..."
                :disabled="!$revealLocked"
              >
                Reset
              </x-action-button>

              <x-action-button
                variant="primary"
                icon="#i-sparkles"
                wire:key="btn-start-reveal-{{ $revealLocked ? '1' : '0' }}"
                wire:click="startReveal"
                loadingText="Memulai..."
                :disabled="$revealLocked || $candidatesList->isEmpty()"
              >
                Mulai Animasi
              </x-action-button>
            </div>
          </div>
        </div>
      </section>

      @if($revealLocked)
        @if($isLocalRevealRunner)
          {{-- Arena Reveal Interaktif Sisi Klien (Data Diambil SEKALI, Kontrol Kecepatan Lokal 1x/2x/4x) --}}
          <div
            wire:key="reveal-runner-{{ $election->id }}-{{ $revealMode }}-{{ $revealDelayMs }}"
            wire:ignore
            x-data="{
              candidates: @js($revealCandidates),
              order: @js($effectiveOrder),
              initialRevealedIds: @js($revealedIds),
              mode: @js($revealMode),
              delayMs: @js($revealDelayMs),
              totalVotes: @js($totalVotesCast),
              speed: 1,
              isAnimating: false,
              isFinished: false,
              stageCards: [],
              rankedList: [],
              newlyLandedIds: [],
              shiftedDownIds: [],
              rafId: null,
              cancelled: false,

              candById(id) {
                return this.candidates.find(c => String(c.id) === String(id)) || null;
              },

              formatVotes(val) {
                return new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(Number(val) || 0)));
              },

              formatShare(cand) {
                const votes = Number(cand.vote_count) || 0;
                const pct = this.totalVotes > 0 ? (votes / this.totalVotes) * 100 : 0;
                return pct.toFixed(1).replace('.', ',') + '%';
              },

              barWidth(cand) {
                const votes = Number(cand.vote_count) || 0;
                const pct = this.totalVotes > 0 ? (votes / this.totalVotes) * 100 : 0;
                return Math.min(100, Math.max(0, pct)) + '%';
              },

              setSpeed(mult) {
                if ([1, 2, 4].includes(mult)) {
                  this.speed = mult;
                }
              },

              sortRanked(list) {
                return [...list].sort((a, b) => {
                  const diff = (Number(b.vote_count) || 0) - (Number(a.vote_count) || 0);
                  if (diff !== 0) return diff;
                  return (Number(a.number) || 0) - (Number(b.number) || 0);
                });
              },

              landCandidates(candsToLand) {
                const prevRanks = {};
                this.rankedList.forEach((item, idx) => {
                  prevRanks[String(item.id)] = idx;
                });

                const existingIds = new Set(this.rankedList.map(i => String(i.id)));
                const additions = [];
                for (const c of candsToLand) {
                  if (!existingIds.has(String(c.id))) {
                    additions.push({ ...c });
                    existingIds.add(String(c.id));
                  }
                }

                const nextList = this.sortRanked([...this.rankedList, ...additions]);
                const landedIds = additions.map(a => String(a.id));
                const shiftedIds = [];
                nextList.forEach((item, newIdx) => {
                  const sid = String(item.id);
                  if (prevRanks[sid] !== undefined && newIdx > prevRanks[sid]) {
                    shiftedIds.push(sid);
                  }
                });

                this.rankedList = nextList;
                this.newlyLandedIds = landedIds;
                this.shiftedDownIds = shiftedIds;

                setTimeout(() => {
                  this.newlyLandedIds = [];
                  this.shiftedDownIds = [];
                }, 520);

                if (landedIds.length > 0) {
                  $wire.markCandidatesRevealed(landedIds);
                }
              },

              sleepScaled(ms) {
                return new Promise((resolve) => {
                  let elapsed = 0;
                  let last = performance.now();
                  const step = (now) => {
                    if (this.cancelled) {
                      resolve();
                      return;
                    }
                    const dt = Math.max(0, now - last);
                    last = now;
                    elapsed += dt * this.speed;
                    if (elapsed >= ms) {
                      resolve();
                      return;
                    }
                    this.rafId = requestAnimationFrame(step);
                  };
                  this.rafId = requestAnimationFrame(step);
                });
              },

              animateCountUp(cards) {
                return new Promise((resolve) => {
                  const hasPositive = cards.some(c => (Number(c.vote_count) || 0) > 0);
                  if (!hasPositive) {
                    cards.forEach(c => { c.displayCount = 0; });
                    resolve();
                    return;
                  }

                  let progress = 0;
                  let last = performance.now();
                  const duration = Math.max(20, Number(this.delayMs) || 1000);

                  const tick = (now) => {
                    if (this.cancelled) {
                      resolve();
                      return;
                    }
                    const dt = Math.max(0, now - last);
                    last = now;
                    progress = Math.min(1, progress + (dt * this.speed) / duration);

                    for (const card of cards) {
                      const target = Number(card.vote_count) || 0;
                      card.displayCount = target === 0 ? 0 : Math.round(target * progress);
                    }

                    if (progress >= 1) {
                      for (const card of cards) {
                        card.displayCount = Number(card.vote_count) || 0;
                      }
                      resolve();
                      return;
                    }
                    this.rafId = requestAnimationFrame(tick);
                  };

                  this.rafId = requestAnimationFrame(tick);
                });
              },

              async initReveal() {
                this.cancelled = false;
                const alreadySet = new Set(this.initialRevealedIds.map(String));
                const alreadyRevealed = [];
                for (const cid of this.order) {
                  if (alreadySet.has(String(cid))) {
                    const found = this.candById(cid);
                    if (found) alreadyRevealed.push({ ...found });
                  }
                }
                this.rankedList = this.sortRanked(alreadyRevealed);

                const remaining = this.order
                  .filter(cid => !alreadySet.has(String(cid)))
                  .map(cid => this.candById(cid))
                  .filter(Boolean);

                if (remaining.length === 0) {
                  this.isAnimating = false;
                  this.isFinished = true;
                  this.stageCards = [];
                  return;
                }

                this.isAnimating = true;
                this.isFinished = false;

                if (this.mode === 'semua') {
                  this.stageCards = remaining.map(c => ({
                    ...c,
                    displayCount: 0,
                    flying: false,
                  }));
                  await this.animateCountUp(this.stageCards);
                  if (this.cancelled) return;
                  await this.sleepScaled(380);
                  if (this.cancelled) return;
                  this.stageCards.forEach(c => { c.flying = true; });
                  await this.sleepScaled(420);
                  if (this.cancelled) return;
                  this.landCandidates(remaining);
                  this.stageCards = [];
                } else {
                  for (const cand of remaining) {
                    if (this.cancelled) return;
                    const cardObj = {
                      ...cand,
                      displayCount: 0,
                      flying: false,
                    };
                    this.stageCards = [cardObj];
                    if ((Number(cand.vote_count) || 0) > 0) {
                      await this.animateCountUp(this.stageCards);
                    } else {
                      cardObj.displayCount = 0;
                    }
                    if (this.cancelled) return;
                    await this.sleepScaled(360);
                    if (this.cancelled) return;
                    this.stageCards[0].flying = true;
                    await this.sleepScaled(420);
                    if (this.cancelled) return;
                    this.landCandidates([cand]);
                    this.stageCards = [];
                    await this.sleepScaled(220);
                  }
                }

                if (!this.cancelled) {
                  this.isAnimating = false;
                  this.isFinished = true;
                }
              },

              destroy() {
                this.cancelled = true;
                if (this.rafId) cancelAnimationFrame(this.rafId);
              }
            }"
            x-init="initReveal()"
          >
            <div class="reveal-arena" :class="{ 'is-finished': isFinished }">
              {{-- Area Kiri: Daftar Peringkat Perolehan Suara --}}
              <section class="panel" aria-label="Daftar Peringkat Perolehan Suara">
                <div class="panel-head">
                  <div>
                    <h2 class="panel-title">
                      <svg class="icon"><use href="#i-trophy"></use></svg>
                      <span>Daftar Peringkat Perolehan Suara</span>
                    </h2>
                    <p class="panel-sub" x-text="isFinished ? 'Seluruh pasangan calon telah ditampilkan. Peringkat tersusun otomatis dari suara terbanyak.' : 'Pasangan calon yang selesai dihitung otomatis masuk dan menempati peringkat sesuai perolehan suaranya.'"></p>
                  </div>
                  <span class="result-total-chip">
                    <svg class="icon"><use href="#i-check-circle"></use></svg>
                    <span x-text="rankedList.length + ' / ' + candidates.length + ' Paslon · Total ' + formatVotes(totalVotes) + ' Suara'"></span>
                  </span>
                </div>

                <div class="reveal-ranking-list" x-show="rankedList.length > 0">
                  <template x-for="(item, idx) in rankedList" :key="item.id">
                    <div
                      class="reveal-rank-row"
                      :class="{
                        'is-rank-1': idx === 0,
                        'is-newly-landed': newlyLandedIds.includes(String(item.id)),
                        'is-shifted-down': shiftedDownIds.includes(String(item.id))
                      }"
                    >
                      <span class="reveal-rank-badge" x-text="idx + 1"></span>

                      <div class="reveal-rank-main">
                        <div class="reveal-rank-title-line">
                          <span class="reveal-rank-cand-num" x-text="'NO. ' + item.formatted_number"></span>
                          <strong class="reveal-rank-name" x-text="item.name"></strong>
                          <span class="reveal-top-badge" x-show="idx === 0">TERATAS</span>
                        </div>
                        <div class="reveal-rank-bar-track">
                          <span class="reveal-rank-bar-fill" :style="'width:' + barWidth(item)"></span>
                        </div>
                      </div>

                      <div class="reveal-rank-stats">
                        <strong class="reveal-rank-votes" x-text="formatVotes(item.vote_count)"></strong>
                        <span class="reveal-rank-share" x-text="formatShare(item)"></span>
                      </div>
                    </div>
                  </template>
                </div>

                <div class="empty-state" x-show="rankedList.length === 0" style="padding: 36px 20px;">
                  <div class="empty-icon"><svg class="icon"><use href="#i-sparkles"></use></svg></div>
                  <h3>Menunggu hasil paslon pertama</h3>
                  <p>Pasangan calon akan segera masuk ke daftar peringkat setelah animasi hitung-naik selesai.</p>
                </div>
              </section>

              {{-- Area Kanan: Panggung Hitung-Naik (TANPA Progress Bar saat menghitung + Kontrol Kecepatan Lokal 1x/2x/4x) --}}
              <section class="reveal-stage-panel" x-show="!isFinished" aria-label="Panggung Hitung Suara">
                <div class="reveal-stage-top">
                  <span class="reveal-stage-kicker">
                    <svg class="icon" style="width: 14px; height: 14px;"><use href="#i-sparkles"></use></svg>
                    <span x-text="mode === 'semua' ? 'HITUNG SERENTAK' : 'PANGGUNG REVEAL'"></span>
                  </span>

                  {{-- Update v5 B.8: Kontrol kecepatan lokal (1x / 2x / 4x) hanya saat reveal berjalan di halaman ini --}}
                  <div class="reveal-speed-controls" x-show="isAnimating" aria-label="Kontrol Kecepatan Animasi">
                    <span class="reveal-speed-label">Kecepatan</span>
                    <button type="button" class="reveal-speed-btn" :class="{ 'is-active': speed === 1 }" x-on:click="setSpeed(1)">1x</button>
                    <button type="button" class="reveal-speed-btn" :class="{ 'is-active': speed === 2 }" x-on:click="setSpeed(2)">2x</button>
                    <button type="button" class="reveal-speed-btn" :class="{ 'is-active': speed === 4 }" x-on:click="setSpeed(4)">4x</button>
                  </div>
                </div>

                <div class="reveal-stage-cards">
                  <template x-for="card in stageCards" :key="card.id">
                    <div class="reveal-stage-card" :class="{ 'is-flying-to-rank': card.flying }">
                      <img :src="card.photo_url" :alt="card.name" class="reveal-stage-photo" />
                      <span class="reveal-stage-num" x-text="'PASLON NO. ' + card.formatted_number"></span>
                      <h3 class="reveal-stage-name" x-text="card.name"></h3>
                      <div class="reveal-stage-counter" x-text="formatVotes(card.displayCount)"></div>
                      <div class="reveal-stage-unit">SUARA SAH</div>
                    </div>
                  </template>
                </div>
              </section>
            </div>
          </div>
        @else
          {{-- Tampilan Operator Lain (Sinkron Progres Reveal tanpa Kontrol Kecepatan Lokal) --}}
          @php
            $revealedSet = array_flip($revealedIds);
            $viewerRanked = collect($revealCandidates)
              ->filter(fn ($c) => isset($revealedSet[(string) $c['id']]))
              ->sort(fn ($a, $b) => ($b['vote_count'] <=> $a['vote_count']) ?: ($a['number'] <=> $b['number']))
              ->values();
            $viewerFinished = $viewerRanked->count() >= count($revealCandidates) && count($revealCandidates) > 0;
          @endphp
          <section class="panel" aria-label="Daftar Peringkat Perolehan Suara (Sinkron)">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">
                  <svg class="icon"><use href="#i-trophy"></use></svg>
                  <span>Daftar Peringkat Perolehan Suara</span>
                </h2>
                <p class="panel-sub">
                  {{ $viewerFinished ? 'Reveal selesai. Seluruh pasangan calon telah ditampilkan sesuai peringkat suara terbanyak.' : 'Reveal sedang dijalankan oleh operator lain. Daftar peringkat diperbarui otomatis mengikuti progres.' }}
                </p>
              </div>
              <span class="result-total-chip">
                <svg class="icon"><use href="#i-check-circle"></use></svg>
                <span>{{ $viewerRanked->count() }} / {{ count($revealCandidates) }} Paslon · Total {{ number_format($totalVotesCast, 0, ',', '.') }} Suara</span>
              </span>
            </div>

            @if($viewerRanked->isEmpty())
              <div class="empty-state" style="padding: 36px 20px;">
                <div class="empty-icon"><svg class="icon"><use href="#i-sparkles"></use></svg></div>
                <h3>Reveal sedang berlangsung</h3>
                <p>Menunggu pasangan calon pertama selesai dihitung oleh operator yang menjalankan animasi.</p>
              </div>
            @else
              <div class="reveal-ranking-list">
                @foreach($viewerRanked as $rIdx => $rItem)
                  <div class="reveal-rank-row {{ $rIdx === 0 ? 'is-rank-1' : '' }}">
                    <span class="reveal-rank-badge">{{ $rIdx + 1 }}</span>
                    <div class="reveal-rank-main">
                      <div class="reveal-rank-title-line">
                        <span class="reveal-rank-cand-num">NO. {{ $rItem['formatted_number'] }}</span>
                        <strong class="reveal-rank-name">{{ $rItem['name'] }}</strong>
                        @if($rIdx === 0)
                          <span class="reveal-top-badge">TERATAS</span>
                        @endif
                      </div>
                      <div class="reveal-rank-bar-track">
                        <span class="reveal-rank-bar-fill" style="width: {{ min(100, max(0, (float) $rItem['share'])) }}%;"></span>
                      </div>
                    </div>
                    <div class="reveal-rank-stats">
                      <strong class="reveal-rank-votes">{{ number_format((int) $rItem['vote_count'], 0, ',', '.') }}</strong>
                      <span class="reveal-rank-share">{{ number_format((float) $rItem['share'], 1, ',', '.') }}%</span>
                    </div>
                  </div>
                @endforeach
              </div>
            @endif
          </section>
        @endif
      @endif
    @endif
  @endif
</div>
