'use strict';

/* =========================================================
   SuaraSekolah — Prototype E-Voting Sekolah
   Semua data disimulasikan di browser (localStorage).
   ========================================================= */

const STORAGE_KEY = 'suara_sekolah_demo_state_v1';
const DEVICE_KEY = 'suara_sekolah_demo_device_v1';
const STATE_VERSION = 1;

const ELECTION = {
  school: 'SMA Negeri 1 Nusantara',
  title: 'Pemilihan Ketua & Wakil Ketua OSIS',
  year: 2026,
  opensAt: '2026-11-17T07:00:00+07:00',
  closesAt: '2026-11-17T13:30:00+07:00'
};

const STATUS_META = {
  belum: { label: 'Belum memilih' },
  sedang: { label: 'Sedang memilih' },
  sudah: { label: 'Sudah memilih' }
};

const CANDIDATES = [
  {
    id: 'paslon-01',
    number: 1,
    chair: 'Raka Pratama',
    vice: 'Nadia Putri',
    color: '#173b8f',
    accent: '#4c8df6',
    vision: 'Mewujudkan sekolah yang inklusif, kreatif, dan berprestasi dengan ruang karya yang setara untuk semua siswa.',
    missions: [
      'Menambah ruang kolaborasi dan fasilitas karya siswa.',
      'Mengadakan festival ide serta pameran karya setiap semester.'
    ]
  },
  {
    id: 'paslon-02',
    number: 2,
    chair: 'Bima Saputra',
    vice: 'Salsabila Aulia',
    color: '#0f5f59',
    accent: '#2dd4bf',
    vision: 'Membangun sekolah yang sehat, aman, dan menyenangkan melalui budaya saling peduli antarwarga sekolah.',
    missions: [
      'Menjalankan program bank waktu untuk kegiatan sosial.',
      'Menghadirkan ruang aman dan kampanye kesehatan mental.'
    ]
  },
  {
    id: 'paslon-03',
    number: 3,
    chair: 'Fajar Nugroho',
    vice: 'Kirana Maharani',
    color: '#7c4212',
    accent: '#f2a93b',
    vision: 'Menghubungkan minat siswa dengan kompetisi, jejaring alumni, dan pengalaman dunia kerja sejak dini.',
    missions: [
      'Membuka kelas persiapan olimpiade dan portofolio siswa.',
      'Menghidupkan kembali klub karier dan jejaring alumni.'
    ]
  }
];

/* =========================================================
   Helpers
   ========================================================= */
function $(selector, root = document) {
  return root.querySelector(selector);
}

function $$(selector, root = document) {
  return Array.from(root.querySelectorAll(selector));
}

function escapeHtml(value) {
  return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => {
    switch (char) {
      case '&': return '&amp;';
      case '<': return '&lt;';
      case '>': return '&gt;';
      case '"': return '&quot;';
      default: return '&#39;';
    }
  });
}

