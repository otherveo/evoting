(() => {
  const numberFormat = new Intl.NumberFormat('id-ID');
  const percentFormat = new Intl.NumberFormat('id-ID', { style: 'percent', maximumFractionDigits: 2 });
  const candidateWords = [
    '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan',
    'Sepuluh', 'Sebelas', 'Dua Belas', 'Tiga Belas', 'Empat Belas', 'Lima Belas', 'Enam Belas',
  ];

  function buildDeck(candidates, votes) {
    if (!votes || Object.keys(votes).length !== candidates.length) {
      throw new Error('Daftar paslon berubah. Muat ulang halaman untuk memulai reveal.');
    }

    const deck = [];
    for (const candidate of candidates) {
      const count = votes[candidate.id];
      if (!Number.isSafeInteger(count) || count < 0) {
        throw new Error('Data suara tidak lengkap. Muat ulang halaman untuk memulai reveal.');
      }
      for (let vote = 0; vote < count; vote++) {
        deck.push(candidate.id);
      }
    }
    for (let index = deck.length - 1; index > 0; index--) {
      const other = Math.floor(Math.random() * (index + 1));
      [deck[index], deck[other]] = [deck[other], deck[index]];
    }
    return deck;
  }

  function center(rect) {
    return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
  }

  window.createRecapRace = function (candidates, setup, wire) {
    let deck = [];
    let disposed = false;
    let autoTimer;
    let frame;
    let layer;
    let visibilityChanged;
    let finishHold;
    const flights = new Set();
    const ticketAnimations = new Set();
    const rowAnimations = new Map();

    return {
      ranking: candidates.map(candidate => ({ ...candidate, votes: 0 })),
      total: 0,
      remaining: 0,
      inFlight: 0,
      loading: false,
      ready: false,
      failed: false,
      mode: setup.mode,
      nextMode: null,
      autoRunning: false,
      delay: setup.delay,
      delayInput: String(setup.delay),
      speed: 1,
      status: 'Memuat data suara…',

      init() {
        layer = document.createElement('div');
        layer.className = 'race-effects';
        layer.setAttribute('aria-hidden', 'true');
        document.body.append(layer);
        visibilityChanged = () => this.scheduleAuto();
        document.addEventListener('visibilitychange', visibilityChanged);
        this.load();
      },

      destroy() {
        disposed = true;
        clearTimeout(autoTimer);
        cancelAnimationFrame(frame);
        finishHold?.();
        document.removeEventListener('visibilitychange', visibilityChanged);
        ticketAnimations.forEach(animation => animation.cancel());
        rowAnimations.forEach(animation => animation.cancel());
        layer?.remove();
      },

      get switching() {
        return this.nextMode !== null;
      },

      get complete() {
        return this.ready && this.remaining === 0 && this.inFlight === 0;
      },

      get validDelay() {
        const value = Number(this.delayInput);
        return /^[0-9]+$/.test(this.delayInput) && value >= 20 && value <= 10000;
      },

      get interval() {
        return Math.max(20, Math.round(this.delay / this.speed));
      },

      formatNumber(value) {
        return numberFormat.format(value);
      },

      share(votes) {
        return this.total === 0 ? 0 : votes / this.total;
      },

      formatShare(votes) {
        return percentFormat.format(this.share(votes));
      },

      async load() {
        if (this.loading || this.ready) return;
        this.loading = true;
        this.failed = false;
        this.status = 'Memuat data suara…';
        try {
          const snapshot = await wire.loadRaceSnapshot();
          if (disposed) return;
          if (!snapshot?.ok) throw new Error('Gagal memuat data suara. Periksa apakah rekapitulasi masih terbuka.');
          deck = buildDeck(this.ranking, snapshot.votes);
          this.total = deck.length;
          this.remaining = deck.length;
          this.ready = true;
          this.status = deck.length === 0 ? 'Tidak ada suara sah untuk ditampilkan.' : 'Siap memulai reveal.';
        } catch (error) {
          if (disposed) return;
          this.failed = true;
          this.status = error instanceof Error && error.message.startsWith('Daftar paslon')
            ? error.message
            : 'Data suara belum dapat dimuat. Periksa apakah rekapitulasi masih terbuka.';
        } finally {
          this.loading = false;
        }
      },

      updateDelay(event) {
        this.delayInput = event.target.value = event.target.value.replace(/[^0-9]/g, '');
        if (!this.validDelay || this.delay === Number(this.delayInput)) return;
        this.delay = Number(this.delayInput);
        this.scheduleAuto();
      },

      setSpeed(factor) {
        if (this.speed === factor) return;
        this.speed = factor;
        this.scheduleAuto();
      },

      toggleAuto() {
        if (!this.ready || this.mode !== 'auto' || this.switching || this.remaining === 0) return;
        this.autoRunning = !this.autoRunning;
        this.status = this.autoRunning ? 'Reveal otomatis berjalan.' : 'Reveal otomatis dijeda.';
        this.scheduleAuto();
      },

      scheduleAuto() {
        clearTimeout(autoTimer);
        if (disposed || document.hidden || !this.autoRunning || this.remaining === 0) return;
        autoTimer = setTimeout(() => {
          const id = this.reserveVote();
          const origin = center(this.$refs.source.getBoundingClientRect());
          this.launchFlight(id, origin, Math.max(160, Math.min(360, this.interval)));
          this.scheduleAuto();
        }, this.interval);
      },

      switchMode(mode) {
        if (!this.ready || this.complete || this.switching || this.mode === mode) return;
        this.autoRunning = false;
        this.scheduleAuto();
        this.nextMode = mode;
        this.settlePlayback();
      },

      settlePlayback() {
        if (this.complete) {
          this.autoRunning = false;
          this.nextMode = null;
          this.status = 'Reveal selesai. Semua suara sah sudah ditampilkan.';
        } else if (this.switching && this.inFlight === 0) {
          this.mode = this.nextMode;
          this.nextMode = null;
          this.autoRunning = this.mode === 'auto' && this.remaining < this.total;
          this.status = this.mode === 'manual'
            ? 'Reveal manual siap.'
            : (this.autoRunning ? 'Reveal otomatis berjalan.' : 'Reveal otomatis siap.');
          this.scheduleAuto();
        } else if (this.switching) {
          this.status = 'Menunggu suara yang sedang bergerak.';
        }
      },

      reserveVote() {
        const id = deck.pop();
        this.remaining = deck.length;
        this.inFlight++;
        return id;
      },

      async openVote() {
        if (!this.ready || this.mode !== 'manual' || this.switching || this.inFlight || this.remaining === 0) return;
        const id = this.reserveVote();
        const candidate = this.ranking.find(row => row.id === id);
        const ticket = this.$refs.ticket.content.firstElementChild.cloneNode(true);
        layer.append(ticket);

        await this.animateTicket(ticket, [
          { opacity: 0, transform: 'translateY(24px) scale(.78)' },
          { opacity: 1, transform: 'none' },
        ], 260);
        await this.holdTicket(1000);
        if (disposed) return;

        const number = ticket.querySelector('.race-ticket-candidate');
        ticket.querySelector('.race-ticket-icon').remove();
        number.hidden = false;
        number.textContent = candidate.formatted_number;
        ticket.querySelector('.race-ticket-label').textContent =
          (candidateWords[candidate.number] || 'Nomor ' + candidate.number) + ' Sah!';
        await this.animateTicket(ticket.firstElementChild, [{ opacity: .15 }, { opacity: 1 }], 180);
        await this.holdTicket(350);
        if (disposed) return;

        const content = ticket.firstElementChild;
        content.replaceChildren(document.createTextNode('+1'));
        content.classList.add('race-ticket-plus');
        await this.animateTicket(ticket, [
          { width: '220px', height: '188px', borderRadius: '22px', background: '#fff', color: '#2048a5' },
          { width: '38px', height: '38px', borderRadius: '50%', background: '#2048a5', color: '#fff' },
        ], 260, 'forwards');
        if (disposed) return;

        const origin = center(ticket.getBoundingClientRect());
        ticket.remove();
        this.launchFlight(id, origin, 560);
      },

      async animateTicket(element, keyframes, duration, fill = 'none') {
        const animation = element.animate(keyframes, { duration, fill, easing: 'cubic-bezier(.22,.76,.25,1)' });
        ticketAnimations.add(animation);
        try {
          await animation.finished;
        } catch (error) {
          if (!disposed) throw error;
        } finally {
          ticketAnimations.delete(animation);
        }
      },

      holdTicket(duration) {
        if (disposed) return Promise.resolve();
        return new Promise(resolve => {
          const timer = setTimeout(() => finishHold(), duration);
          finishHold = () => {
            clearTimeout(timer);
            finishHold = null;
            resolve();
          };
        });
      },

      launchFlight(id, origin, duration) {
        const node = document.createElement('span');
        node.className = 'race-vote';
        node.textContent = '+1';
        node.style.transform = 'translate3d(' + (origin.x - 19) + 'px,' + (origin.y - 19) + 'px,0)';
        layer.append(node);

        const row = this.$refs.ranking.querySelector('[data-candidate="' + CSS.escape(id) + '"]');
        flights.add({ id, target: row?.querySelector('[data-race-target]'), origin, duration, node, start: performance.now() });
        if (!frame) frame = requestAnimationFrame(time => this.drawFlights(time));
      },

      drawFlights(time) {
        frame = null;
        const landed = [];
        const positions = new Map();
        for (const flight of flights) {
          if (flight.target && !positions.has(flight.target)) {
            positions.set(flight.target, center(flight.target.getBoundingClientRect()));
          }
        }
        for (const flight of flights) {
          const progress = Math.min(1, Math.max(0, (time - flight.start) / flight.duration));
          const target = positions.get(flight.target) ?? flight.origin;
          const x = flight.origin.x + (target.x - flight.origin.x) * progress;
          const arc = Math.min(70, Math.abs(target.y - flight.origin.y) * .2 + 28);
          const y = flight.origin.y + (target.y - flight.origin.y) * progress - Math.sin(progress * Math.PI) * arc;
          flight.node.style.transform = 'translate3d(' + (x - 19) + 'px,' + (y - 19) + 'px,0)';
          if (progress === 1) {
            flights.delete(flight);
            flight.node.remove();
            landed.push(flight.id);
          }
        }

        if (landed.length) this.applyLandings(landed);
        if (flights.size) frame = requestAnimationFrame(next => this.drawFlights(next));
      },

      applyLandings(ids) {
        // Sort after each landing so ties retain the relative order at that point in the race.
        let nextRanking = [...this.ranking];
        for (const id of ids) {
          nextRanking.find(candidate => candidate.id === id).votes++;
          nextRanking.sort((left, right) => right.votes - left.votes);
        }
        if (nextRanking.some((candidate, index) => candidate.id !== this.ranking[index].id)) {
          this.moveRows(nextRanking);
        }
        this.inFlight -= ids.length;
        this.settlePlayback();
      },

      moveRows(nextRanking) {
        const rows = [...this.$refs.ranking.querySelectorAll('[data-candidate]')];
        const before = new Map(rows.map(row => [row, row.getBoundingClientRect().top]));
        this.ranking = nextRanking;

        // Alpine flushes the keyed x-for in a microtask before the browser paints.
        queueMicrotask(() => {
          if (disposed) return;
          rows.forEach(row => rowAnimations.get(row)?.cancel());
          const offsets = rows.map(row => [row, before.get(row) - row.getBoundingClientRect().top]);
          for (const [row, offset] of offsets) {
            if (Math.abs(offset) < 1) continue;
            const animation = row.animate([
              { transform: 'translateY(' + offset + 'px)' },
              { transform: 'none' },
            ], { duration: 360, easing: 'cubic-bezier(.22,.76,.25,1)' });
            rowAnimations.set(row, animation);
            const cleanup = () => {
              if (rowAnimations.get(row) === animation) rowAnimations.delete(row);
            };
            animation.onfinish = cleanup;
            animation.oncancel = cleanup;
          }
        });
      },
    };
  };
})();
