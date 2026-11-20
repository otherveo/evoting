{{-- UI-013, FR-020, FR-032, BR-017, BR-019, Update v5 Bagian B: Halaman Rekapitulasi & Integritas Operator --}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/operator/recap-reveal.css') }}?v={{ filemtime(public_path('css/operator/recap-reveal.css')) }}" />
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
        x-data="{
          candidates: @js($revealCandidates),
          totalVotes: @js($totalVotesCast),
          orderIds: [],
          delayMs: 150,
          delayError: '',
          running: false,
          abortFlag: false,
          skipFlag: false,
          speed: 1,
          wakers: [],
          statusText: 'Siap dijalankan — atur jeda dan urutan tampil, lalu tekan Play',
          hasResults: false,
          dragId: null,

          /* Konstanta animasi persis referensi vote-simulation.html */
          POP_MS: 520,
          START_DELAY: 1000,
          PULSE_MS: 500,
          HOLD_MS: 2000,
          DROP_MS: 520,
          GROW_MS: 520,
          SHIFT_MS: 560,

          init() {
            this.orderIds = this.candidates.map(c => String(c.id));

            this._scrimEl = document.createElement('div');
            this._scrimEl.className = 'sim-scrim';
            this._layerEl = document.createElement('div');
            this._layerEl.className = 'sim-layer';
            document.body.appendChild(this._scrimEl);
            document.body.appendChild(this._layerEl);

            this._onKeyDown = (e) => {
              if (e.key === 'Escape' && this.running) {
                this.skipNow();
              }
            };
            window.addEventListener('keydown', this._onKeyDown);
          },

          destroy() {
            this.abortFlag = true;
            this.skipNow();
            window.removeEventListener('keydown', this._onKeyDown);
            this.cleanupOverlay();
            if (this._scrimEl) {
              this._scrimEl.remove();
              this._scrimEl = null;
            }
            if (this._layerEl) {
              this._layerEl.remove();
              this._layerEl = null;
            }
          },

          candById(id) {
            return this.candidates.find(c => String(c.id) === String(id)) || null;
          },

          get orderedCandidates() {
            return this.orderIds.map(id => this.candById(id)).filter(Boolean);
          },

          formatVotes(val) {
            return new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(Number(val) || 0)));
          },

          formatShare(votes) {
            const pct = this.totalVotes > 0 ? (Number(votes) / this.totalVotes) * 100 : 0;
            return pct.toFixed(1).replace('.', ',') + '%';
          },

          sharePct(votes) {
            if (this.totalVotes <= 0) return 0;
            return Math.min(100, Math.max(0, (Number(votes) / this.totalVotes) * 100));
          },

          esc(str) {
            return String(str).replace(/[&<>\"']/g, c => ({
              '&': '&amp;',
              '<': '&lt;',
              '>': '&gt;',
              '\"': '&quot;',
              '\'': '&#39;'
            }[c]));
          },

          setDelayPreset(val) {
            if (this.running) return;
            this.delayMs = val;
            this.delayError = '';
          },

          validateDelay() {
            const v = Number(this.delayMs);
            if (!Number.isFinite(v) || !Number.isInteger(v) || v < 20 || v > 10000) {
              this.delayError = 'Jeda antar angka wajib berupa bilangan bulat antara 20 ms hingga 10000 ms (10 detik).';
              return false;
            }
            this.delayError = '';
            return true;
          },

          shuffleOrder() {
            if (this.running) return;
            const arr = [...this.orderIds];
            for (let i = arr.length - 1; i > 0; i--) {
              const j = Math.floor(Math.random() * (i + 1));
              [arr[i], arr[j]] = [arr[j], arr[i]];
            }
            this.orderIds = arr;
          },

          moveOrder(index, delta) {
            if (this.running) return;
            const target = index + delta;
            if (target < 0 || target >= this.orderIds.length) return;
            const arr = [...this.orderIds];
            [arr[index], arr[target]] = [arr[target], arr[index]];
            this.orderIds = arr;
          },

          /* Pointer-event Drag & Drop persis referensi vote-simulation.html (instan di mouse & sentuh) */
          onHandleDown(e, id) {
            if (this.running) return;
            e.preventDefault();
            this.dragId = String(id);
            e.currentTarget.setPointerCapture(e.pointerId);
          },

          onSetupMove(e) {
            if (!this.dragId || this.running) return;
            const overEl = document.elementFromPoint(e.clientX, e.clientY)?.closest('[data-setup-id]');
            if (!overEl) return;
            const overId = String(overEl.dataset.setupId);
            if (overId === this.dragId) return;

            const rect = overEl.getBoundingClientRect();
            const insertBefore = e.clientY < rect.top + rect.height / 2;

            const arr = this.orderIds.filter(x => x !== this.dragId);
            const overIdx = arr.indexOf(overId);
            if (overIdx === -1) return;
            arr.splice(insertBefore ? overIdx : overIdx + 1, 0, this.dragId);
            this.orderIds = arr;
          },

          onSetupUp() {
            this.dragId = null;
          },

          /* Jeda yang menghormati kecepatan lokal (1x/2x/4x) & bisa dilewati (tombol x / Esc) */
          wait(ms) {
            if (this.skipFlag || this.abortFlag) return Promise.resolve();
            const scaled = Math.max(8, Math.round(ms / this.speed));
            return new Promise((resolve) => {
              const timer = setTimeout(finish, scaled);
              const finish = () => {
                clearTimeout(timer);
                this.wakers = this.wakers.filter(fn => fn !== finish);
                resolve();
              };
              this.wakers.push(finish);
            });
          },

          skipNow() {
            this.skipFlag = true;
            [...this.wakers].forEach(fn => fn());
          },

          doneAnim(anim) {
            return anim.finished.catch(() => {});
          },

          cleanupOverlay() {
            if (this._layerEl) this._layerEl.innerHTML = '';
            if (this._scrimEl) this._scrimEl.classList.remove('on');
          },

          buildPop(c) {
            const el = document.createElement('div');
            el.className = 'sim-pop';
            el.innerHTML = `
              <button class='sim-pop-close' type='button' aria-label='Lewati animasi'>&times;</button>
              <img class='sim-photo--lg' src='${this.esc(c.photo_url)}' alt='${this.esc(c.name)}' />
              <div class='sim-pop-no'>PASLON NO. ${this.esc(c.formatted_number)}</div>
              <div class='sim-pop-name'>${this.esc(c.name)}</div>
              <div class='sim-pop-count'>0</div>
              <div class='sim-pop-unit'>SUARA SAH</div>
              <div class='sim-pop-speed' aria-label='Kontrol Kecepatan Lokal'>
                <span class='sim-pop-speed-label'>Kecepatan</span>
                <button type='button' class='sim-speed-btn ${this.speed === 1 ? 'is-active' : ''}' data-speed='1'>1x</button>
                <button type='button' class='sim-speed-btn ${this.speed === 2 ? 'is-active' : ''}' data-speed='2'>2x</button>
                <button type='button' class='sim-speed-btn ${this.speed === 4 ? 'is-active' : ''}' data-speed='4'>4x</button>
              </div>
            `;
            el.querySelector('.sim-pop-close').addEventListener('click', () => this.skipNow());
            el.querySelectorAll('.sim-speed-btn').forEach((btn) => {
              btn.addEventListener('click', () => {
                const mult = Number(btn.dataset.speed);
                if ([1, 2, 4].includes(mult)) {
                  this.speed = mult;
                  el.querySelectorAll('.sim-speed-btn').forEach((b) => {
                    b.classList.toggle('is-active', Number(b.dataset.speed) === mult);
                  });
                }
              });
            });
            return el;
          },

          buildResultRow(c) {
            const li = document.createElement('li');
            li.className = 'sim-result';
            li.dataset.id = String(c.id);
            li.style.visibility = 'hidden';
            li.innerHTML = `
              <span class='sim-rank'>#1</span>
              <img class='sim-photo' src='${this.esc(c.photo_url)}' alt='${this.esc(c.name)}' />
              <div class='sim-who'>
                <div class='sim-name-row'>
                  <span class='name'>${this.esc(c.name)}</span>
                  <span class='sim-top-badge' hidden>TERATAS</span>
                </div>
                <div class='meta'>Paslon No. ${this.esc(c.formatted_number)}</div>
              </div>
              <div class='sim-score-wrap'>
                <strong class='sim-score'>${this.formatVotes(c.vote_count)}</strong>
                <span class='sim-share'>${this.formatShare(c.vote_count)}</span>
              </div>
              <div class='sim-bar'><i></i></div>
            `;
            return li;
          },

          refreshRanks() {
            const listEl = this.$refs.simList;
            if (!listEl) return;
            [...listEl.children].forEach((el, idx) => {
              const rankEl = el.querySelector('.sim-rank');
              const topBadge = el.querySelector('.sim-top-badge');
              if (rankEl) rankEl.textContent = '#' + (idx + 1);
              el.classList.toggle('is-rank-1', idx === 0);
              if (topBadge) topBadge.hidden = idx !== 0;
            });
          },

          async moveToList(c, pop) {
            const listEl = this.$refs.simList;
            const scrim = this._scrimEl;
            const li = this.buildResultRow(c);

            // Cari posisi: suara lebih tinggi di atas (seri -> nomor urut lebih kecil di atas)
            const beforeEl = [...listEl.children].find((el) => {
              const other = this.candById(el.dataset.id);
              if (!other) return false;
              const oVotes = Number(other.vote_count) || 0;
              const cVotes = Number(c.vote_count) || 0;
              return oVotes < cVotes || (oVotes === cVotes && Number(other.number) > Number(c.number));
            });

            // Rekam posisi baris lama untuk FLIP shift
            const siblings = [...listEl.children];
            const beforeTop = new Map(siblings.map(el => [el, el.getBoundingClientRect().top]));

            listEl.insertBefore(li, beforeEl || null);
            this.hasResults = true;
            li.scrollIntoView({ block: 'nearest' });

            siblings.forEach((el) => {
              const dy = beforeTop.get(el) - el.getBoundingClientRect().top;
              if (dy) {
                el.animate(
                  [{ transform: `translateY(${dy}px)` }, { transform: 'translateY(0)' }],
                  { duration: Math.round(this.SHIFT_MS / this.speed), easing: 'cubic-bezier(.2,.8,.2,1)' }
                );
              }
            });
            this.refreshRanks();

            const a = pop.getBoundingClientRect();
            const b = li.getBoundingClientRect();
            const px = v => v + 'px';

            // Kunci popup pada koordinat fixed awalnya, sembunyikan tombol close & kontrol kecepatan
            const closeBtn = pop.querySelector('.sim-pop-close');
            const speedBox = pop.querySelector('.sim-pop-speed');
            if (closeBtn) closeBtn.hidden = true;
            if (speedBox) speedBox.hidden = true;

            Object.assign(pop.style, {
              position: 'fixed',
              left: px(a.left),
              top: px(a.top),
              width: px(a.width),
              height: px(a.height),
              margin: '0',
            });
            scrim.classList.remove('on');

            // Tahap 1: kotak utuh turun ke slot baris tujuan
            const drop = pop.animate(
              [{ transform: 'translateY(0)' }, { transform: `translateY(${b.top - a.top}px)` }],
              { duration: Math.round(this.DROP_MS / this.speed), easing: 'cubic-bezier(.4,0,.2,1)', fill: 'forwards' }
            );
            await this.doneAnim(drop);
            drop.cancel();
            pop.style.top = px(b.top);

            // Tahap 2: kotak melebar/morph jadi baris hasil; isi popup memudar, baris hasil muncul
            [...pop.children]
              .filter(el => !el.classList.contains('sim-pop-close') && !el.classList.contains('sim-pop-speed'))
              .forEach(el => el.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 240, fill: 'forwards' }));

            li.style.visibility = '';
            li.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 340, delay: 110, fill: 'backwards' });

            const grow = pop.animate([
              {
                left: px(a.left),
                width: px(a.width),
                height: px(a.height),
                borderRadius: '22px',
                boxShadow: '0 30px 80px -20px rgba(15,27,61,.55)',
              },
              {
                left: px(b.left),
                width: px(b.width),
                height: px(b.height),
                borderRadius: '16px',
                boxShadow: '0 30px 80px -20px rgba(15,27,61,0)',
              },
            ], { duration: Math.round(this.GROW_MS / this.speed), easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'forwards' });

            await this.doneAnim(grow);
            pop.remove();

            // Bilah proporsional baru terisi setelah angka berhenti dan kartu mendarat
            const barFill = li.querySelector('.sim-bar i');
            if (barFill) {
              requestAnimationFrame(() => {
                barFill.style.width = this.sharePct(c.vote_count) + '%';
              });
            }
          },

          async runCandidate(c, delay) {
            this.skipFlag = false;
            const scrim = this._scrimEl;
            const layer = this._layerEl;
            scrim.classList.add('on');

            const pop = this.buildPop(c);
            layer.appendChild(pop);

            // 1. Pop-in: 0 -> 110% -> 100%
            await this.doneAnim(pop.animate([
              { transform: 'scale(0)', opacity: 0 },
              { transform: 'scale(1.1)', opacity: 1, offset: 0.6 },
              { transform: 'scale(1)', opacity: 1 },
            ], { duration: Math.round(this.POP_MS / this.speed), easing: 'ease-out' }));
            if (this.abortFlag) return;

            // 2. Jeda awal, lalu count-up 0 -> n (paslon 0 suara langsung tampil 0 tanpa animasi hitung)
            const countEl = pop.querySelector('.sim-pop-count');
            const targetVotes = Math.max(0, Number(c.vote_count) || 0);

            if (targetVotes > 0) {
              await this.wait(this.START_DELAY);
              if (this.abortFlag) return;

              for (let i = 0; i <= targetVotes; i++) {
                if (this.abortFlag) return;
                if (this.skipFlag) {
                  countEl.textContent = this.formatVotes(targetVotes);
                  break;
                }
                countEl.textContent = this.formatVotes(i);
                if (i < targetVotes) {
                  await this.wait(delay);
                }
              }
            } else {
              countEl.textContent = '0';
              await this.wait(450);
            }
            if (this.abortFlag) return;

            // 3. Denyut sekali setelah sampai di n
            await this.doneAnim(pop.animate([
              { transform: 'scale(1)' },
              { transform: 'scale(1.08)', offset: 0.5 },
              { transform: 'scale(1)' },
            ], { duration: Math.round(this.PULSE_MS / this.speed), easing: 'ease-in-out' }));
            if (this.abortFlag) return;

            // 4. Jeda hold, lalu turun & morph ke daftar peringkat
            await this.wait(this.HOLD_MS);
            if (this.abortFlag) return;

            await this.moveToList(c, pop);
          },

          async playAnimation() {
            if (this.running) return;
            if (!this.validateDelay()) {
              window.showToast('Gagal', 'error');
              return;
            }
            if (this.orderedCandidates.length === 0) return;

            this.running = true;
            this.abortFlag = false;
            this.skipFlag = false;
            this.speed = 1;

            const delay = Math.max(20, Math.min(10000, Math.floor(Number(this.delayMs) || 150)));
            const order = this.orderedCandidates;

            if (this.$refs.simList) {
              this.$refs.simList.innerHTML = '';
            }
            this.hasResults = false;

            for (let i = 0; i < order.length && !this.abortFlag; i++) {
              this.statusText = `Menghitung ${i + 1} dari ${order.length}: ${order[i].name}`;
              await this.runCandidate(order[i], delay);
            }

            this.statusText = this.abortFlag
              ? 'Animasi dihentikan'
              : `Selesai · ${order.length} paslon diurutkan berdasarkan perolehan suara (Total ${this.formatVotes(this.totalVotes)} suara)`;

            this.running = false;
            this.cleanupOverlay();
          },

          resetResults() {
            if (this.running) return;
            if (this.$refs.simList) {
              this.$refs.simList.innerHTML = '';
            }
            this.hasResults = false;
            this.statusText = 'Siap dijalankan — atur jeda dan urutan tampil, lalu tekan Play';
          },

          abortAnimation() {
            this.abortFlag = true;
            this.skipNow();
            this.cleanupOverlay();
          }
        }"
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