function normalizeKey(value) {
  return String(value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('id-ID');
}

function normalizeName(value) {
  return String(value || '').trim().replace(/\s+/g, ' ');
}

function uid(prefix) {
  return `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
}

function generateToken() {
  try {
    const bytes = new Uint8Array(20);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
  } catch (error) {
    return Array.from({ length: 40 }, () => Math.floor(Math.random() * 16).toString(16)).join('');
  }
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function icon(name, className = '') {
  return `<svg class="icon${className ? ` ${className}` : ''}" aria-hidden="true"><use href="#i-${name}"></use></svg>`;
}

function firstName(name) {
  return normalizeName(name).split(' ')[0] || 'Pemilih';
}

function initials(name) {
  return normalizeName(name)
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || '?';
}

function nameHue(name) {
  let hash = 0;
  for (const char of normalizeName(name)) {
    hash = (hash * 31 + char.charCodeAt(0)) % 360;
  }
  return hash;
}

function avatar(name, extraClass = '') {
  return `<span class="avatar${extraClass ? ` ${extraClass}` : ''}" style="--avatar-h:${nameHue(name)}" aria-hidden="true">${escapeHtml(initials(name))}</span>`;
}

function statusBadge(status) {
  const safeStatus = STATUS_META[status] ? status : 'belum';
  return `<span class="status-badge is-${safeStatus}"><span class="status-dot"></span>${STATUS_META[safeStatus].label}</span>`;
}

function getCandidate(id) {
  return CANDIDATES.find((candidate) => candidate.id === id) || null;
}

function padNumber(value) {
  return String(value).padStart(2, '0');
}

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  timeZone: 'Asia/Jakarta'
});

const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  timeZone: 'Asia/Jakarta'
});

const shortDateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
  day: '2-digit',
  month: 'short',
  hour: '2-digit',
  minute: '2-digit',
  timeZone: 'Asia/Jakarta'
});

const longDateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  timeZone: 'Asia/Jakarta'
});

const timeFormatter = new Intl.DateTimeFormat('id-ID', {
  hour: '2-digit',
  minute: '2-digit',
  timeZone: 'Asia/Jakarta'
});

const numberFormatter = new Intl.NumberFormat('id-ID');
const percentFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

function formatDate(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? '—' : dateFormatter.format(date);
}

function formatDateTime(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? '—' : `${dateTimeFormatter.format(date)} WIB`;
}

function formatShortDateTime(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? '—' : shortDateTimeFormatter.format(date);
}

function formatLongDateTime(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? '—' : `${longDateTimeFormatter.format(date)} WIB`;
}

function formatTime(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? '—' : `${timeFormatter.format(date)} WIB`;
}

function formatNumber(value) {
  return numberFormatter.format(Number(value) || 0);
}

function formatPercent(value) {
  return `${percentFormatter.format(Number(value) || 0)}%`;
}

function isElectionClosed() {
  const closesAt = new Date(ELECTION.closesAt).getTime();
  return !Number.isNaN(closesAt) && Date.now() >= closesAt;
}

function maskToken(token) {
  if (!token) return '—';
  return `${token.slice(0, 6)}••••••${token.slice(-4)}`;
}

function buildVoteUrl(token) {
  const base = window.location.href.split('#')[0];
  return `${base}#/vote/${encodeURIComponent(token)}`;
}

function initialsForDevice() {
  return String(DEVICE.id || 'DEV').slice(0, 6).toUpperCase();
}

/* =========================================================
   Simulated device identity
   ========================================================= */
function detectDeviceLabel() {
  const ua = navigator.userAgent || '';
  let browser = 'Browser';
  if (/Edg\//.test(ua)) browser = 'Edge';
  else if (/OPR\//.test(ua)) browser = 'Opera';
  else if (/Chrome\//.test(ua)) browser = 'Chrome';
  else if (/Firefox\//.test(ua)) browser = 'Firefox';
  else if (/Safari\//.test(ua)) browser = 'Safari';

  let os = 'Perangkat';
  if (/Windows/.test(ua)) os = 'Windows';
  else if (/Android/.test(ua)) os = 'Android';
  else if (/iPhone|iPad/.test(ua)) os = 'iOS';
  else if (/Mac OS X/.test(ua)) os = 'macOS';
  else if (/Linux/.test(ua)) os = 'Linux';

  return `${browser} · ${os}`;
}

function randomPrivateIp() {
  return `10.10.${1 + Math.floor(Math.random() * 24)}.${10 + Math.floor(Math.random() * 220)}`;
}

function getDeviceIdentity() {
  try {
    const saved = JSON.parse(localStorage.getItem(DEVICE_KEY) || 'null');
    if (saved && saved.id && saved.label) return saved;
  } catch (error) {
    console.warn('Identitas perangkat tidak dapat dibaca.', error);
  }

  let id;
  try {
    id = crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : uid('dev');
  } catch (error) {
    id = uid('dev');
  }

  const identity = {
    id,
    label: detectDeviceLabel(),
    ip: randomPrivateIp()
  };

  try {
    localStorage.setItem(DEVICE_KEY, JSON.stringify(identity));
  } catch (error) {
    console.warn('Identitas perangkat tidak dapat disimpan.', error);
  }

  return identity;
}

const DEVICE = getDeviceIdentity();

/* =========================================================
   Candidate illustrations (dummy photos)
   ========================================================= */
function candidatePortrait(candidate) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 420">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${candidate.color}"/>
      <stop offset="1" stop-color="${candidate.accent}"/>
    </linearGradient>
    <linearGradient id="shade" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#061126" stop-opacity="0"/>
      <stop offset="1" stop-color="#061126" stop-opacity=".38"/>
    </linearGradient>
  </defs>
  <rect width="640" height="420" fill="url(#bg)"/>
  <circle cx="86" cy="62" r="150" fill="#ffffff" opacity=".08"/>
  <circle cx="582" cy="380" r="190" fill="#ffffff" opacity=".07"/>
  <g fill="#ffffff" opacity=".18">
    <circle cx="540" cy="78" r="3"/>
    <circle cx="568" cy="98" r="2"/>
    <circle cx="512" cy="116" r="2.5"/>
    <circle cx="594" cy="138" r="2"/>
    <circle cx="72" cy="306" r="2.5"/>
    <circle cx="104" cy="330" r="2"/>
  </g>
  <g transform="translate(238 210)">
    <path d="M-160 210 C-152 108 -96 66 0 66 C96 66 152 108 160 210 Z" fill="#0b1834"/>
    <path d="M-57 66 L0 124 L57 66 Z" fill="#f8fafc" opacity=".9"/>
    <rect x="-29" y="0" width="58" height="72" rx="25" fill="#c98a65"/>
    <ellipse cx="0" cy="-42" rx="73" ry="81" fill="#e5a47c"/>
    <path d="M-75 -53 C-80 -135 77 -140 77 -48 C58 -89 34 -109 0 -110 C-37 -110 -62 -88 -75 -53 Z" fill="#111a2c"/>
    <circle cx="-27" cy="-44" r="4.3" fill="#20293a"/>
    <circle cx="27" cy="-44" r="4.3" fill="#20293a"/>
    <path d="M-16 -16 Q0 -4 16 -16" stroke="#a55a3d" stroke-width="3.5" fill="none" stroke-linecap="round"/>
    <rect x="-14" y="132" width="28" height="18" rx="4" fill="#e8eefb" opacity=".85"/>
    <rect x="-8" y="138" width="16" height="2.5" rx="1.25" fill="${candidate.accent}"/>
  </g>
  <g transform="translate(424 240) scale(.92)">
    <path d="M-158 210 C-150 111 -96 69 0 69 C96 69 150 111 158 210 Z" fill="#f1f5fd" opacity=".94"/>
    <path d="M-54 69 L0 124 L54 69 Z" fill="#dfe8f8"/>
    <rect x="-27" y="4" width="54" height="70" rx="23" fill="#b97b55"/>
    <ellipse cx="0" cy="-38" rx="70" ry="77" fill="#c98a63"/>
    <path d="M-70 -42 C-78 -124 74 -136 71 -38 C55 -80 32 -100 0 -101 C-34 -101 -58 -80 -70 -42 Z" fill="#2a1c16"/>
    <circle cx="67" cy="-84" r="20" fill="#2a1c16"/>
    <circle cx="-25" cy="-40" r="4.2" fill="#20293a"/>
    <circle cx="25" cy="-40" r="4.2" fill="#20293a"/>
    <path d="M-14 -13 Q0 -2 14 -13" stroke="#8f4e35" stroke-width="3.5" fill="none" stroke-linecap="round"/>
    <rect x="-14" y="132" width="28" height="18" rx="4" fill="#ffffff" opacity=".9"/>
    <rect x="-8" y="138" width="16" height="2.5" rx="1.25" fill="${candidate.color}"/>
  </g>
  <rect width="640" height="420" fill="url(#shade)"/>
</svg>`;
  return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

CANDIDATES.forEach((candidate) => {
  candidate.photo = candidatePortrait(candidate);
});

/* =========================================================
   Demo state
   ========================================================= */
function createInitialState() {
  const now = Date.now();
  const iso = (minutesAgo) => new Date(now - minutesAgo * 60000).toISOString();

  const tabletA = {
    id: 'seed-device-tablet-01',
    label: 'Chrome · Tablet Lenovo (Perangkat sekolah)',
    ip: '10.14.2.10'
  };
  const tabletB = {
    id: 'seed-device-ipad-02',
    label: 'Safari · iPad (Perangkat sekolah)',
    ip: '10.14.2.11'
  };

  const definitions = [
    {
      name: 'Alya Rahmawati',
      status: 'belum',
      created: 192,
      events: []
    },
    {
      name: 'Bagas Pratama',
      status: 'belum',
      created: 170,
      events: [
        { minutes: 24, action: 'Membuka tautan QR', device: tabletA, result: 'berhasil' }
      ]
    },
    {
      name: 'Citra Lestari',
      status: 'sedang',
      created: 151,
      started: 9,
      device: tabletB,
      events: [
        { minutes: 9, action: 'Memulai sesi voting', device: tabletB, result: 'berhasil' }
      ]
    },
    {
      name: 'Dimas Arya',
      status: 'sudah',
      created: 134,
      started: 35,
      completed: 31,
      device: tabletA,
      events: [
        { minutes: 38, action: 'Membuka tautan QR', device: tabletA, result: 'berhasil' },
        { minutes: 35, action: 'Memulai sesi voting', device: tabletA, result: 'berhasil' },
        { minutes: 31, action: 'Menyimpan suara', device: tabletA, result: 'berhasil' }
      ]
    },
    {
      name: 'Elsa Maharani',
      status: 'belum',
      created: 88,
      events: []
    },
    {
      name: 'Farhan Rizky',
      status: 'belum',
      created: 55,
      events: []
    }
  ];

  const voters = definitions.map((definition, index) => {
    const id = `seed-voter-${index + 1}`;
    const code = `P-${String(index + 1).padStart(3, '0')}`;
    const events = definition.events.map((event, eventIndex) => ({
      id: `seed-event-${index + 1}-${eventIndex + 1}`,
      at: iso(event.minutes),
      action: event.action,
      result: event.result,
      deviceId: event.device.id,
      deviceLabel: event.device.label,
      ip: event.device.ip
    }));

    return {
      id,
      code,
      name: definition.name,
      token: generateToken(),
      tokenVersion: 1,
      status: definition.status,
      createdAt: iso(definition.created),
      startedAt: definition.started ? iso(definition.started) : null,
      completedAt: definition.completed ? iso(definition.completed) : null,
      resetCount: 0,
      duplicateNameAck: false,
      activeSession: definition.status === 'sedang' && definition.device
        ? {
            id: 'seed-session-citra',
            deviceId: definition.device.id,
            deviceLabel: definition.device.label,
            ip: definition.device.ip,
            startedAt: iso(definition.started)
          }
        : null,
      deviceEvents: events
    };
  });

  const audit = voters.map((voter) => ({
    id: uid('audit'),
    at: voter.createdAt,
    action: 'Hak pilih dibuat',
    detail: `${voter.name} · ${voter.code}`,
    kind: 'create',
    actor: 'Operator'
  }));

  audit.push({
    id: uid('audit'),
    at: iso(24),
    action: 'QR dibuka',
    detail: 'Bagas Pratama · P-002',
    kind: 'access',
    actor: 'Pemilih'
  });
  audit.push({
    id: uid('audit'),
    at: iso(9),
    action: 'Sesi voting dimulai',
    detail: 'Citra Lestari · P-003',
    kind: 'start',
    actor: 'Pemilih'
  });
  audit.push({
    id: uid('audit'),
    at: iso(31),
    action: 'Suara anonim tersimpan',
    detail: 'Dimas Arya · P-004 · pilihan tidak dihubungkan ke identitas',
    kind: 'confirm',
    actor: 'Sistem'
  });
  audit.sort((a, b) => new Date(b.at) - new Date(a.at));

  return {
    version: STATE_VERSION,
    meta: {
      createdAt: iso(200),
      maxVoters: 50,
      nextVoterNumber: 7
    },
    voters,
    votes: [
      { id: 'seed-vote-001', candidateId: 'paslon-01', castAt: iso(31) }
    ],
    revokedTokens: [],
    audit
  };
}

function readState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (
        parsed &&
        parsed.version === STATE_VERSION &&
        Array.isArray(parsed.voters) &&
        parsed.meta &&
        Array.isArray(parsed.audit)
      ) {
        return parsed;
      }
    }
  } catch (error) {
    console.warn('Data demo tidak dapat dibaca, memuat data awal.', error);
  }

  const seeded = createInitialState();
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(seeded));
  } catch (error) {
    console.warn('Data demo awal tidak dapat disimpan.', error);
  }
  return seeded;
}

function saveState() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  } catch (error) {
    console.warn('Perubahan demo tidak dapat disimpan.', error);
  }
}

function addAudit(action, detail, kind = 'info', actor = 'Operator') {
  state.audit.unshift({
    id: uid('audit'),
    at: new Date().toISOString(),
    action,
    detail,
    kind,
    actor
  });
  if (state.audit.length > 100) state.audit.length = 100;
}

function addDeviceEvent(voter, action, result = 'berhasil') {
  voter.deviceEvents.push({
    id: uid('evt'),
    at: new Date().toISOString(),
    action,
    result,
    deviceId: DEVICE.id,
    deviceLabel: DEVICE.label,
    ip: DEVICE.ip
  });
  if (voter.deviceEvents.length > 40) {
    voter.deviceEvents = voter.deviceEvents.slice(-40);
  }
}

function getDeviceUsage() {
  const usage = new Map();
  for (const voter of state.voters) {
    for (const event of voter.deviceEvents) {
      if (!event.deviceId) continue;
      if (!usage.has(event.deviceId)) {
        usage.set(event.deviceId, {
          label: event.deviceLabel,
          ip: event.ip,
          voterIds: new Set()
        });
      }
      usage.get(event.deviceId).voterIds.add(voter.id);
    }
  }
  return usage;
}

/* Global app variables */
let state = null;
let voteView = null;
let activeModal = null;
let operatorUI = { query: '', filter: 'all' };

/* =========================================================
   Shared UI pieces
   ========================================================= */
function brandLink(href = '#/operator', label = 'SuaraSekolah — Dashboard Operator') {
  return `<a class="brand" href="${href}" aria-label="${escapeHtml(label)}">
    <span class="brand-mark">${icon('ballot')}</span>
    <span class="brand-text"><strong>SuaraSekolah</strong><small>Prototype E-Voting</small></span>
  </a>`;
}

function siteHeader(active) {
  return `<header class="site-header">
    <div class="header-inner container">
      ${brandLink()}
      <nav class="main-nav" aria-label="Navigasi utama">
        <a class="nav-link${active === 'operator' ? ' is-active' : ''}" href="#/operator">
          ${icon('dashboard')}<span class="nav-label-full">Dashboard Operator</span><span class="nav-label-short">Operator</span>
        </a>
        <a class="nav-link${active === 'simulator' ? ' is-active' : ''}" href="#/simulator">
          ${icon('qr')}<span class="nav-label-full">Simulasi Pemilih</span><span class="nav-label-short">Simulasi</span>
        </a>
        <a class="nav-link${active === 'total' ? ' is-active' : ''}" href="#/total-suara">
          ${icon('users')}<span class="nav-label-full">Total Suara</span><span class="nav-label-short">Total</span>
        </a>
        <a class="nav-link${active === 'results' ? ' is-active' : ''}" href="#/hasil">
          ${icon('chart')}<span class="nav-label-full">Hasil Pemilu</span><span class="nav-label-short">Hasil</span>
        </a>
      </nav>
      <div class="header-side">
        <span class="demo-chip"><span class="demo-dot"></span>Mode Demo</span>
        <span class="operator-chip"><span class="operator-avatar">OP</span><span class="operator-name">Operator</span></span>
      </div>
    </div>
  </header>`;
}

function publicHeader(active) {
  return `<header class="site-header public-site-header">
    <div class="header-inner container">
      ${brandLink('#/total-suara', 'SuaraSekolah — Halaman Publik')}
      <nav class="main-nav" aria-label="Navigasi publik">
        <a class="nav-link${active === 'total' ? ' is-active' : ''}" href="#/total-suara">
          ${icon('users')}<span>Total Suara</span>
        </a>
        <a class="nav-link${active === 'results' ? ' is-active' : ''}" href="#/hasil">
          ${icon('chart')}<span>Hasil Pemilu</span>
        </a>
      </nav>
      <div class="header-side">
        <span class="public-chip">${icon('eye')} Halaman Publik</span>
        <a class="public-operator-link" href="#/operator" aria-label="Buka dashboard operator">${icon('dashboard')}<span>Operator</span></a>
      </div>
    </div>
  </header>`;
}

function publicFooter() {
  return `<footer class="site-footer public-footer">
    <p>${icon('lock')} Halaman publik · dihitung dari suara anonim, tanpa menampilkan identitas pemilih.</p>
    <a class="footer-link" href="#/operator">Dashboard operator ${icon('arrow-right')}</a>
  </footer>`;
}

function parseRoute() {
  const raw = window.location.hash.replace(/^#\/?/, '');
  if (raw.startsWith('vote/')) {
    let token = raw.slice(5);
    try {
      token = decodeURIComponent(token);
    } catch (error) {
      token = '';
    }
    return { name: 'vote', token };
  }
  if (raw === 'simulator') return { name: 'simulator', token: null };
  if (raw === 'hasil') return { name: 'results', token: null };
  if (raw === 'total-suara' || raw === 'total') return { name: 'total', token: null };
  return { name: 'operator', token: null };
}

function voteStepper(step) {
  const steps = [
    { label: 'Verifikasi', icon: 'shield' },
    { label: 'Memilih', icon: 'ballot' },
    { label: 'Konfirmasi', icon: 'check-circle' },
    { label: 'Selesai', icon: 'check' }
  ];
  const current = { intro: 0, ballot: 1, confirm: 2, success: 3 }[step];
  if (current == null) return '';

  return `<ol class="vote-steps" aria-label="Tahapan pemilihan">
    ${steps.map((item, index) => {
      const stateClass = index < current ? ' is-done' : index === current ? ' is-active' : '';
      return `<li class="${stateClass.trim()}">
        <span class="step-dot">${index < current ? icon('check') : index + 1}</span>
        <span class="step-label">${item.label}</span>
      </li>`;
    }).join('')}
  </ol>`;
}

function statCard({ label, value, hint, iconName, variant = '', cap = '', progress = null }) {
  return `<article class="stat-card${variant ? ` ${variant}` : ''}">
    <div class="stat-top">
      <span class="stat-icon">${icon(iconName)}</span>
      ${cap ? `<span class="stat-cap">${escapeHtml(cap)}</span>` : ''}
    </div>
    <p class="stat-value">${value}</p>
    <p class="stat-label">${escapeHtml(label)}</p>
    <p class="stat-hint">${escapeHtml(hint)}</p>
    ${progress ? `<div class="progress" aria-hidden="true"><span style="width:${progress}%"></span></div>` : ''}
  </article>`;
}

const AUDIT_VISUALS = {
  create: { icon: 'user-check', tone: 'blue' },
  access: { icon: 'qr', tone: 'teal' },
  start: { icon: 'activity', tone: 'amber' },
  confirm: { icon: 'check-circle', tone: 'green' },
  reset: { icon: 'rotate', tone: 'red' },
  warn: { icon: 'alert', tone: 'red' },
  info: { icon: 'info', tone: 'blue' }
};

function auditItem(entry) {
  const visual = AUDIT_VISUALS[entry.kind] || AUDIT_VISUALS.info;
  return `<div class="audit-item">
    <span class="audit-icon audit-${visual.tone}">${icon(visual.icon)}</span>
    <div class="audit-main">
      <p>${escapeHtml(entry.action)}</p>
      <span>${escapeHtml(entry.detail)}</span>
    </div>
    <time class="audit-time" datetime="${escapeHtml(entry.at)}">${formatShortDateTime(entry.at)}</time>
  </div>`;
}

function voterRow(voter, sharedDevices) {
  const latest = voter.deviceEvents.length ? voter.deviceEvents[voter.deviceEvents.length - 1] : null;
  const shared = Boolean(latest && sharedDevices.has(latest.deviceId));
  const searchText = normalizeKey(`${voter.name} ${voter.code}`);
  const duplicateBadge = voter.duplicateNameAck
    ? '<span class="name-badge">Nama ganda terverifikasi</span>'
    : '';

  return `<div class="voter-row" data-status="${voter.status}" data-search="${escapeHtml(searchText)}">
    <div class="voter-identity">
      ${avatar(voter.name)}
      <div class="voter-id-copy">
        <div class="voter-name-line">
          <span class="voter-name" title="${escapeHtml(voter.name)}">${escapeHtml(voter.name)}</span>
          ${duplicateBadge}
        </div>
        <p class="voter-meta">${voter.code} · Dibuat ${formatDate(voter.createdAt)}</p>
      </div>
    </div>

    <div class="cell">
      <span class="cell-label">Status</span>
      ${statusBadge(voter.status)}
    </div>

    <div class="cell device-cell">
      <span class="cell-label">Perangkat terakhir</span>
      ${latest
        ? `<div class="device-name">${icon('monitor')}<span title="${escapeHtml(latest.deviceLabel)}">${escapeHtml(latest.deviceLabel)}</span></div>
           ${shared ? `<div class="shared-flag" title="Perangkat ini juga tercatat pada pemilih lain. Hanya indikator audit.">${icon('alert')} Dipakai bersama</div>` : ''}`
        : `<div class="device-name muted">${icon('monitor')}<span>Belum ada akses</span></div>`}
    </div>

    <div class="cell time-cell">
      <span class="cell-label">Aktivitas terakhir</span>
      ${formatShortDateTime(latest ? latest.at : voter.createdAt)}
      <small>${latest ? 'WIB' : 'Dibuat'}</small>
    </div>

    <div class="row-actions">
      <button class="btn btn-xs btn-ghost" type="button" data-detail="${voter.id}">${icon('eye')} Detail</button>
      ${voter.status !== 'sudah' ? `<button class="btn btn-xs btn-soft" type="button" data-qr="${voter.id}">${icon('qr')} QR</button>` : ''}
      ${voter.status === 'sedang' ? `<button class="btn btn-xs btn-warn-ghost" type="button" data-reset="${voter.id}">${icon('rotate')} Reset</button>` : ''}
    </div>
  </div>`;
}

/* =========================================================
   Operator dashboard
   ========================================================= */
function renderOperatorPage() {
  const counts = { total: state.voters.length, belum: 0, sedang: 0, sudah: 0 };
  state.voters.forEach((voter) => {
    if (counts[voter.status] != null) counts[voter.status] += 1;
  });

  const maxVoters = state.meta.maxVoters || 50;
  const capacityPercent = Math.min(100, Math.round((counts.total / maxVoters) * 100));
  const usage = getDeviceUsage();
  const sharedDevices = new Set();
  const sharedVoterIds = new Set();

  usage.forEach((entry, deviceId) => {
    if (entry.voterIds.size > 1) {
      sharedDevices.add(deviceId);
      entry.voterIds.forEach((voterId) => sharedVoterIds.add(voterId));
    }
  });

  const sortedVoters = [...state.voters].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  const auditMarkup = state.audit.slice(0, 6).map(auditItem).join('');
  const rowsMarkup = sortedVoters.map((voter) => voterRow(voter, sharedDevices)).join('');
  const filters = [
    { key: 'all', label: 'Semua', count: counts.total },
    { key: 'belum', label: 'Belum', count: counts.belum },
    { key: 'sedang', label: 'Sedang', count: counts.sedang },
    { key: 'sudah', label: 'Sudah', count: counts.sudah }
  ];

  document.getElementById('app').innerHTML = `
    ${siteHeader('operator')}
    <main class="page container view-enter">
      <section class="page-heading">
        <div>
          <p class="eyebrow">${icon('sparkles')} ${ELECTION.school} · ${ELECTION.year}</p>
          <h1>Dashboard Operator</h1>
          <p class="lede">Kelola hak pilih, pantau status pemilih, dan tangani sesi yang terputus.</p>
        </div>
        <div class="heading-actions">
          <button class="btn btn-primary" type="button" id="btn-add-voter">${icon('plus')} Tambah Pemilih</button>
        </div>
      </section>

      ${sharedVoterIds.size > 0 ? `
        <div class="page-alert">
          <div class="alert alert-warning">
            <div class="alert-flex">
              ${icon('alert')}
              <div>
                <strong>Indikator perangkat terdeteksi</strong>
                <p>${sharedVoterIds.size} pemilih tercatat memakai perangkat atau browser yang sama. Data ini hanya indikator audit, bukan bukti identitas.</p>
              </div>
            </div>
            <button class="btn btn-sm btn-warn-ghost" type="button" id="btn-device-info">Pahami aturannya</button>
          </div>
        </div>` : ''}

      <section class="stat-grid" aria-label="Ringkasan pemilih">
        ${statCard({
          label: 'Hak pilih dibuat',
          value: counts.total,
          hint: `Kapasitas demo ${maxVoters} pemilih`,
          iconName: 'users',
          cap: `${counts.total} / ${maxVoters}`,
          progress: capacityPercent
        })}
        ${statCard({
          label: 'Belum memilih',
          value: counts.belum,
          hint: 'QR siap digunakan',
          iconName: 'clock',
          variant: 'stat-violet'
        })}
        ${statCard({
          label: 'Sedang memilih',
          value: counts.sedang,
          hint: 'Sesi voting aktif',
          iconName: 'activity',
          variant: 'stat-amber'
        })}
        ${statCard({
          label: 'Sudah memilih',
          value: counts.sudah,
          hint: 'Suara anonim tersimpan',
          iconName: 'check-circle',
          variant: 'stat-green'
        })}
      </section>

      <section class="panel voter-panel" aria-labelledby="voter-panel-title">
        <div class="panel-head">
          <div>
            <h2 class="panel-title" id="voter-panel-title">${icon('users')} Daftar Pemilih</h2>
            <p class="panel-sub">Satu nama, satu hak pilih. Status diperbarui otomatis saat pemilih memakai QR.</p>
          </div>
          <div class="voter-tools">
            <div class="search-field">
              ${icon('search')}
              <label class="sr-only" for="voter-search">Cari pemilih</label>
              <input class="field-input" id="voter-search" type="search" placeholder="Cari nama atau kode..." value="${escapeHtml(operatorUI.query)}" autocomplete="off">
            </div>
            <div class="filter-row" role="group" aria-label="Filter status pemilih">
              ${filters.map((filter) => `
                <button class="filter-pill${operatorUI.filter === filter.key ? ' is-active' : ''}" type="button" data-filter="${filter.key}" aria-pressed="${operatorUI.filter === filter.key}">
                  ${filter.label} <span class="count">${filter.count}</span>
                </button>`).join('')}
            </div>
          </div>
        </div>

        <div class="voter-table-head" aria-hidden="true">
          <span>Pemilih</span>
          <span>Status</span>
          <span>Perangkat terakhir</span>
          <span>Aktivitas terakhir</span>
          <span style="text-align:right">Aksi</span>
        </div>
        <div id="voter-list">${rowsMarkup}</div>
        <div class="empty-state" id="voter-empty" hidden>
          <span class="empty-icon">${icon('search')}</span>
          <h3>Tidak ada pemilih yang cocok</h3>
          <p>Ubah kata kunci atau filter status untuk melihat pemilih lainnya.</p>
        </div>
        <div class="panel-foot">
          <span>Menampilkan <strong id="visible-count">${counts.total}</strong> dari <strong>${counts.total}</strong> pemilih</span>
          <span>Data disimpan di browser ini</span>
        </div>
      </section>

      <div class="dashboard-lower">
        <section class="panel" aria-labelledby="activity-title">
          <div class="panel-head">
            <div>
              <h2 class="panel-title" id="activity-title">${icon('activity')} Aktivitas Terbaru</h2>
              <p class="panel-sub">Jejak tindakan operator, pemilih, dan sistem pada demo ini.</p>
            </div>
          </div>
          <div class="audit-list">${auditMarkup || '<p class="panel-sub" style="padding:18px 0">Belum ada aktivitas.</p>'}</div>
        </section>

        <aside class="panel guide-panel" aria-labelledby="guide-title">
          <div class="panel-head">
            <div>
              <h2 class="panel-title" id="guide-title">${icon('sparkles')} Alur Demo Cepat</h2>
              <p class="panel-sub">Coba seluruh workflow tanpa me-refresh halaman.</p>
            </div>
          </div>
          <div class="guide-steps">
            <div class="guide-step"><span class="guide-num">1</span><p><strong>Tambah pemilih.</strong> <span>QR simulasi langsung diterbitkan dengan status Belum memilih.</span></p></div>
            <div class="guide-step"><span class="guide-num">2</span><p><strong>Buka halaman pemilih.</strong> <span>Mulai sesi, pilih satu paslon, lalu konfirmasi.</span></p></div>
            <div class="guide-step"><span class="guide-num">3</span><p><strong>Uji sesi terputus.</strong> <span>Tinggalkan halaman, lalu buka tautan yang sama untuk melanjutkan.</span></p></div>
            <div class="guide-step"><span class="guide-num">4</span><p><strong>Reset dari operator.</strong> <span>Status kembali ke Belum memilih dan QR lama otomatis dicabut.</span></p></div>
          </div>
          <p class="guide-note">${icon('info')} Autentikasi operator dan masa berlaku token belum ditetapkan, sehingga disimulasikan pada demo ini.</p>
        </aside>
      </div>

      <footer class="site-footer">
        <p>${icon('lock')} Prototype demo · data tersimpan di localStorage, bukan server.</p>
        <button class="link-button" type="button" id="btn-reset-demo">Reset data demo</button>
      </footer>
    </main>`;

  bindOperatorPage();
}

function bindOperatorPage() {
  $('#btn-add-voter')?.addEventListener('click', openAddVoterModal);
  $('#btn-device-info')?.addEventListener('click', showDeviceInfoModal);
  $('#btn-reset-demo')?.addEventListener('click', showResetDemoConfirm);

  $$('[data-detail]').forEach((button) => {
    button.addEventListener('click', () => showVoterDetail(button.dataset.detail));
  });
  $$('[data-qr]').forEach((button) => {
    button.addEventListener('click', () => showQrModal(button.dataset.qr));
  });
  $$('[data-reset]').forEach((button) => {
    button.addEventListener('click', () => showResetConfirm(button.dataset.reset));
  });

  const search = $('#voter-search');
  search?.addEventListener('input', (event) => {
    operatorUI.query = event.target.value;
    applyVoterFilter();
  });

  $$('.filter-pill').forEach((button) => {
    button.addEventListener('click', () => {
      operatorUI.filter = button.dataset.filter;
      $$('.filter-pill').forEach((item) => {
        const active = item === button;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', String(active));
      });
      applyVoterFilter();
    });
  });

  applyVoterFilter();
}

function applyVoterFilter() {
  const rows = $$('#voter-list .voter-row');
  const query = normalizeKey(operatorUI.query);
  let visible = 0;

  rows.forEach((row) => {
    const matchQuery = !query || (row.dataset.search || '').includes(query);
    const matchStatus = operatorUI.filter === 'all' || row.dataset.status === operatorUI.filter;
    const show = matchQuery && matchStatus;
    row.hidden = !show;
    if (show) visible += 1;
  });

  const empty = $('#voter-empty');
  if (empty) empty.hidden = visible > 0;
  const count = $('#visible-count');
  if (count) count.textContent = String(visible);
}

/* =========================================================
   Toasts, loading state, and modal framework
   ========================================================= */
function showToast(message, type = 'success') {
  const root = $('#toast-root');
  if (!root) return;

  while (root.children.length >= 4) {
    root.removeChild(root.firstElementChild);
  }

  const icons = { success: 'check-circle', error: 'alert', warning: 'alert', info: 'info' };
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.setAttribute('role', 'status');
  toast.innerHTML = `${icon(icons[type] || 'info')}<span>${escapeHtml(message)}</span>`;
  root.appendChild(toast);

  requestAnimationFrame(() => toast.classList.add('is-visible'));
  setTimeout(() => {
    toast.classList.add('is-leaving');
    setTimeout(() => toast.remove(), 240);
  }, 4200);
}

function setButtonLoading(button, loading, loadingText = 'Memproses...') {
  if (!button) return;
  if (loading) {
    if (!button.dataset.originalHtml) button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.classList.add('is-loading');
    button.innerHTML = `<span class="spinner" aria-hidden="true"></span><span>${escapeHtml(loadingText)}</span>`;
  } else {
    button.disabled = false;
    button.classList.remove('is-loading');
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
  }
}

function closeModal(options = {}) {
  const root = $('#modal-root');
  if (!activeModal) {
    if (root) root.innerHTML = '';
    document.body.classList.remove('modal-open');
    return;
  }

  const modal = activeModal;
  activeModal = null;
  document.removeEventListener('keydown', modal.keyHandler);
  if (modal.root) modal.root.innerHTML = '';
  document.body.classList.remove('modal-open');

  if (modal.lastFocus && modal.lastFocus.isConnected) {
    try {
      modal.lastFocus.focus({ preventScroll: true });
    } catch (error) {
      modal.lastFocus.focus();
    }
  }

  if (!options.silent && typeof modal.onClose === 'function') {
    modal.onClose();
  }
}

function openModal({ title, subtitle = '', body = '', footer = '', width = '560px', iconName = 'info', onOpen, onClose }) {
  closeModal();
  const root = $('#modal-root');
  if (!root) return;

  const lastFocus = document.activeElement;
  root.innerHTML = `<div class="modal-backdrop" data-modal-backdrop>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" style="--modal-width:${width}">
      <div class="modal-header">
        <div class="modal-heading">
          ${iconName ? `<span class="modal-icon">${icon(iconName)}</span>` : ''}
          <div>
            <h2 id="modal-title">${escapeHtml(title)}</h2>
            ${subtitle ? `<p>${escapeHtml(subtitle)}</p>` : ''}
          </div>
        </div>
        <button class="icon-btn" type="button" data-modal-close aria-label="Tutup dialog">${icon('x')}</button>
      </div>
      <div class="modal-body">${body}</div>
      ${footer ? `<div class="modal-footer">${footer}</div>` : ''}
    </div>
  </div>`;

  document.body.classList.add('modal-open');
  const backdrop = $('[data-modal-backdrop]', root);
  const modal = $('.modal', root);

  const keyHandler = (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
      return;
    }
    if (event.key !== 'Tab') return;

    const focusable = $$(
      'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      modal
    ).filter((element) => element.offsetParent !== null);
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };

  activeModal = { root, modal, onClose, keyHandler, lastFocus };
  document.addEventListener('keydown', keyHandler);

  $$('[data-modal-close]', root).forEach((button) => {
    button.addEventListener('click', () => closeModal());
  });
  backdrop?.addEventListener('mousedown', (event) => {
    if (event.target === backdrop) closeModal();
  });

  requestAnimationFrame(() => {
    backdrop?.classList.add('is-visible');
    const focusTarget = $('[data-autofocus]', modal)
      || $('input:not([type="hidden"])', modal)
      || $('.btn-primary', modal)
      || $('button', modal);
    focusTarget?.focus({ preventScroll: true });
  });

  if (typeof onOpen === 'function') onOpen(modal);
}

function confirmDialog({ title, subtitle = '', message = '', body = '', confirmText = 'Ya, lanjutkan', cancelText = 'Batal', tone = 'primary', iconName = 'alert' }) {
  return new Promise((resolve) => {
    let settled = false;
    const finish = (value) => {
      if (settled) return;
      settled = true;
      closeModal({ silent: true });
      resolve(value);
    };

    openModal({
      title,
      subtitle,
      iconName,
      width: '520px',
      body: body || `<p class="confirm-message">${escapeHtml(message)}</p>`,
      footer: `
        <button class="btn btn-ghost" type="button" data-confirm-cancel data-autofocus>${escapeHtml(cancelText)}</button>
        <button class="btn ${tone === 'danger' ? 'btn-danger' : 'btn-primary'}" type="button" data-confirm-ok>${escapeHtml(confirmText)}</button>`,
      onClose: () => finish(false),
      onOpen: (modal) => {
        $('[data-confirm-cancel]', modal)?.addEventListener('click', () => finish(false));
        $('[data-confirm-ok]', modal)?.addEventListener('click', () => finish(true));
      }
    });
  });
}

/* =========================================================
   Add voter + QR modal
   ========================================================= */
function openAddVoterModal() {
  if (state.voters.length >= state.meta.maxVoters) {
    showToast(`Kapasitas maksimum ${state.meta.maxVoters} hak pilih sudah tercapai.`, 'error');
    return;
  }

  const body = `<form id="add-voter-form" novalidate>
    <div class="form-field">
      <label class="field-label" for="voter-name">Nama lengkap <span class="req">*</span></label>
      <input class="field-input" id="voter-name" name="voter-name" type="text" maxlength="80" autocomplete="off" placeholder="Contoh: Alya Rahmawati" aria-describedby="name-hint">
      <p class="field-hint" id="name-hint">Nama yang sudah terdaftar akan dikunci agar tidak dibuatkan hak pilih kedua secara tidak sengaja.</p>
      <p class="field-error" id="name-error" hidden></p>
    </div>
    <div class="inline-alert inline-alert-warning" id="duplicate-box" hidden>
      <strong>${icon('alert')} Nama sudah terdaftar</strong>
      <p id="duplicate-copy"></p>
      <label class="check-row" for="duplicate-ack">
        <input type="checkbox" id="duplicate-ack">
        <span>Saya memastikan ini adalah orang yang berbeda dan sudah diverifikasi langsung.</span>
      </label>
    </div>
    <div class="alert alert-info" style="margin-top:14px">
      ${icon('qr')}
      <div><strong>Satu QR, satu suara.</strong><p>Setelah disimpan, QR simulasi dapat dibuka untuk memulai pemilihan. Token hanya sekali pakai dan tidak memuat nama atau pilihan pemilih.</p></div>
    </div>
  </form>`;

  const footer = `
    <button class="btn btn-ghost" type="button" data-modal-close>Batal</button>
    <button class="btn btn-primary" type="submit" form="add-voter-form" id="btn-save-voter">${icon('plus')} Buat hak pilih</button>`;

  openModal({
    title: 'Tambah hak pilih',
    subtitle: 'Masukkan nama lengkap pemilih yang sudah diverifikasi operator.',
    iconName: 'users',
    width: '580px',
    body,
    footer,
    onOpen: (modal) => {
      const form = $('#add-voter-form', modal);
      const input = $('#voter-name', modal);
      const error = $('#name-error', modal);
      const duplicateBox = $('#duplicate-box', modal);
      const duplicateCopy = $('#duplicate-copy', modal);
      const duplicateAck = $('#duplicate-ack', modal);
      const saveButton = $('#btn-save-voter', modal);
      let duplicateTargetId = null;

      const showError = (message) => {
        error.hidden = false;
        error.innerHTML = `${icon('alert')}<span>${escapeHtml(message)}</span>`;
        input.classList.add('is-invalid');
      };

      const clearError = () => {
        error.hidden = true;
        error.innerHTML = '';
        input.classList.remove('is-invalid');
      };

      const refreshDuplicate = () => {
        const key = normalizeKey(input.value);
        const existing = key.length >= 2
          ? state.voters.find((voter) => normalizeKey(voter.name) === key)
          : null;

        if ((existing?.id || null) !== duplicateTargetId) {
          duplicateAck.checked = false;
          duplicateTargetId = existing?.id || null;
        }
        duplicateBox.hidden = !existing;
        if (existing) {
          duplicateCopy.textContent = `${existing.name} (${existing.code}) sudah memiliki hak pilih. Jika ini orang yang berbeda, centang konfirmasi di bawah.`;
        }
      };

      input.addEventListener('input', () => {
        clearError();
        refreshDuplicate();
      });

      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const name = normalizeName(input.value);

        if (name.length < 2) {
          showError('Nama lengkap minimal 2 karakter.');
          input.focus();
          return;
        }
        if (name.length > 80) {
          showError('Nama lengkap maksimal 80 karakter.');
          input.focus();
          return;
        }

        const existing = state.voters.find((voter) => normalizeKey(voter.name) === normalizeKey(name));
        if (existing && !duplicateAck.checked) {
          showError('Nama ini sudah terdaftar. Centang konfirmasi orang berbeda untuk melanjutkan.');
          duplicateBox.hidden = false;
          duplicateAck.focus();
          return;
        }

        clearError();
        setButtonLoading(saveButton, true, 'Membuat hak pilih...');
        await sleep(700);
        state = readState();

        if (state.voters.length >= state.meta.maxVoters) {
          setButtonLoading(saveButton, false);
          showError(`Kapasitas maksimum ${state.meta.maxVoters} hak pilih sudah tercapai.`);
          return;
        }

        const existingNow = state.voters.find((voter) => normalizeKey(voter.name) === normalizeKey(name));
        if (existingNow && !duplicateAck.checked) {
          setButtonLoading(saveButton, false);
          showError('Nama ini baru saja terdaftar di tab lain. Muat ulang lalu periksa kembali.');
          return;
        }

        const now = new Date().toISOString();
        const voter = {
          id: uid('voter'),
          code: `P-${String(state.meta.nextVoterNumber).padStart(3, '0')}`,
          name,
          token: generateToken(),
          tokenVersion: 1,
          status: 'belum',
          createdAt: now,
          startedAt: null,
          completedAt: null,
          resetCount: 0,
          duplicateNameAck: Boolean(existingNow),
          activeSession: null,
          deviceEvents: []
        };

        state.meta.nextVoterNumber += 1;
        state.voters.unshift(voter);
        addAudit('Hak pilih dibuat', `${voter.name} · ${voter.code}`, 'create');
        saveState();

        closeModal({ silent: true });
        operatorUI = { query: '', filter: 'all' };
        renderOperatorPage();
        showToast(`Hak pilih ${voter.code} berhasil dibuat. QR siap dibagikan.`, 'success');

        setTimeout(() => {
          if (parseRoute().name === 'operator') showQrModal(voter.id, { created: true });
        }, 240);
      });

      refreshDuplicate();
    }
  });
}

async function copyToClipboard(text) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch (error) {
    console.warn('Clipboard API tidak tersedia.', error);
  }

  try {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    const success = document.execCommand('copy');
    textarea.remove();
    return success;
  } catch (error) {
    return false;
  }
}

function showQrModal(voterId, options = {}) {
  const voter = state.voters.find((item) => item.id === voterId);
  if (!voter) {
    showToast('Hak pilih tidak ditemukan. Muat ulang data demo.', 'error');
    return;
  }

  const voteUrl = buildVoteUrl(voter.token);
  const note = options.created
    ? `<div class="qr-success-note">${icon('check-circle')} Hak pilih berhasil dibuat. Tunjukkan atau bagikan QR simulasi ini kepada pemilih.</div>`
    : options.newToken
      ? `<div class="qr-success-note">${icon('rotate')} QR lama telah dicabut. QR baru diterbitkan dan siap digunakan.</div>`
      : '';

  const body = `${note}
    <div class="qr-layout">
      <div class="qr-card">
        <a class="qr-frame" href="#/vote/${encodeURIComponent(voter.token)}" data-qr-open aria-label="Buka halaman pemilih untuk ${escapeHtml(voter.name)}">
          <canvas class="qr-canvas" id="qr-canvas" width="696" height="696" aria-label="QR simulasi untuk ${escapeHtml(voter.name)}"></canvas>
        </a>
        <span class="qr-hint">${icon('external')} QR simulasi — klik untuk membuka</span>
      </div>
      <div class="qr-details">
        <div class="voter-mini">
          ${avatar(voter.name)}
          <div class="voter-id-copy">
            <div class="voter-name">${escapeHtml(voter.name)}</div>
            <p class="voter-meta">${voter.code}${voter.duplicateNameAck ? ' · Nama ganda terverifikasi' : ''}</p>
          </div>
          <div style="margin-left:auto">${statusBadge(voter.status)}</div>
        </div>
        <dl class="detail-list">
          <div><dt>Status hak pilih</dt><dd>${STATUS_META[voter.status]?.label || '—'}</dd></div>
          <div><dt>Dibuat</dt><dd>${formatDate(voter.createdAt)}</dd></div>
          <div><dt>Token</dt><dd>Sekali pakai</dd></div>
        </dl>
        <div class="alert alert-info">
          ${icon('info')}
          <div><strong>QR simulasi, bukan kode pindai.</strong><p>Klik QR atau tombol di bawah untuk membuka halaman pemilih. Di produksi, QR mengarah ke token acak yang tidak memuat nama maupun pilihan.</p></div>
        </div>
        <div class="token-box">
          <div><span>Tautan token</span><code>${escapeHtml(maskToken(voter.token))}</code></div>
          <button class="icon-btn" type="button" id="btn-copy-link" title="Salin tautan pemilih" aria-label="Salin tautan pemilih">${icon('copy')}</button>
        </div>
      </div>
    </div>`;

  const footer = `
    <button class="btn btn-ghost" type="button" data-modal-close>Tutup</button>
    <a class="btn btn-primary" href="#/vote/${encodeURIComponent(voter.token)}" data-qr-open>Buka Halaman Pemilih ${icon('arrow-right')}</a>`;

  openModal({
    title: options.newToken ? 'QR baru diterbitkan' : options.created ? 'Hak pilih berhasil dibuat' : 'QR hak pilih',
    subtitle: `${voter.name} · ${voter.code}`,
    iconName: 'qr',
    width: '780px',
    body,
    footer,
    onOpen: (modal) => {
      setTimeout(() => drawQrCode($('#qr-canvas', modal), voter.token), 40);

      $('#btn-copy-link', modal)?.addEventListener('click', async () => {
        const copied = await copyToClipboard(voteUrl);
        showToast(
          copied ? 'Tautan pemilih berhasil disalin.' : 'Tautan tidak dapat disalin pada browser ini.',
          copied ? 'success' : 'error'
        );
      });

      $$('[data-qr-open]', modal).forEach((link) => {
        link.addEventListener('click', () => closeModal({ silent: true }));
      });
    }
  });
}

/* =========================================================
   Voter detail, reset, and demo controls
   ========================================================= */
function deviceEventMarkup(event) {
  return `<div class="device-item">
    <div class="device-item-head">
      <span>${escapeHtml(event.action)}</span>
      <span>${formatShortDateTime(event.at)} WIB</span>
    </div>
    <div class="device-item-meta">
      <span>${icon('monitor')} ${escapeHtml(event.deviceLabel)}</span>
      <span>IP ${escapeHtml(event.ip)}</span>
      <span>ID ${escapeHtml(String(event.deviceId).slice(0, 8).toUpperCase())}</span>
      <span class="${event.result === 'ditolak' ? 'result-denied' : ''}">${event.result === 'ditolak' ? 'Ditolak' : 'Berhasil'}</span>
    </div>
  </div>`;
}

function showVoterDetail(voterId) {
  const voter = state.voters.find((item) => item.id === voterId);
  if (!voter) {
    showToast('Pemilih tidak ditemukan.', 'error');
    return;
  }

  const events = [...voter.deviceEvents].reverse();
  const latest = events[0] || null;
  const usage = getDeviceUsage();
  const shared = Boolean(latest && (usage.get(latest.deviceId)?.voterIds.size || 0) > 1);

  const body = `
    <div class="detail-hero">
      ${avatar(voter.name, 'avatar-lg')}
      <div class="voter-id-copy">
        <h3>${escapeHtml(voter.name)}</h3>
        <p>${voter.code} · Dibuat ${formatDate(voter.createdAt)}</p>
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
          ${statusBadge(voter.status)}
          ${voter.duplicateNameAck ? '<span class="name-badge">Nama ganda terverifikasi</span>' : ''}
        </div>
      </div>
    </div>

    <div class="meta-grid">
      <div class="meta-item"><span>Mulai memilih</span><strong>${formatDateTime(voter.startedAt)}</strong></div>
      <div class="meta-item"><span>Suara tersimpan</span><strong>${formatDateTime(voter.completedAt)}</strong></div>
      <div class="meta-item"><span>Reset operator</span><strong>${voter.resetCount} kali</strong></div>
      <div class="meta-item"><span>Token</span><strong>${voter.status === 'sudah' ? 'Sudah digunakan' : 'Aktif · sekali pakai'}</strong></div>
    </div>

    ${shared ? `<div class="alert alert-warning" style="margin-bottom:15px">
      ${icon('alert')}
      <div><strong>Perangkat juga tercatat pada pemilih lain.</strong><p>Gunakan informasi ini sebagai indikator audit. Verifikasi langsung tetap diperlukan; perangkat yang sama bukan alasan tunggal untuk menolak pemilih.</p></div>
    </div>` : ''}

    <h3 class="section-title">${icon('monitor')} Aktivitas perangkat</h3>
    <div class="device-list">
      ${events.length
        ? events.slice(0, 12).map(deviceEventMarkup).join('')
        : '<div class="device-item"><div class="device-item-head"><span>Belum ada aktivitas perangkat</span></div><div class="device-item-meta"><span>QR belum pernah dibuka.</span></div></div>'}
    </div>

    <div class="privacy-callout">
      ${icon('lock')}
      <span>Operator hanya dapat melihat status hak pilih dan indikator perangkat. Pilihan paslon pemilih tidak ditampilkan dan tidak dihubungkan ke identitas.</span>
    </div>`;

  const footer = `
    <button class="btn btn-ghost" type="button" data-modal-close>Tutup</button>
    ${voter.status !== 'sudah' ? `<button class="btn btn-soft" type="button" id="detail-show-qr">${icon('qr')} Lihat QR</button>` : ''}
    ${voter.status === 'sedang' ? `<button class="btn btn-danger-ghost" type="button" id="detail-reset">${icon('rotate')} Reset sesi</button>` : ''}`;

  openModal({
    title: 'Detail pemilih',
    subtitle: `${voter.code} · Pemeriksaan status dan perangkat`,
    iconName: 'user-check',
    width: '660px',
    body,
    footer,
    onOpen: (modal) => {
      $('#detail-show-qr', modal)?.addEventListener('click', () => {
        closeModal({ silent: true });
        showQrModal(voter.id);
      });
      $('#detail-reset', modal)?.addEventListener('click', () => {
        closeModal({ silent: true });
        showResetConfirm(voter.id);
      });
    }
  });
}

async function showResetConfirm(voterId) {
  state = readState();
  const voter = state.voters.find((item) => item.id === voterId);

  if (!voter) {
    showToast('Pemilih tidak ditemukan.', 'error');
    return;
  }
  if (voter.status !== 'sedang') {
    showToast('Reset hanya tersedia untuk pemilih yang sedang memilih.', 'warning');
    renderOperatorPage();
    return;
  }

  const confirmed = await confirmDialog({
    title: 'Reset sesi pemilih?',
    subtitle: `${voter.name} · ${voter.code}`,
    iconName: 'rotate',
    tone: 'danger',
    confirmText: 'Ya, reset sesi',
    cancelText: 'Batal',
    body: `<p class="confirm-message">Sesi aktif <strong>${escapeHtml(voter.name)}</strong> akan dihentikan. Tindakan ini digunakan ketika pemilih terputus atau perangkat bermasalah.</p>
      <ul class="confirm-list">
        <li>Status kembali menjadi <strong>Belum memilih</strong>.</li>
        <li>Tautan dan QR lama dicabut, sehingga sesi sebelumnya tidak dapat dilanjutkan.</li>
        <li>QR baru diterbitkan otomatis untuk diberikan kepada pemilih.</li>
      </ul>
      <div class="alert alert-warning">${icon('alert')}<div>Suara yang belum dikonfirmasi tidak akan tersimpan.</div></div>`
  });

  if (!confirmed) return;

  const result = resetVoterSession(voter.id);
  if (!result.ok) {
    showToast(result.message || 'Sesi tidak dapat direset.', 'error');
    renderOperatorPage();
    return;
  }

  operatorUI = { query: '', filter: 'all' };
  renderOperatorPage();
  showToast('Sesi direset. QR lama dicabut dan QR baru diterbitkan.', 'success');

  setTimeout(() => {
    if (parseRoute().name === 'operator') showQrModal(result.voter.id, { newToken: true });
  }, 260);
}

function resetVoterSession(voterId) {
  state = readState();
  const voter = state.voters.find((item) => item.id === voterId);
  if (!voter) return { ok: false, message: 'Pemilih tidak ditemukan.' };
  if (voter.status !== 'sedang') {
    return { ok: false, message: 'Sesi sudah tidak aktif. Status pemilih telah berubah.' };
  }

  const revokedAt = new Date().toISOString();
  const oldToken = voter.token;

  state.revokedTokens.push({
    token: oldToken,
    voterId: voter.id,
    revokedAt,
    reason: 'reset_operator'
  });

  voter.token = generateToken();
  voter.tokenVersion += 1;
  voter.status = 'belum';
  voter.startedAt = null;
  voter.activeSession = null;
  voter.resetCount += 1;

  addAudit(
    'Sesi direset operator',
    `${voter.name} · ${voter.code} · token lama dicabut, QR baru diterbitkan`,
    'reset'
  );
  saveState();

  return { ok: true, voter };
}

function showDeviceInfoModal() {
  const body = `
    <div class="alert alert-warning">
      ${icon('alert')}
      <div><strong>Indikator audit, bukan bukti identitas.</strong><p>Data perangkat tidak dapat memastikan siapa orangnya dan tidak boleh menjadi dasar tunggal untuk menolak pemilih.</p></div>
    </div>
    <ul class="confirm-list" style="margin-top:16px">
      <li>Demo mencatat User-Agent, identitas browser lokal, IP simulasi, dan waktu akses.</li>
      <li>Cookie dapat dihapus, browser dapat diganti, dan IP dapat dipakai bersama.</li>
      <li>Penggunaan satu perangkat oleh beberapa pemilih tetap diperbolehkan setelah verifikasi langsung operator.</li>
      <li>Peringatan perangkat hanya membantu pemeriksaan; keputusan tetap berada pada operator.</li>
    </ul>
    <div class="privacy-callout">${icon('lock')} Pada demo ini seluruh data perangkat disimulasikan dan disimpan lokal di browser, bukan dikirim ke server.</div>`;

  openModal({
    title: 'Pemeriksaan perangkat',
    subtitle: 'Batasan dan makna indikator perangkat',
    iconName: 'fingerprint',
    width: '600px',
    body,
    footer: '<button class="btn btn-primary" type="button" data-modal-close>Mengerti</button>'
  });
}

async function showResetDemoConfirm() {
  const confirmed = await confirmDialog({
    title: 'Reset seluruh data demo?',
    subtitle: 'Tindakan ini mengembalikan data contoh awal.',
    iconName: 'refresh',
    tone: 'danger',
    confirmText: 'Ya, reset demo',
    body: `<p class="confirm-message">Seluruh pemilih, status, sesi, QR, dan log audit pada demo akan diganti dengan data awal.</p>
      <ul class="confirm-list">
        <li>QR dan tautan pemilih yang sedang aktif akan hilang.</li>
        <li>Data pemilih dan suara anonim yang dibuat selama demo akan dihapus.</li>
        <li>Identitas perangkat browser tetap dipertahankan.</li>
      </ul>`
  });

  if (!confirmed) return;

  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch (error) {
    console.warn('Data demo tidak dapat dihapus.', error);
  }

  state = createInitialState();
  saveState();
  operatorUI = { query: '', filter: 'all' };
  voteView = null;

  if (parseRoute().name === 'operator') {
    renderOperatorPage();
  } else {
    window.location.hash = '#/operator';
  }
  showToast('Data demo telah dikembalikan ke kondisi awal.', 'info');
}

/* =========================================================
   Simulator page
   ========================================================= */
function renderSimulatorPage() {
  const sortedVoters = [...state.voters].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  const rows = sortedVoters.map((voter) => {
    let hint = 'QR siap digunakan · belum ada sesi';
    if (voter.status === 'sedang') {
      hint = voter.activeSession?.deviceId === DEVICE.id
        ? 'Sesi aktif di perangkat ini — dapat dilanjutkan'
        : 'Sesi aktif di perangkat lain — perlu tindakan operator';
    } else if (voter.status === 'sudah') {
      hint = `Suara tersimpan ${formatShortDateTime(voter.completedAt)} WIB`;
    }

    return `<div class="sim-row">
      <div class="sim-identity">
        ${avatar(voter.name)}
        <div class="voter-id-copy">
          <div class="sim-name">${escapeHtml(voter.name)}</div>
          <div class="sim-hint">${voter.code} · ${escapeHtml(hint)}</div>
        </div>
      </div>
      <div class="sim-status">${statusBadge(voter.status)}</div>
      <div class="sim-action">
        <a class="btn btn-soft" href="#/vote/${encodeURIComponent(voter.token)}">Buka ${icon('arrow-right')}</a>
      </div>
    </div>`;
  }).join('');

  document.getElementById('app').innerHTML = `
    ${siteHeader('simulator')}
    <main class="page container narrow-page view-enter">
      <section class="page-heading">
        <div>
          <p class="eyebrow">${icon('qr')} Mode Pemilih</p>
          <h1>Simulasi Pemilih</h1>
          <p class="lede">Di produksi, halaman voting dibuka dari hasil pemindaian QR. Untuk demo, pilih hak pilih di bawah untuk membuka tautan tokennya.</p>
        </div>
        <div class="heading-actions">
          <a class="btn btn-secondary" href="#/operator">${icon('dashboard')} Dashboard Operator</a>
        </div>
      </section>

      <div class="page-alert">
        <div class="alert alert-info">
          <div class="alert-flex">
            ${icon('info')}
            <div><strong>Pemindai QR disimulasikan.</strong><p>QR pada dashboard tidak dapat dipindai kamera. Tautan di bawah mewakili hasil pemindaian dan tetap memakai token acak sekali pakai.</p></div>
          </div>
        </div>
      </div>

      <section class="panel" aria-labelledby="sim-title">
        <div class="panel-head">
          <div>
            <h2 class="panel-title" id="sim-title">${icon('users')} Pilih hak pilih</h2>
            <p class="panel-sub">${state.voters.length} hak pilih tersedia pada demo ini.</p>
          </div>
          <span class="demo-chip"><span class="demo-dot"></span>Data dummy</span>
        </div>
        <div class="sim-list">
          ${rows || '<div class="empty-state"><span class="empty-icon">' + icon('users') + '</span><h3>Belum ada pemilih</h3><p>Tambahkan pemilih dari dashboard operator terlebih dahulu.</p></div>'}
        </div>
      </section>

      <div class="sim-guide">
        <div class="sim-guide-card"><span>Langkah 1</span><p>Buka tautan pemilih</p><small>Mewakili pemindaian QR oleh pemilih di perangkatnya.</small></div>
        <div class="sim-guide-card"><span>Langkah 2</span><p>Mulai dan pilih paslon</p><small>Status berubah menjadi Sedang memilih, lalu Sudah memilih setelah konfirmasi.</small></div>
        <div class="sim-guide-card"><span>Langkah 3</span><p>Uji sesi terputus</p><small>Tinggalkan halaman lalu buka tautan yang sama untuk melanjutkan sesi.</small></div>
      </div>

      <footer class="site-footer">
        <p>${icon('lock')} Prototype demo · pemilih tidak memakai akun atau password.</p>
        <button class="link-button" type="button" id="btn-reset-demo-sim">Reset data demo</button>
      </footer>
    </main>`;

  $('#btn-reset-demo-sim')?.addEventListener('click', showResetDemoConfirm);
}

/* =========================================================
   Public results & live turnout
   ========================================================= */
let electionBoundaryTimer = null;

function scheduleElectionBoundaryRefresh() {
  clearTimeout(electionBoundaryTimer);
  if (isElectionClosed()) return;

  const closesAt = new Date(ELECTION.closesAt).getTime();
  const delay = closesAt - Date.now();
  if (!Number.isFinite(delay) || delay <= 0) return;

  electionBoundaryTimer = setTimeout(() => {
    const route = parseRoute();
    if (route.name === 'results' || route.name === 'total') {
      state = readState();
      refreshCurrentRoute();
    }
  }, Math.min(delay + 350, 2147483647));
}

function getElectionResultData() {
  const totalVotes = state.votes.length;
  const registeredVoters = state.voters.length;
  const turnout = registeredVoters ? (totalVotes / registeredVoters) * 100 : 0;
  const tally = CANDIDATES.map((candidate) => {
    const votes = state.votes.filter((vote) => vote.candidateId === candidate.id).length;
    return {
      ...candidate,
      votes,
      share: totalVotes ? (votes / totalVotes) * 100 : 0
    };
  });
  const highest = tally.reduce((max, candidate) => Math.max(max, candidate.votes), 0);
  const leaders = highest > 0 ? tally.filter((candidate) => candidate.votes === highest) : [];

  return {
    totalVotes,
    registeredVoters,
    turnout,
    tally,
    highest,
    leaders,
    winner: leaders.length === 1 ? leaders[0] : null,
    tie: leaders.length > 1
  };
}

function publicStatusPill(closed) {
  return `<span class="public-status ${closed ? 'is-closed' : 'is-live'}">
    <span class="public-status-dot"></span>${closed ? 'Pemungutan suara selesai' : 'Pemungutan suara berlangsung'}
  </span>`;
}

function resultRowMarkup(candidate, winnerId) {
  const isWinner = candidate.id === winnerId;
  return `<article class="result-row${isWinner ? ' is-leading' : ''}">
    <div class="result-candidate">
      <span class="result-number">${padNumber(candidate.number)}</span>
      <img class="result-thumb" src="${candidate.photo}" alt="" loading="lazy">
      <div class="result-name">
        <span class="result-kicker">Paslon ${padNumber(candidate.number)}${isWinner ? ' · Perolehan tertinggi' : ''}</span>
        <strong>${escapeHtml(candidate.chair)} <span class="amp">&amp;</span> ${escapeHtml(candidate.vice)}</strong>
      </div>
    </div>
    <div class="result-share-block">
      <div class="result-bar" aria-hidden="true"><span style="width:${candidate.share.toFixed(2)}%"></span></div>
      <span class="result-share-label">${formatPercent(candidate.share)}</span>
    </div>
    <div class="result-tally">
      <strong>${formatNumber(candidate.votes)}</strong>
      <span>suara</span>
    </div>
  </article>`;
}

function winningResultMarkup(data) {
  if (!data.totalVotes) {
    return `<div class="public-empty-result">
      ${icon('chart')}
      <div><strong>Belum ada suara yang tercatat.</strong><p>Rekapitulasi paslon akan tampil di sini setelah suara pertama masuk.</p></div>
    </div>`;
  }

  if (data.tie) {
    return `<div class="public-tie-result">
      ${icon('info')}
      <div><strong>Perolehan suara tertinggi berimbang.</strong><p>${data.leaders.map((candidate) => `Paslon ${padNumber(candidate.number)} (${formatNumber(candidate.votes)} suara)`).join(' dan ')} memiliki jumlah suara yang sama. Penetapan dilakukan sesuai mekanisme panitia.</p></div>
    </div>`;
  }

  const winner = data.winner;
  return `<section class="winner-card" aria-labelledby="winner-title">
    <div class="winner-copy">
      <span class="winner-kicker">${icon('trophy')} Perolehan suara tertinggi</span>
      <p class="winner-label">Pasangan Calon ${padNumber(winner.number)}</p>
      <h2 id="winner-title">${escapeHtml(winner.chair)} <span class="amp">&amp;</span> ${escapeHtml(winner.vice)}</h2>
      <div class="winner-count">
        <strong>${formatNumber(winner.votes)}</strong>
        <span>suara · ${formatPercent(winner.share)} dari total suara</span>
      </div>
      <p class="winner-note">${icon('info')} Rekapitulasi demo, bukan penetapan resmi panitia.</p>
    </div>
    <div class="winner-media">
      <img src="${winner.photo}" alt="Ilustrasi ${escapeHtml(winner.chair)} dan ${escapeHtml(winner.vice)}">
      <span class="winner-stamp">${icon('check-circle')} Suara terbanyak</span>
    </div>
  </section>`;
}

function renderPublicResultsPage() {
  state = readState();
  scheduleElectionBoundaryRefresh();
  const closed = isElectionClosed();
  const data = getElectionResultData();

  if (!closed) {
    document.getElementById('app').innerHTML = `
      ${publicHeader('results')}
      <main class="page container public-page view-enter">
        <section class="locked-results" aria-labelledby="locked-title">
          <div class="locked-visual">
            <span class="locked-ring"></span>
            <span class="locked-icon">${icon('lock')}</span>
          </div>
          <p class="eyebrow">${icon('chart')} Hasil belum dibuka</p>
          <h1 id="locked-title">Hasil pemilu tersedia setelah masa pemungutan suara berakhir</h1>
          <p class="locked-lede">Rincian perolehan masing-masing paslon baru ditampilkan setelah pemungutan suara ditutup. Ini menjaga agar informasi hasil tidak memengaruhi pemilih yang belum memberikan suara.</p>
          <div class="locked-schedule">
            <span>${icon('calendar')} Pemungutan suara berakhir</span>
            <strong>${escapeHtml(formatLongDateTime(ELECTION.closesAt))}</strong>
          </div>
          <div class="locked-actions">
            <a class="btn btn-primary" href="#/total-suara">${icon('users')} Lihat total suara saat ini</a>
            <a class="btn btn-ghost" href="#/operator">Kembali ke dashboard</a>
          </div>
          <p class="locked-footnote">${icon('lock')} Halaman ini tidak memuat perolehan per paslon sebelum waktu tutup.</p>
        </section>
        ${publicFooter()}
      </main>`;
    return;
  }

  document.getElementById('app').innerHTML = `
    ${publicHeader('results')}
    <main class="page container public-page view-enter">
      <section class="results-hero">
        <div class="results-hero-copy">
          ${publicStatusPill(true)}
          <p class="eyebrow">${icon('chart')} Rekapitulasi publik · ${ELECTION.school}</p>
          <h1>Hasil ${escapeHtml(ELECTION.title)} ${ELECTION.year}</h1>
          <p class="results-lede">Perolehan suara dihitung dari data suara anonim. Identitas pemilih dan pilihan perorangan tidak ditampilkan.</p>
          <div class="results-hero-meta">
            <span>${icon('calendar')} Ditutup <strong>${escapeHtml(formatLongDateTime(ELECTION.closesAt))}</strong></span>
            <span>${icon('lock')} Suara anonim</span>
          </div>
        </div>
        <div class="results-hero-total" aria-label="Total suara sah">
          <span>Total suara sah</span>
          <strong>${formatNumber(data.totalVotes)}</strong>
          <small>dari ${formatNumber(data.registeredVoters)} hak pilih</small>
        </div>
      </section>

      <section class="result-summary-grid" aria-label="Ringkasan partisipasi">
        <article class="result-summary-card">
          <span class="result-summary-icon">${icon('users')}</span>
          <div><strong>${formatNumber(data.registeredVoters)}</strong><span>Hak pilih terdaftar</span></div>
        </article>
        <article class="result-summary-card">
          <span class="result-summary-icon is-teal">${icon('activity')}</span>
          <div><strong>${formatPercent(data.turnout)}</strong><span>Partisipasi pemilih</span></div>
        </article>
        <article class="result-summary-card">
          <span class="result-summary-icon is-amber">${icon('clock')}</span>
          <div><strong>${escapeHtml(formatTime(ELECTION.closesAt))}</strong><span>Waktu pemungutan suara ditutup</span></div>
        </article>
      </section>

      ${winningResultMarkup(data)}

      <section class="panel results-panel" aria-labelledby="results-list-title">
        <div class="panel-head">
          <div>
            <h2 class="panel-title" id="results-list-title">${icon('chart')} Perolehan suara per paslon</h2>
            <p class="panel-sub">Rekap dari ${formatNumber(data.totalVotes)} suara anonim yang tersimpan.</p>
          </div>
          <span class="result-total-chip">${icon('check-circle')} Penghitungan selesai</span>
        </div>
        <div class="result-list">
          ${data.tally.map((candidate) => resultRowMarkup(candidate, data.winner?.id)).join('')}
        </div>
      </section>

      <div class="public-note">
        ${icon('info')}
        <p>Hasil ini merupakan rekapitulasi data demo dan belum menjadi penetapan resmi. Sesuai prinsip kerahasiaan, halaman publik hanya menghitung suara anonim dan tidak dapat menelusuri pilihan ke pemilih.</p>
      </div>

      ${publicFooter()}
    </main>`;
}

function renderPublicTotalPage() {
  state = readState();
  scheduleElectionBoundaryRefresh();
  const closed = isElectionClosed();
  const data = getElectionResultData();
  const remaining = Math.max(0, data.registeredVoters - data.totalVotes);
  const progress = Math.min(100, data.turnout);
  const updatedAt = new Date().toISOString();

  document.getElementById('app').innerHTML = `
    ${publicHeader('total')}
    <main class="page container public-page view-enter">
      <section class="total-hero">
        <div class="total-hero-copy">
          ${publicStatusPill(closed)}
          <p class="eyebrow">${icon('eye')} Halaman publik · ${ELECTION.school}</p>
          <h1>Total suara saat ini</h1>
          <p>Jumlah suara yang sudah masuk secara keseluruhan. Halaman ini tidak menampilkan perolehan masing-masing paslon.</p>
          <p class="total-period">${icon('calendar')} ${closed ? 'Pemungutan suara ditutup' : 'Pemungutan suara berlangsung hingga'} <strong>${escapeHtml(formatLongDateTime(ELECTION.closesAt))}</strong></p>
        </div>

        <div class="total-board">
          <span class="total-board-label"><span class="live-dot"></span>Suara tercatat</span>
          <strong class="total-number" aria-live="polite">${formatNumber(data.totalVotes)}</strong>
          <span class="total-board-sub">dari ${formatNumber(data.registeredVoters)} hak pilih</span>
          <div class="total-progress" role="progressbar" aria-label="Partisipasi pemilih" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${progress.toFixed(1)}">
            <span style="width:${progress.toFixed(2)}%"></span>
          </div>
          <div class="total-board-foot">
            <span>${formatPercent(data.turnout)} partisipasi</span>
            <span>${formatNumber(remaining)} belum tercatat</span>
          </div>
        </div>
      </section>

      <section class="total-facts" aria-label="Ringkasan data suara">
        <article class="total-fact">
          <span class="total-fact-icon">${icon('users')}</span>
          <div><span>Hak pilih terdaftar</span><strong>${formatNumber(data.registeredVoters)}</strong></div>
        </article>
        <article class="total-fact">
          <span class="total-fact-icon is-teal">${icon('activity')}</span>
          <div><span>Partisipasi saat ini</span><strong>${formatPercent(data.turnout)}</strong></div>
        </article>
        <article class="total-fact">
          <span class="total-fact-icon is-amber">${icon('clock')}</span>
          <div><span>Diperbarui</span><strong>${formatTime(updatedAt)}</strong></div>
        </article>
      </section>

      <section class="total-privacy-card">
        <div class="total-privacy-copy">
          <span class="total-privacy-icon">${icon('lock')}</span>
          <div>
            <h2>Hanya total, tanpa rincian paslon</h2>
            <p>Halaman ini sengaja menampilkan agregat suara saja. Perolehan tiap paslon hanya dibuka di halaman hasil setelah masa pemungutan suara berakhir.</p>
          </div>
        </div>
        <a class="btn btn-secondary" href="#/hasil">
          ${closed ? 'Lihat hasil lengkap' : 'Cek status hasil'} ${icon('arrow-right')}
        </a>
      </section>

      <div class="public-updated">
        <span>${icon('refresh')} Data demo dari browser ini · diperbarui ${formatTime(updatedAt)}</span>
        <button class="link-button" type="button" id="btn-refresh-total">Perbarui sekarang</button>
      </div>

      ${publicFooter()}
    </main>`;

  $('#btn-refresh-total')?.addEventListener('click', () => {
    state = readState();
    renderPublicTotalPage();
    showToast('Data total suara diperbarui.', 'info');
  });
}

/* =========================================================
   Voter page views
   ========================================================= */
function voteShell(step, content) {
  const withStepper = ['intro', 'ballot', 'confirm', 'success'].includes(step);

  return `<header class="vote-header">
    <div class="vote-header-inner container">
      ${brandLink()}
      <div class="vote-header-side">
        <span class="demo-chip"><span class="demo-dot"></span>Mode Demo</span>
        <a class="ghost-link" href="#/operator">${icon('arrow-left')} Dashboard operator</a>
      </div>
    </div>
  </header>
  <main class="vote-main">
    ${withStepper ? voteStepper(step) : ''}
    <div class="view-enter">${content}</div>
    <p class="vote-footnote">${icon('lock')} Prototype demo · data disimpan lokal di browser, tanpa backend</p>
  </main>`;
}

function introView(voter) {
  return `<section class="vote-card intro-layout" aria-labelledby="intro-title">
    <div class="intro-main">
      <p class="eyebrow">${icon('shield')} Hak pilih terverifikasi</p>
      <h1 id="intro-title">Halo, ${escapeHtml(firstName(voter.name))}.</h1>
      <p class="lede">Anda terdaftar sebagai pemilih pada ${ELECTION.title} ${ELECTION.year}. Periksa kembali identitas Anda sebelum memulai.</p>

      <div class="intro-rules">
        <div class="rule-item">
          <span class="rule-icon">${icon('ballot')}</span>
          <div><strong>Satu pasangan calon</strong><span>Pilih satu dari ${CANDIDATES.length} paslon, lalu periksa kembali sebelum mengirim.</span></div>
        </div>
        <div class="rule-item">
          <span class="rule-icon">${icon('lock')}</span>
          <div><strong>Pilihan Anda rahasia</strong><span>Operator hanya melihat status, bukan paslon yang Anda pilih.</span></div>
        </div>
        <div class="rule-item">
          <span class="rule-icon">${icon('alert')}</span>
          <div><strong>Token sekali pakai</strong><span>Setelah dikonfirmasi, tautan yang sama tidak dapat menghasilkan suara kedua.</span></div>
        </div>
      </div>

      <button class="btn btn-primary btn-lg" type="button" id="btn-start-vote">${icon('ballot')} Mulai Memilih</button>
      <p class="micro">Dengan menekan tombol, status Anda berubah menjadi <strong>Sedang memilih</strong>. Anda dapat melanjutkan sesi jika halaman terputus.</p>
    </div>

    <aside class="voter-pass" aria-label="Kartu pemilih">
      <div class="pass-top">
        <span class="pass-brand">${icon('ballot')} Kartu Pemilih</span>
        <span class="pass-live">Terverifikasi</span>
      </div>
      <h2 class="pass-name">${escapeHtml(voter.name)}</h2>
      <p class="pass-sub">${ELECTION.school} · ${ELECTION.year}</p>
      <dl class="pass-grid">
        <div><dt>Kode hak pilih</dt><dd>${voter.code}</dd></div>
        <div><dt>Status</dt><dd>${statusBadge('belum')}</dd></div>
        <div><dt>Perangkat</dt><dd>${escapeHtml(DEVICE.label)}</dd></div>
        <div><dt>Token</dt><dd>Sekali pakai</dd></div>
      </dl>
      <div class="pass-stripe" aria-hidden="true"></div>
      <p class="pass-foot">${icon('lock')} Token diverifikasi otomatis oleh sistem</p>
    </aside>
  </section>

  <p class="vote-secondary-note">${icon('info')} Bukan nama Anda, atau tidak bisa memulai? Jangan lanjutkan dan hubungi operator untuk pemeriksaan.</p>`;
}

function candidateCardMarkup(candidate, selected) {
  const number = padNumber(candidate.number);
  return `<div class="candidate-card${selected ? ' is-selected' : ''}" role="button" tabindex="0" data-candidate="${candidate.id}" aria-pressed="${selected}" aria-label="Pilih pasangan calon nomor ${number}, ${escapeHtml(candidate.chair)} dan ${escapeHtml(candidate.vice)}">
    <div class="candidate-media">
      <img class="candidate-photo" src="${candidate.photo}" alt="Foto dummy ${escapeHtml(candidate.chair)} dan ${escapeHtml(candidate.vice)}" loading="lazy">
      <span class="candidate-number">${number}</span>
      <span class="candidate-check" aria-hidden="true">${icon('check')}</span>
    </div>
    <div class="candidate-body">
      <p class="candidate-kicker">Pasangan Calon ${number}</p>
      <h3 class="candidate-names">${escapeHtml(candidate.chair)} <span class="amp">&amp;</span> ${escapeHtml(candidate.vice)}</h3>
      <div class="candidate-vision">
        <span>Visi</span>
        <p>${escapeHtml(candidate.vision)}</p>
      </div>
      <ul class="mission-list">
        ${candidate.missions.slice(0, 2).map((mission) => `<li>${escapeHtml(mission)}</li>`).join('')}
      </ul>
      <span class="candidate-select-hint">${icon('check-circle')} Paslon ini dipilih</span>
    </div>
  </div>`;
}

function ballotView(voter) {
  const selected = getCandidate(voteView.selected);
  const selectionLabel = selected
    ? `Paslon ${padNumber(selected.number)} · ${selected.chair} & ${selected.vice}`
    : 'Belum memilih';

  return `<div class="ballot-shell">
    <div class="ballot-head">
      <div>
        <p class="eyebrow">${icon('ballot')} Langkah 2 dari 4</p>
        <h1>Pilih pasangan calon</h1>
        <p class="lede">Pilih satu paslon. Anda masih dapat mengubah pilihan sebelum konfirmasi akhir.</p>
      </div>
      <div class="ballot-voter">
        ${avatar(voter.name)}
        <div><strong>${escapeHtml(voter.name)}</strong><small>${voter.code}</small></div>
      </div>
    </div>

    ${voteView.resumed ? `<div class="resume-banner">${icon('refresh')} Sesi sebelumnya ditemukan di browser ini. Silakan lanjutkan memilih — pilihan yang belum dikonfirmasi belum tersimpan.</div>` : ''}

    <div class="candidate-grid" role="group" aria-label="Daftar pasangan calon">
      ${CANDIDATES.map((candidate) => candidateCardMarkup(candidate, selected?.id === candidate.id)).join('')}
    </div>

    <div class="ballot-bar">
      <div class="ballot-bar-info">
        <span>Pilihan Anda</span>
        <strong id="selection-label">${escapeHtml(selectionLabel)}</strong>
      </div>
      <button class="btn btn-primary" type="button" id="btn-to-confirm" ${selected ? '' : 'disabled'}>
        Lanjut ke Konfirmasi ${icon('arrow-right')}
      </button>
    </div>

    <p class="session-note">${icon('monitor')} Sesi tersimpan di browser ini. Jika halaman ditutup, buka kembali tautan QR yang sama untuk melanjutkan. Operator dapat mereset sesi jika diperlukan.</p>
  </div>`;
}

function confirmView(voter) {
  const candidate = getCandidate(voteView.selected);
  if (!candidate) return ballotView(voter);

  return `<section class="vote-card confirm-card" aria-labelledby="confirm-title">
    <div class="confirm-top">
      <span class="confirm-icon">${icon('check-circle')}</span>
      <p class="eyebrow" style="justify-content:center">${icon('ballot')} Langkah 3 dari 4</p>
      <h1 id="confirm-title">Konfirmasi pilihan Anda</h1>
      <p>Periksa kembali pasangan calon yang Anda pilih. Setelah dikonfirmasi, pilihan tidak dapat diubah.</p>
    </div>

    <div class="confirm-summary">
      <img class="confirm-photo" src="${candidate.photo}" alt="Foto dummy ${escapeHtml(candidate.chair)} dan ${escapeHtml(candidate.vice)}">
      <div class="confirm-candidate">
        <span>Pasangan Calon ${padNumber(candidate.number)}</span>
        <h2>${escapeHtml(candidate.chair)} <span class="amp">&amp;</span> ${escapeHtml(candidate.vice)}</h2>
        <p>${escapeHtml(candidate.vision)}</p>
      </div>
    </div>

    <div class="alert alert-warning">
      ${icon('alert')}
      <div><strong>Periksa dengan teliti.</strong><p>Suara disimpan anonim dan tidak dapat diubah setelah tombol konfirmasi ditekan.</p></div>
    </div>

    <div class="confirm-actions">
      <button class="btn btn-secondary" type="button" id="btn-back-ballot">${icon('arrow-left')} Ubah pilihan</button>
      <button class="btn btn-primary" type="button" id="btn-confirm-vote">${icon('lock')} Konfirmasi Pilihan</button>
    </div>
  </section>`;
}

function successView(voter) {
  return `<section class="vote-card success-card" aria-labelledby="success-title">
    <div class="success-badge">${icon('check-circle')}</div>
    <p class="eyebrow" style="justify-content:center">${icon('check')} Langkah 4 dari 4</p>
    <h1 id="success-title">Suara berhasil disimpan</h1>
    <p>Terima kasih, ${escapeHtml(firstName(voter.name))}. Partisipasi Anda pada ${ELECTION.title} ${ELECTION.year} sudah tercatat.</p>

    <div class="success-meta">
      <div><span>Status hak pilih</span><strong>Sudah memilih</strong></div>
      <div><span>Waktu penyimpanan</span><strong>${formatDateTime(voter.completedAt)}</strong></div>
    </div>

    <div class="alert alert-success" style="text-align:left">
      ${icon('check-circle')}
      <div><strong>Token tidak dapat digunakan lagi.</strong><p>QR dan tautan ini tidak dapat menghasilkan suara kedua. Pilihan Anda disimpan terpisah dari data identitas.</p></div>
    </div>

    <div class="success-actions">
      <a class="btn btn-primary" href="#/operator">Selesai &amp; kembali ke dashboard (demo) ${icon('arrow-right')}</a>
    </div>
    <p class="micro">Operator hanya melihat status “Sudah memilih”, bukan paslon yang Anda pilih.</p>
  </section>`;
}

function statePage({ tone = 'is-neutral', iconName = 'info', eyebrow = '', title, text, details = [], actions = '' }) {
  return `<section class="state-page">
    <div class="state-icon ${tone}">${icon(iconName)}</div>
    ${eyebrow ? `<p class="eyebrow">${icon('shield')} ${escapeHtml(eyebrow)}</p>` : ''}
    <h1>${escapeHtml(title)}</h1>
    <p>${escapeHtml(text)}</p>
    ${details.length ? `<dl class="state-details">${details.map((item) => `<div><dt>${escapeHtml(item.label)}</dt><dd>${escapeHtml(item.value)}</dd></div>`).join('')}</dl>` : ''}
    <div class="state-actions">${actions}</div>
  </section>`;
}

function invalidTokenView() {
  return statePage({
    tone: 'is-danger',
    iconName: 'alert',
    eyebrow: 'Tautan tidak dikenali',
    title: 'Tautan pemilihan tidak valid',
    text: 'Token tidak ditemukan pada pemilihan ini. Periksa kembali tautan dari operator atau minta QR baru.',
    actions: `<a class="btn btn-primary" href="#/operator">Kembali ke dashboard (demo)</a><a class="btn btn-ghost" href="#/simulator">Buka simulasi pemilih</a>`
  });
}

function revokedTokenView(revoked) {
  const voter = revoked ? state.voters.find((item) => item.id === revoked.voterId) : null;
  return statePage({
    tone: 'is-danger',
    iconName: 'rotate',
    eyebrow: 'Sesi telah dicabut',
    title: 'Tautan ini sudah tidak berlaku',
    text: 'Operator telah mereset sesi dan mencabut QR lama. Sesi sebelumnya tidak dapat digunakan kembali; gunakan QR baru yang diterbitkan operator.',
    details: [
      { label: 'Nama pemilih', value: voter?.name || 'Tidak tersedia' },
      { label: 'Kode hak pilih', value: voter?.code || '—' },
      { label: 'Dicabut pada', value: formatDateTime(revoked?.revokedAt) },
      { label: 'Status saat ini', value: voter ? STATUS_META[voter.status].label : 'Belum memilih' }
    ],
    actions: `<a class="btn btn-primary" href="#/operator">Kembali ke dashboard (demo)</a><a class="btn btn-ghost" href="#/simulator">Buka simulasi pemilih</a>`
  });
}

function usedTokenView(voter) {
  return statePage({
    tone: 'is-neutral',
    iconName: 'check-circle',
    eyebrow: 'Token sudah digunakan',
    title: 'Suara untuk token ini sudah tersimpan',
    text: 'Setiap token hanya dapat menghasilkan satu suara. Untuk alasan kerahasiaan, halaman ini tidak menampilkan pilihan yang pernah disimpan.',
    details: [
      { label: 'Nama pemilih', value: voter.name },
      { label: 'Kode hak pilih', value: voter.code },
      { label: 'Status', value: STATUS_META.sudah.label },
      { label: 'Disimpan pada', value: formatDateTime(voter.completedAt) }
    ],
    actions: `<a class="btn btn-primary" href="#/operator">Kembali ke dashboard (demo)</a>`
  });
}

function conflictTokenView(voter) {
  return statePage({
    tone: '',
    iconName: 'wifi-off',
    eyebrow: 'Sesi tidak dapat dilanjutkan',
    title: 'Sesi sedang berjalan di perangkat lain',
    text: 'Token ini sudah dipakai untuk memulai sesi voting. Demi mencegah suara ganda, sesi tidak dapat dibuka dari perangkat lain. Hubungi operator untuk verifikasi dan reset manual.',
    details: [
      { label: 'Nama pemilih', value: voter.name },
      { label: 'Kode hak pilih', value: voter.code },
      { label: 'Perangkat sesi', value: voter.activeSession?.deviceLabel || 'Tidak diketahui' },
      { label: 'Sesi dimulai', value: formatDateTime(voter.activeSession?.startedAt) }
    ],
    actions: `<button class="btn btn-secondary" type="button" id="btn-check-status">${icon('refresh')} Periksa status</button><a class="btn btn-primary" href="#/operator">Dashboard operator</a>`
  });
}

/* =========================================================
   Vote routing, bindings, and state transitions
   ========================================================= */
function enterVote(token, options = {}) {
  const { logAccess = true } = options;
  state = readState();

  const voter = state.voters.find((item) => item.token === token) || null;
  const revoked = state.revokedTokens.find((item) => item.token === token) || null;
  let step = 'invalid';

  if (voter) {
    if (voter.status === 'belum') step = 'intro';
    else if (voter.status === 'sudah') step = 'used';
    else if (voter.status === 'sedang') {
      step = voter.activeSession?.deviceId === DEVICE.id ? 'ballot' : 'conflict';
    }
  } else if (revoked) {
    step = 'revoked';
  }

  voteView = {
    token,
    step,
    selected: null,
    resumed: step === 'ballot'
  };

  if (voter && logAccess) {
    let action = 'Membuka tautan QR';
    let result = 'berhasil';
    if (step === 'conflict') {
      action = 'Percobaan akses dari perangkat lain';
      result = 'ditolak';
    } else if (step === 'used') {
      action = 'Membuka tautan yang sudah digunakan';
      result = 'ditolak';
    } else if (step === 'ballot') {
      action = 'Melanjutkan sesi voting';
    }
    addDeviceEvent(voter, action, result);
    saveState();
  }

  renderVotePage();
}

function renderVotePage() {
  if (!voteView) return;

  const token = voteView.token;
  const voter = state.voters.find((item) => item.token === token) || null;
  const revoked = state.revokedTokens.find((item) => item.token === token) || null;
  let step = voteView.step;

  if (!voter) {
    step = revoked ? 'revoked' : 'invalid';
  } else if (voter.status === 'belum') {
    if (step !== 'intro') step = 'intro';
  } else if (voter.status === 'sudah') {
    if (step !== 'success') step = 'used';
  } else if (voter.status === 'sedang') {
    const sameDevice = voter.activeSession?.deviceId === DEVICE.id;
    if (step === 'intro' || step === 'used' || step === 'success') {
      step = sameDevice ? 'ballot' : 'conflict';
    }
  }

  if (step === 'confirm' && !getCandidate(voteView.selected)) step = 'ballot';
  voteView.step = step;

  let content = '';
  if (step === 'invalid') content = invalidTokenView();
  else if (step === 'revoked') content = revokedTokenView(revoked);
  else if (step === 'used' && voter) content = usedTokenView(voter);
  else if (step === 'conflict' && voter) content = conflictTokenView(voter);
  else if (step === 'intro' && voter) content = introView(voter);
  else if (step === 'ballot' && voter) content = ballotView(voter);
  else if (step === 'confirm' && voter) content = confirmView(voter);
  else if (step === 'success' && voter) content = successView(voter);
  else content = invalidTokenView();

  document.getElementById('app').innerHTML = voteShell(step, content);
  bindVoteEvents(step, voter);
  window.scrollTo({ top: 0, behavior: 'auto' });
}

function bindVoteEvents(step, voter) {
  if (step === 'intro') {
    const startButton = $('#btn-start-vote');
    startButton?.addEventListener('click', () => startVoting(startButton));
    return;
  }

  if (step === 'ballot') {
    const cards = $$('.candidate-card');
    const confirmButton = $('#btn-to-confirm');
    const selectionLabel = $('#selection-label');

    const selectCandidate = (candidateId) => {
      const candidate = getCandidate(candidateId);
      if (!candidate) return;
      voteView.selected = candidate.id;

      cards.forEach((card) => {
        const active = card.dataset.candidate === candidate.id;
        card.classList.toggle('is-selected', active);
        card.setAttribute('aria-pressed', String(active));
      });

      if (selectionLabel) {
        selectionLabel.textContent = `Paslon ${padNumber(candidate.number)} · ${candidate.chair} & ${candidate.vice}`;
      }
      if (confirmButton) confirmButton.disabled = false;
    };

    cards.forEach((card) => {
      card.addEventListener('click', () => selectCandidate(card.dataset.candidate));
      card.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          selectCandidate(card.dataset.candidate);
        }
      });
    });

    confirmButton?.addEventListener('click', () => {
      if (!voteView.selected) {
        showToast('Pilih satu pasangan calon terlebih dahulu.', 'error');
        return;
      }
      voteView.step = 'confirm';
      renderVotePage();
    });
    return;
  }

  if (step === 'confirm') {
    $('#btn-back-ballot')?.addEventListener('click', () => {
      voteView.step = 'ballot';
      renderVotePage();
    });
    const submitButton = $('#btn-confirm-vote');
    submitButton?.addEventListener('click', () => submitVote(submitButton));
    return;
  }

  if (step === 'conflict') {
    $('#btn-check-status')?.addEventListener('click', () => {
      state = readState();
      enterVote(voteView.token, { logAccess: false });
      showToast('Status sesi diperiksa ulang.', 'info');
    });
  }
}

async function startVoting(button) {
  if (!voteView || voteView.step !== 'intro') return;
  const token = voteView.token;

  setButtonLoading(button, true, 'Memulai sesi...');
  await sleep(700);

  if (!voteView || voteView.token !== token || parseRoute().name !== 'vote') return;

  state = readState();
  const voter = state.voters.find((item) => item.token === token);
  if (!voter) {
    enterVote(token, { logAccess: false });
    return;
  }

  if (voter.status === 'sudah') {
    voteView.step = 'used';
    renderVotePage();
    return;
  }

  if (voter.status === 'sedang') {
    if (voter.activeSession?.deviceId === DEVICE.id) {
      voteView.step = 'ballot';
      voteView.resumed = true;
      renderVotePage();
      showToast('Sesi yang sudah aktif dilanjutkan.', 'info');
    } else {
      voteView.step = 'conflict';
      renderVotePage();
      showToast('Sesi sedang berjalan di perangkat lain.', 'error');
    }
    return;
  }

  if (voter.status !== 'belum') {
    setButtonLoading(button, false);
    showToast('Status hak pilih tidak dapat memulai sesi baru.', 'error');
    return;
  }

  const now = new Date().toISOString();
  voter.status = 'sedang';
  voter.startedAt = now;
  voter.activeSession = {
    id: uid('ses'),
    deviceId: DEVICE.id,
    deviceLabel: DEVICE.label,
    ip: DEVICE.ip,
    startedAt: now
  };
  addDeviceEvent(voter, 'Memulai sesi voting');
  addAudit('Sesi voting dimulai', `${voter.name} · ${voter.code}`, 'start', 'Pemilih');
  saveState();

  voteView.step = 'ballot';
  voteView.selected = null;
  voteView.resumed = false;
  showToast('Sesi dimulai. Silakan pilih satu pasangan calon.', 'success');
  renderVotePage();
}

function refreshCurrentRoute() {
  const route = parseRoute();
  if (route.name === 'operator') renderOperatorPage();
  else if (route.name === 'simulator') renderSimulatorPage();
  else if (route.name === 'results') renderPublicResultsPage();
  else if (route.name === 'total') renderPublicTotalPage();
  else if (route.name === 'vote') enterVote(route.token, { logAccess: false });
}

async function submitVote(button) {
  if (!voteView || voteView.step !== 'confirm') return;
  const token = voteView.token;
  const candidate = getCandidate(voteView.selected);

  if (!candidate) {
    voteView.step = 'ballot';
    renderVotePage();
    return;
  }

  setButtonLoading(button, true, 'Menyimpan suara...');
  await sleep(950);

  state = readState();
  const voter = state.voters.find((item) => item.token === token);
  const sessionValid = voter
    && voter.status === 'sedang'
    && voter.activeSession
    && voter.activeSession.deviceId === DEVICE.id;

  if (!sessionValid) {
    showToast('Sesi tidak lagi aktif atau telah direset. Suara tidak tersimpan.', 'error');
    if (parseRoute().name === 'vote' && voteView?.token === token) {
      enterVote(token, { logAccess: false });
    } else {
      refreshCurrentRoute();
    }
    return;
  }

  const now = new Date().toISOString();
  state.votes.push({
    id: uid('suara'),
    candidateId: candidate.id,
    castAt: now
  });
  voter.status = 'sudah';
  voter.completedAt = now;
  voter.activeSession = null;
  addDeviceEvent(voter, 'Menyimpan suara');
  addAudit(
    'Suara anonim tersimpan',
    `${voter.name} · ${voter.code} · pilihan tidak dihubungkan ke identitas`,
    'confirm',
    'Sistem'
  );
  saveState();

  const stillHere = voteView && voteView.token === token && parseRoute().name === 'vote';
  if (stillHere) {
    voteView.step = 'success';
    voteView.selected = null;
    voteView.resumed = false;
    renderVotePage();
  } else {
    refreshCurrentRoute();
  }
  showToast('Suara berhasil disimpan.', 'success');
}

/* =========================================================
   Simulated QR renderer
   ========================================================= */
function hashString(value) {
  let hash = 2166136261;
  for (let index = 0; index < value.length; index += 1) {
    hash ^= value.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }
  return hash >>> 0;
}

function mulberry32(seed) {
  let value = seed;
  return function random() {
    value |= 0;
    value = (value + 0x6d2b79f5) | 0;
    let result = Math.imul(value ^ (value >>> 15), 1 | value);
    result = (result + Math.imul(result ^ (result >>> 7), 61 | result)) ^ result;
    return ((result ^ (result >>> 14)) >>> 0) / 4294967296;
  };
}

function drawQrCode(canvas, token) {
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  const gridSize = 29;
  const quietZone = 4;
  const dataSize = gridSize - quietZone * 2;
  const cells = Array.from({ length: gridSize }, () => Array(gridSize).fill(false));
  const reserved = Array.from({ length: gridSize }, () => Array(gridSize).fill(false));
  const random = mulberry32(hashString(token));

  const setCell = (dataX, dataY, dark) => {
    if (dataX < 0 || dataY < 0 || dataX >= dataSize || dataY >= dataSize) return;
    cells[dataY + quietZone][dataX + quietZone] = dark;
  };

  const markFinder = (dataX, dataY) => {
    for (let y = -1; y <= 7; y += 1) {
      for (let x = -1; x <= 7; x += 1) {
        const gridY = dataY + y + quietZone;
        const gridX = dataX + x + quietZone;
        if (gridY >= 0 && gridY < gridSize && gridX >= 0 && gridX < gridSize) {
          reserved[gridY][gridX] = true;
          cells[gridY][gridX] = false;
        }
      }
    }

    for (let y = 0; y < 7; y += 1) {
      for (let x = 0; x < 7; x += 1) {
        const border = x === 0 || x === 6 || y === 0 || y === 6;
        const core = x >= 2 && x <= 4 && y >= 2 && y <= 4;
        setCell(dataX + x, dataY + y, border || core);
        reserved[dataY + y + quietZone][dataX + x + quietZone] = true;
      }
    }
  };

  markFinder(0, 0);
  markFinder(dataSize - 7, 0);
  markFinder(0, dataSize - 7);

  for (let y = 8; y < dataSize - 8; y += 1) {
    setCell(6, y, y % 2 === 0);
    reserved[y + quietZone][6 + quietZone] = true;
  }
  for (let x = 8; x < dataSize - 8; x += 1) {
    setCell(x, 6, x % 2 === 0);
    reserved[6 + quietZone][x + quietZone] = true;
  }

  for (let y = 0; y < dataSize; y += 1) {
    for (let x = 0; x < dataSize; x += 1) {
      const gridY = y + quietZone;
      const gridX = x + quietZone;
      if (reserved[gridY][gridX]) continue;
      cells[gridY][gridX] = random() > 0.54;
    }
  }

  const displaySize = 232;
  const scale = canvas.width / displaySize;
  ctx.setTransform(scale, 0, 0, scale, 0, 0);
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, displaySize, displaySize);

  const cellSize = displaySize / gridSize;
  ctx.fillStyle = '#0b1730';
  for (let y = 0; y < gridSize; y += 1) {
    for (let x = 0; x < gridSize; x += 1) {
      if (!cells[y][x]) continue;
      ctx.fillRect(
        Math.floor(x * cellSize),
        Math.floor(y * cellSize),
        Math.ceil(cellSize),
        Math.ceil(cellSize)
      );
    }
  }

  ctx.strokeStyle = 'rgba(11, 23, 48, 0.12)';
  ctx.lineWidth = 1;
  ctx.strokeRect(0.5, 0.5, displaySize - 1, displaySize - 1);
}

/* =========================================================
   Router, cross-tab sync, and boot
   ========================================================= */
function onRoute() {
  closeModal();
  const route = parseRoute();
  const routeTitles = {
    operator: 'Dashboard Operator',
    simulator: 'Simulasi Pemilih',
    results: 'Hasil Pemilu',
    total: 'Total Suara'
  };
  document.title = `${routeTitles[route.name] || 'Memilih'} — SuaraSekolah`;

  if (route.name === 'vote') {
    if (!voteView || voteView.token !== route.token) {
      enterVote(route.token);
    } else {
      renderVotePage();
    }
  } else {
    voteView = null;
    if (route.name === 'simulator') renderSimulatorPage();
    else if (route.name === 'results') renderPublicResultsPage();
    else if (route.name === 'total') renderPublicTotalPage();
    else renderOperatorPage();
  }

  window.scrollTo({ top: 0, behavior: 'auto' });
}

function onStorage(event) {
  if (event.key !== STORAGE_KEY && event.key !== null) return;
  state = readState();
  closeModal({ silent: true });
  refreshCurrentRoute();
  showToast('Data demo diperbarui dari tab lain.', 'info');
}

function initializeApp() {
  state = readState();
  if (!window.location.hash) {
    window.history.replaceState(null, '', '#/operator');
  }
  window.addEventListener('hashchange', onRoute);
  window.addEventListener('storage', onStorage);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) return;
    const route = parseRoute();
    if (route.name === 'results' || route.name === 'total') {
      state = readState();
      refreshCurrentRoute();
    }
  });
  onRoute();
}

initializeApp();

