{{-- UI-013, FR-020, FR-032, BR-017, BR-019, BR-026: Halaman Rekapitulasi & Pemeriksaan Integritas Operator --}}
{{-- Update v5 — Bagian B (kunci operator + reveal) + Bagian C (file CSS mandiri) --}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/operator/recap-reveal.css') . '?v=' . filemtime(public_path('css/operator/recap-reveal.css')) }}" />
@endpush
<div class="container page view-enter" wire:poll.5s="pollRefresh">
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
      @if($isOpen)
        <x-action-button
          variant="secondary"
          icon="#i-lock"
          wire:click="toggleRecap"
          loadingText="Menutup..."
        >
          Tutup Rekapitulasi
        </x-action-button>
      @endif
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

  @if(! $isOpen)
    {{-- Update v5 — Bagian B.1: kunci operator menggantikan gate fase. --}}
    @if(! $canOpen)
      <x-locked-results-card
        eyebrow="REKAPITULASI PER PASLON TERKUNCI"
        heading="Rekapitulasi Per Paslon Belum Bisa Dibuka"
        description="Halaman ini dibuka oleh operator bila seluruh kuota hak pilih sudah terpakai, atau bila masa pemilihan sudah diakhiri."
        scheduleCaption="SYARAT BUKA"
        :scheduleLabel="$integrity['total_voted'] . ' / ' . $election->max_voter_rights . ' suara terpakai — atau akhiri pemilihan untuk membuka'"
        footnote="Halaman tidak akan terkunci permanen: mengakhiri pemilihan selalu membuka kunci."
        refreshAction="refreshData"
        :secondaryUrl="route('operator.elections.monitor', $election)"
        secondaryLabel="Kembali ke Pantau Pemilih"
      />
    @else
      <x-locked-results-card
        eyebrow="SYARAT TERPENUHI · REKAPITULASI TERKUNCI OLEH OPERATOR"
        heading="Rekapitulasi Per Paslon Terkunci"
        description="Syarat membuka sudah terpenuhi. Buka halaman ini untuk melihat rekapitulasi perolehan suara per paslon."
        scheduleCaption="STATUS"
        :scheduleLabel="$phase === 'ditutup' ? 'Masa pemilihan sudah berakhir' : ($integrity['total_voted'] . ' / ' . $election->max_voter_rights . ' suara terpakai (kuota penuh)')"
        footnote="Halaman dibuka dan ditutup oleh operator; state ini terlihat sama oleh semua operator."
        primaryLabel="Buka Rekapitulasi"
        primaryAction="toggleRecap"
        primaryIcon="#i-eye"
        primaryLoadingText="Membuka..."
        :secondaryUrl="route('operator.elections.monitor', $election)"
        secondaryLabel="Kembali ke Pantau Pemilih"
      />
    @endif
  @else
    {{-- Update v5 — Bagian B.2: pilihan mode tampilan (tersimpan per pemilihan). --}}
    <section class="panel recap-toolbar" aria-label="Mode tampilan rekap">
      <div class="recap-mode-switch" role="group" aria-label="Mode tampilan">
        <span class="recap-mode-label">Mode tampilan:</span>
        <button
          type="button"
          class="btn btn-sm {{ $recapMode === 'biasa' ? 'btn-primary' : 'btn-secondary' }}"
          wire:click="setRecapMode('biasa')"
          @if($election->reveal_locked) disabled title="Reveal sedang berjalan — tekan Reset terlebih dahulu" @endif
        >
          Biasa
        </button>
        <button
          type="button"
          class="btn btn-sm {{ $recapMode === 'animasi' ? 'btn-primary' : 'btn-secondary' }}"
          wire:click="setRecapMode('animasi')"
          @if($election->reveal_locked) disabled title="Reveal sedang berjalan — tekan Reset terlebih dahulu" @endif
        >
          Animasi (reveal)
        </button>
      </div>
      @if($election->reveal_locked)
        <p class="recap-lock-note">
          <svg class="icon"><use href="#i-lock"></use></svg>
          <span>Reveal sedang berjalan{{ $lockerName ? ' — dikunci oleh ' . $lockerName : '' }}. Konfigurasi terkunci sampai Reset.</span>
        </p>
      @endif
    </section>

    @if($recapMode === 'biasa')
      {{-- Update v5 — Bagian B.7: mode biasa memakai hasil live (bukan hanya saat Ditutup). --}}
      @if($phase === 'ditutup')
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
      @else
        <x-results-breakdown
          :election="$election"
          :resultsData="$resultsData ?? []"
          :leadingCandidates="$leadingCandidates"
          :totalVotesCast="$totalVotesCast"
          eyebrow="REKAPITULASI OPERATOR (LIVE)"
          subtitle="Kuota hak pilih sudah terpakai penuh — angka di bawah adalah rekapitulasi live dari tabel suara anonim. Pemungutan suara masih berlangsung."
          metaRightLabel="Status Integritas"
          :metaRightValue="$integrity['is_consistent'] ? 'Konsisten (100%)' : 'Perlu Pemeriksaan'"
          :totalSubtext="'Dari ' . $integrity['total_rights'] . ' hak pilih yang diterbitkan'"
          winnerNote="Perolehan tertinggi saat ini — angka masih bisa berubah selama pemungutan suara berlangsung."
          statusLabel="Pemilihan Berlangsung · Hasil Terbuka (Kuota Penuh)"
          statusClass="is-live"
        />
      @endif
    @else
      @if($election->reveal_locked)
        {{-- Update v5 — Bagian B.4/B.5/B.8: panggung reveal (murni sisi klien dari snapshot sekali ambil). --}}
        <section
          class="reveal-show"
          aria-label="Reveal hasil"
          wire:ignore
          x-data="{
            snapshot: @js($revealSnapshot ?? []),
            order: @js($revealOrder),
            revealed: @js($revealedIds),
            delay: {{ (int) $election->reveal_delay_ms }},
            mode: @js($election->reveal_mode ?? 'satu'),
            speed: 1,
            byId: {},
            shown: {},
            progress: {},
            currentId: null,
            flyingId: null,
            flyingIds: [],
            syncStopped: false,
            syncTimer: null,
            raf: null,
            lastTick: null,
            holdUntil: 0,
            get totalVotes() {
              return this.snapshot.reduce((s, r) => s + (r.vote_count || 0), 0);
            },
            get unrevealedOrder() {
              return this.order.filter(id => !this.revealed.includes(id));
            },
            get ranked() {
              const rows = this.snapshot.filter(r => this.revealed.includes(r.id));
              rows.sort((a, b) => (b.vote_count - a.vote_count) || (a.number - b.number));
              return rows.map((r, i) => ({ ...r, rank: i + 1 }));
            },
            get currentRow() {
              return this.currentId ? (this.byId[this.currentId] || null) : null;
            },
            get isFinished() {
              return this.unrevealedOrder.length === 0 && !this.currentId && !this.flyingId && this.flyingIds.length === 0;
            },
            get flightMs() {
              return Math.round(450 / this.speed);
            },
            get revealProgressLabel() {
              const total = this.order.length;
              const done = this.revealed.length;
              if (total === 0) return '';
              if (this.isFinished) return 'Semua ' + total + ' paslon sudah ditampilkan.';
              return 'Ditampilkan: ' + done + ' dari ' + total + ' paslon.';
            },
            init() {
              this.snapshot.forEach(r => {
                this.byId[r.id] = r;
                this.shown[r.id] = 0;
                this.progress[r.id] = 0;
              });
              if (this.mode === 'satu') {
                this.startNext();
              } else {
                this.lastTick = null;
                this.raf = requestAnimationFrame(t => this.tick(t));
              }
              this.syncTimer = setInterval(() => this.sync(), 3000);
            },
            activeIds() {
              if (this.mode === 'satu') return this.currentId ? [this.currentId] : [];
              return this.unrevealedOrder.filter(id => !this.flyingIds.includes(id));
            },
            startNext() {
              const next = this.unrevealedOrder[0] || null;
              this.currentId = next;
              if (next) {
                if (this.byId[next] && this.byId[next].vote_count === 0) {
                  this.holdUntil = performance.now() + Math.round(500 / this.speed);
                } else {
                  this.holdUntil = 0;
                }
                this.lastTick = null;
                if (!this.raf) this.raf = requestAnimationFrame(t => this.tick(t));
              }
            },
            tick(now) {
              if (!document.contains(this.$el)) { this.stopAll(); return; }
              if (!this.lastTick) this.lastTick = now;
              const dt = now - this.lastTick;
              this.lastTick = now;
              const duration = Math.max(1, this.delay / this.speed);
              const activeIds = this.activeIds();
              if (activeIds.length === 0) { this.raf = null; return; }
              let allDone = true;
              for (const id of activeIds) {
                const row = this.byId[id];
                if (!row) continue;
                if (row.vote_count === 0) {
                  this.shown[id] = 0;
                  this.progress[id] = 1;
                } else {
                  this.progress[id] = Math.min(1, (this.progress[id] || 0) + dt / duration);
                  this.shown[id] = Math.round(row.vote_count * this.progress[id]);
                }
                if (this.progress[id] < 1) allDone = false;
              }
              if (allDone && this.mode === 'satu' && this.currentId && this.byId[this.currentId] && this.byId[this.currentId].vote_count === 0 && now < this.holdUntil) {
                this.raf = requestAnimationFrame(t => this.tick(t));
                return;
              }
              if (allDone) {
                this.completeReveal(activeIds);
                return;
              }
              this.raf = requestAnimationFrame(t => this.tick(t));
            },
            completeReveal(ids) {
              if (this.mode === 'satu') {
                this.flyingId = ids[0];
              } else {
                this.flyingIds = ids.slice();
              }
              setTimeout(() => {
                ids.forEach(id => {
                  if (!this.revealed.includes(id)) this.revealed.push(id);
                  $wire.markRevealed(id).catch(() => {});
                });
                this.flyingId = null;
                this.flyingIds = [];
                if (this.mode === 'satu') {
                  this.currentId = null;
                  this.startNext();
                }
              }, this.flightMs);
            },
            sync() {
              if (!document.contains(this.$el)) { this.stopAll(); return; }
              $wire.syncRevealState().then(state => {
                if (!state) return;
                if (!state.recap_open || !state.reveal_locked) {
                  this.syncStopped = true;
                  this.stopAll();
                  return;
                }
                const serverRevealed = state.revealed_ids || [];
                serverRevealed.forEach(id => {
                  if (!this.revealed.includes(id)) this.revealed.push(id);
                });
                if (this.mode === 'satu' && this.currentId && this.revealed.includes(this.currentId)) {
                  this.currentId = null;
                  this.flyingId = null;
                  if (this.raf) { cancelAnimationFrame(this.raf); this.raf = null; }
                  this.startNext();
                }
              }).catch(() => {});
            },
            stopAll() {
              if (this.raf) { cancelAnimationFrame(this.raf); this.raf = null; }
              if (this.syncTimer) { clearInterval(this.syncTimer); this.syncTimer = null; }
            }
          }"
        >
          <div class="reveal-show-head">
            <div>
              <h2 class="panel-title">
                <svg class="icon"><use href="#i-sparkles"></use></svg>
                <span>Reveal Hasil</span>
              </h2>
              <p class="panel-sub" x-text="isFinished ? 'Semua paslon sudah ditampilkan.' : (mode === 'satu' ? 'Satu per satu paslon — angka menghitung naik dari 0.' : 'Semua paslon menghitung naik bersamaan.')"></p>
            </div>
            <div class="reveal-show-actions">
              <div class="reveal-speed" role="group" aria-label="Kecepatan animasi">
                <span>Kecepatan:</span>
                <template x-for="s in [1, 2, 4]" :key="s">
                  <button type="button" class="speed-btn" :class="{ 'is-active': speed === s }" :disabled="isFinished" x-on:click="speed = s" x-text="s + 'x'"></button>
                </template>
              </div>
              <x-action-button
                variant="secondary"
                icon="#i-rotate"
                wire:click="resetReveal"
                loadingText="Mereset..."
              >
                Reset
              </x-action-button>
            </div>
          </div>

          <div class="reveal-grid">
            <div class="reveal-board" aria-label="Daftar peringkat">
              <h3>Daftar Peringkat</h3>
              <template x-if="ranked.length === 0">
                <p class="reveal-board-empty">Belum ada paslon yang ditampilkan.</p>
              </template>
              <template x-for="row in ranked" :key="row.id">
                <div class="reveal-rank-row" x-transition:enter="reveal-row-enter" x-transition:enter-start="reveal-row-enter-start" x-transition:enter-end="reveal-row-enter-end">
                  <span class="reveal-rank" x-text="row.rank"></span>
                  <div class="reveal-rank-main">
                    <div class="reveal-rank-name">
                      <span x-text="'PASLON ' + row.formatted_number"></span>
                      <strong x-text="row.name"></strong>
                      <span class="reveal-rank-badge" x-show="row.rank === 1" x-cloak>TERATAS</span>
                    </div>
                    <div class="reveal-rank-count">
                      <strong x-text="row.vote_count.toLocaleString('id-ID')"></strong>
                      <span x-text="'suara · ' + row.share.toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + '%'"></span>
                    </div>
                  </div>
                  <div class="reveal-rank-bar"><span :style="'width: ' + row.share + '%;'"></span></div>
                </div>
              </template>
            </div>

            <div class="reveal-stage" aria-label="Panggung reveal">
              <template x-if="mode === 'satu'">
                <div>
                  <template x-if="currentRow">
                    <div class="reveal-stage-card" :class="{ 'is-flying': flyingId === currentId }" :style="flyingId === currentId ? 'transition-duration: ' + flightMs + 'ms;' : ''">
                      <img :src="currentRow.photo_url" :alt="'Foto ' + currentRow.name" class="reveal-stage-photo" />
                      <div class="reveal-stage-info">
                        <span x-text="'PASANGAN CALON ' + currentRow.formatted_number"></span>
                        <h3 x-text="currentRow.name"></h3>
                      </div>
                      <div class="reveal-stage-count" x-text="(shown[currentId] ?? 0).toLocaleString('id-ID')"></div>
                      <div class="reveal-stage-sub" x-text="'dari ' + totalVotes.toLocaleString('id-ID') + ' suara sah'"></div>
                    </div>
                  </template>
                  <template x-if="!currentRow && !isFinished">
                    <p class="reveal-stage-wait">Menyiapkan paslon berikutnya…</p>
                  </template>
                  <template x-if="isFinished">
                    <p class="reveal-stage-done">Reveal selesai — seluruh paslon sudah tampil di daftar peringkat.</p>
                  </template>
                  <p class="reveal-stage-progress" x-text="revealProgressLabel"></p>
                </div>
              </template>
              <template x-if="mode === 'semua'">
                <div class="reveal-stage-all">
                  <template x-for="id in unrevealedOrder" :key="id">
                    <div class="reveal-stage-card is-small" :class="{ 'is-flying': flyingIds.includes(id) }" :style="flyingIds.includes(id) ? 'transition-duration: ' + flightMs + 'ms;' : ''">
                      <img :src="byId[id].photo_url" :alt="'Foto ' + byId[id].name" class="reveal-stage-photo" />
                      <div class="reveal-stage-info">
                        <span x-text="'PASANGAN CALON ' + byId[id].formatted_number"></span>
                        <h3 x-text="byId[id].name"></h3>
                      </div>
                      <div class="reveal-stage-count" x-text="(shown[id] ?? 0).toLocaleString('id-ID')"></div>
                    </div>
                  </template>
                  <template x-if="isFinished">
                    <p class="reveal-stage-done">Reveal selesai — seluruh paslon sudah tampil di daftar peringkat.</p>
                  </template>
                  <p class="reveal-stage-progress" x-text="revealProgressLabel"></p>
                </div>
              </template>
            </div>
          </div>

          <template x-if="syncStopped">
            <p class="reveal-stopped" role="status">Reveal dihentikan (direset atau halaman ditutup) — memuat ulang tampilan…</p>
          </template>
          <template x-if="isFinished">
            <p class="reveal-finished-note">Untuk melihat rekap biasa, tekan <strong>Reset</strong> terlebih dahulu, lalu pilih mode <strong>Biasa</strong>.</p>
          </template>
        </section>
      @else
        {{-- Update v5 — Bagian B.3: konfigurasi reveal (sebelum dimulai). --}}
        @php
          $orderPos = array_flip($revealOrder);
          $configCandidates = collect($resultsData ?? [])->map(fn ($r) => [
              'id' => $r['candidate'] ? (string) $r['candidate']->id : null,
              'number' => $r['formatted_number'],
              'name' => $r['name'],
          ])->filter(fn ($c) => $c['id'] !== null)->values();
        @endphp
        <section class="panel reveal-config" aria-label="Konfigurasi reveal">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">
                <svg class="icon"><use href="#i-sparkles"></use></svg>
                <span>Konfigurasi Reveal</span>
              </h2>
              <p class="panel-sub">Atur urutan tampil, mode, dan delay sebelum memulai animasi. Konfigurasi tersimpan dan terlihat sama oleh semua operator.</p>
            </div>
          </div>
          <div class="reveal-config-body">
            <div class="reveal-config-block">
              <h3>Urutan tampil</h3>
              <p class="field-hint">Klik paslon sesuai urutan yang diinginkan (klik lagi untuk menghapus dari urutan).</p>
              <div class="reveal-order-list">
                @foreach($configCandidates as $cc)
                  @php $pos = $orderPos[$cc['id']] ?? null; @endphp
                  <button
                    type="button"
                    class="reveal-order-chip {{ $pos !== null ? 'is-ordered' : '' }}"
                    wire:click="toggleRevealOrderCandidate('{{ $cc['id'] }}')"
                    wire:loading.attr="disabled"
                  >
                    <span class="reveal-order-pos">{{ $pos !== null ? ($pos + 1) : '—' }}</span>
                    <span class="reveal-order-name">Paslon {{ $cc['number'] }} — {{ $cc['name'] }}</span>
                  </button>
                @endforeach
              </div>
              <div class="reveal-config-actions">
                <x-action-button
                  variant="secondary"
                  icon="#i-rotate"
                  wire:click="shuffleRevealOrder"
                  loadingText="Mengacak..."
                >
                  Acak Urutan Tampil
                </x-action-button>
                <span class="reveal-order-status">
                  @if(count($revealOrder) === count($configCandidates) && count($configCandidates) > 0)
                    Urutan lengkap ({{ count($revealOrder) }} paslon).
                  @else
                    Urutan belum lengkap: {{ count($revealOrder) }} dari {{ count($configCandidates) }} paslon.
                  @endif
                </span>
              </div>
            </div>

            <div class="reveal-config-block">
              <h3>Mode reveal</h3>
              <div class="reveal-mode-choice" role="group" aria-label="Mode reveal">
                <button
                  type="button"
                  class="btn {{ $election->reveal_mode === 'satu' ? 'btn-primary' : 'btn-secondary' }}"
                  wire:click="setRevealMode('satu')"
                >
                  Satu per satu paslon
                </button>
                <button
                  type="button"
                  class="btn {{ $election->reveal_mode === 'semua' ? 'btn-primary' : 'btn-secondary' }}"
                  wire:click="setRevealMode('semua')"
                >
                  Langsung semua paslon
                </button>
              </div>
            </div>

            <div class="reveal-config-block">
              <h3>Delay hitung-naik</h3>
              <p class="field-hint">Total durasi animasi hitung-naik untuk satu paslon (20–10000 ms).</p>
              <div class="reveal-delay-row" x-data>
                <input
                  type="number"
                  x-ref="delayInput"
                  value="{{ (int) $election->reveal_delay_ms }}"
                  min="20"
                  max="10000"
                  step="1"
                  class="reveal-delay-input"
                  aria-label="Delay hitung-naik (ms)"
                />
                <span class="reveal-delay-unit">ms</span>
                <button
                  type="button"
                  class="btn btn-secondary"
                  x-on:click="$wire.applyDelay(parseInt($refs.delayInput.value, 10) || 0)"
                >
                  Terapkan
                </button>
              </div>
              @if($delayError !== '')
                <p class="reveal-delay-error" role="alert">{{ $delayError }}</p>
              @endif
            </div>

            <div class="reveal-config-start">
              <x-action-button
                variant="primary"
                icon="#i-sparkles"
                wire:click="startReveal"
                loadingText="Memulai..."
                :disabled="count($revealOrder) !== count($configCandidates) || count($configCandidates) === 0"
              >
                Mulai Animasi
              </x-action-button>
              @if(count($revealOrder) !== count($configCandidates))
                <span class="field-hint">Lengkapi urutan tampil terlebih dahulu (atau tekan "Acak Urutan Tampil").</span>
              @endif
            </div>
          </div>
        </section>
      @endif
    @endif
  @endif
</div>
