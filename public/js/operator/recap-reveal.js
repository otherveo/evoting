/**
 * Update v5 (Bagian B) — Animasi Reveal Perolehan Suara 100% Client-Side
 * Referensi koreografi: public/tmp/vote-simulation.html
 *
 * Keamanan:
 * - Saat Mode Animasi dibuka, klien HANYA menerima metadata paslon (id, nomor, nama, foto).
 * - Perolehan suara baru ditarik sekali lewat $wire.fetchRevealResults() tepat ketika tombol "Play" ditekan.
 */
window.createRecapSim = function (el, wire) {
  let initialCandidates = [];
  try {
    const raw = el && el.dataset ? el.dataset.candidates : '[]';
    const parsed = JSON.parse(raw || '[]');
    if (Array.isArray(parsed)) {
      initialCandidates = parsed.map((c) => ({
        id: String(c.id),
        number: Number(c.number) || 0,
        formatted_number: String(c.formatted_number || ''),
        name: String(c.name || ''),
        photo_url: String(c.photo_url || ''),
        vote_count: null,
        share: null,
      }));
    }
  } catch (e) {
    initialCandidates = [];
  }

  return {
    candidates: initialCandidates,
    totalVotes: 0,
    votesLoaded: false,
    loadingVotes: false,
    orderIds: initialCandidates.map((c) => String(c.id)),
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
    _scrimEl: null,
    _layerEl: null,
    _onKeyDown: null,

    // Konstanta animasi persis referensi vote-simulation.html
    POP_MS: 520,
    START_DELAY: 1000,
    PULSE_MS: 500,
    HOLD_MS: 2000,
    DROP_MS: 520,
    GROW_MS: 520,
    SHIFT_MS: 560,

    init() {
      if (this.orderIds.length === 0 && this.candidates.length > 0) {
        this.orderIds = this.candidates.map((c) => String(c.id));
      }

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
      if (this._onKeyDown) {
        window.removeEventListener('keydown', this._onKeyDown);
      }
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
      return this.candidates.find((c) => String(c.id) === String(id)) || null;
    },

    get orderedCandidates() {
      return this.orderIds.map((id) => this.candById(id)).filter(Boolean);
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
      return String(str).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
      }[c]));
    },

    setDelayPreset(val) {
      if (this.running || this.loadingVotes) return;
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
      if (this.running || this.loadingVotes) return;
      const arr = [...this.orderIds];
      for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
      }
      this.orderIds = arr;
    },

    moveOrder(index, delta) {
      if (this.running || this.loadingVotes) return;
      const target = index + delta;
      if (target < 0 || target >= this.orderIds.length) return;
      const arr = [...this.orderIds];
      [arr[index], arr[target]] = [arr[target], arr[index]];
      this.orderIds = arr;
    },

    // Pointer-event Drag & Drop (mouse & sentuh)
    onHandleDown(e, id) {
      if (this.running || this.loadingVotes) return;
      e.preventDefault();
      this.dragId = String(id);
      e.currentTarget.setPointerCapture(e.pointerId);
    },

    onSetupMove(e) {
      if (!this.dragId || this.running || this.loadingVotes) return;
      const overEl = document.elementFromPoint(e.clientX, e.clientY)?.closest('[data-setup-id]');
      if (!overEl) return;
      const overId = String(overEl.dataset.setupId);
      if (overId === this.dragId) return;

      const rect = overEl.getBoundingClientRect();
      const insertBefore = e.clientY < rect.top + rect.height / 2;

      const arr = this.orderIds.filter((x) => x !== this.dragId);
      const overIdx = arr.indexOf(overId);
      if (overIdx === -1) return;
      arr.splice(insertBefore ? overIdx : overIdx + 1, 0, this.dragId);
      this.orderIds = arr;
    },

    onSetupUp() {
      this.dragId = null;
    },

    // Jeda yang menghormati kecepatan lokal (1x/2x/4x) & bisa dilewati (tombol x / Esc)
    wait(ms) {
      if (this.skipFlag || this.abortFlag) return Promise.resolve();
      const scaled = Math.max(8, Math.round(ms / this.speed));
      return new Promise((resolve) => {
        const finish = () => {
          clearTimeout(timer);
          this.wakers = this.wakers.filter((fn) => fn !== finish);
          resolve();
        };
        const timer = setTimeout(finish, scaled);
        this.wakers.push(finish);
      });
    },

    skipNow() {
      this.skipFlag = true;
      [...this.wakers].forEach((fn) => fn());
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
        <button class="sim-pop-close" type="button" aria-label="Lewati animasi">&times;</button>
        <img class="sim-photo--lg" src="${this.esc(c.photo_url)}" alt="${this.esc(c.name)}" />
        <div class="sim-pop-no">PASLON NO. ${this.esc(c.formatted_number)}</div>
        <div class="sim-pop-name">${this.esc(c.name)}</div>
        <div class="sim-pop-count">0</div>
        <div class="sim-pop-unit">SUARA SAH</div>
        <div class="sim-pop-speed" aria-label="Kontrol Kecepatan Lokal">
          <span class="sim-pop-speed-label">Kecepatan</span>
          <button type="button" class="sim-speed-btn ${this.speed === 1 ? 'is-active' : ''}" data-speed="1">1x</button>
          <button type="button" class="sim-speed-btn ${this.speed === 2 ? 'is-active' : ''}" data-speed="2">2x</button>
          <button type="button" class="sim-speed-btn ${this.speed === 4 ? 'is-active' : ''}" data-speed="4">4x</button>
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
        <span class="sim-rank">#1</span>
        <img class="sim-photo" src="${this.esc(c.photo_url)}" alt="${this.esc(c.name)}" />
        <div class="sim-who">
          <div class="sim-name-row">
            <span class="name">${this.esc(c.name)}</span>
            <span class="sim-top-badge" hidden>TERATAS</span>
          </div>
          <div class="meta">Paslon No. ${this.esc(c.formatted_number)}</div>
        </div>
        <div class="sim-score-wrap">
          <strong class="sim-score">${this.formatVotes(c.vote_count)}</strong>
          <span class="sim-share">${this.formatShare(c.vote_count)}</span>
        </div>
        <div class="sim-bar"><i></i></div>
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
      const beforeTop = new Map(siblings.map((el) => [el, el.getBoundingClientRect().top]));

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
      const px = (v) => v + 'px';

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
        .filter((el) => !el.classList.contains('sim-pop-close') && !el.classList.contains('sim-pop-speed'))
        .forEach((el) => el.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 240, fill: 'forwards' }));

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
      if (this.running || this.loadingVotes) return;
      if (!this.validateDelay()) {
        if (typeof window.showToast === 'function') {
          window.showToast('Gagal', 'error');
        }
        return;
      }
      if (this.orderedCandidates.length === 0) return;

      // Tarik data perolehan suara dari server HANYA SEKALI saat tombol "Play" ditekan
      if (!this.votesLoaded) {
        this.loadingVotes = true;
        this.statusText = 'Mengambil data perolehan suara dari server...';
        try {
          const res = await wire.fetchRevealResults();
          if (!res || !res.ok) {
            this.statusText = (res && res.message) ? res.message : 'Rekapitulasi terkunci atau gagal memuat data.';
            if (typeof window.showToast === 'function') {
              window.showToast('Gagal', 'error');
            }
            return;
          }

          this.totalVotes = Number(res.total_votes) || 0;
          const votesMap = res.votes_by_id || {};
          this.candidates = this.candidates.map((c) => {
            const entry = votesMap[String(c.id)] || { vote_count: 0, share: 0 };
            return {
              ...c,
              vote_count: Number(entry.vote_count) || 0,
              share: Number(entry.share) || 0,
            };
          });
          this.votesLoaded = true;
        } catch (err) {
          this.statusText = 'Gagal menghubungi server saat memuat data suara.';
          if (typeof window.showToast === 'function') {
            window.showToast('Gagal', 'error');
          }
          return;
        } finally {
          this.loadingVotes = false;
        }
      }

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
      if (this.running || this.loadingVotes) return;
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
    },
  };
};
