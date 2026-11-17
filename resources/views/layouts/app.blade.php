<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <meta name="description" content="SuaraSekolah — Sistem E-Voting Sekolah Berbasis QR Token Sekali Pakai" />
  <meta name="theme-color" content="#132a63" />
  <title>{{ isset($title) ? $title . ' — SuaraSekolah' : 'SuaraSekolah — Sistem E-Voting' }}</title>
  <link rel="stylesheet" href="{{ asset('css/suarasekolah.css') }}?v=2" />
  @livewireStyles
</head>
<body>
  @include('partials.svg-sprite')

  <div class="ambient" aria-hidden="true">
    <span class="ambient-orb ambient-orb-1"></span>
    <span class="ambient-orb ambient-orb-2"></span>
    <span class="ambient-grid"></span>
  </div>

  @php
    /** @var \App\Models\StaffAccount|null $staffUser */
    $staffUser = auth('staff')->user();
    $isPublicRoute = request()->routeIs('public.*');
  @endphp

  <header class="site-header {{ $isPublicRoute ? 'public-site-header' : '' }}">
    <div class="container header-inner">
      <a href="{{ $staffUser ? ($staffUser->isAdmin() ? route('admin.operators') : route('operator.elections')) : route('login') }}" class="brand">
        <span class="brand-mark" aria-hidden="true">
          <svg class="icon"><use href="#i-ballot"></use></svg>
        </span>
        <span class="brand-text">
          <strong>SuaraSekolah</strong>
          <small>Sistem E-Voting QR Token</small>
        </span>
      </a>

      {{-- FR-004, BR-021: Navigasi utama sesuai peran staf atau publik --}}
      @if($staffUser && !$staffUser->needsActivation() && (!$staffUser->isAdmin() || session('staff_2fa_verified', false)))
        <nav class="main-nav" aria-label="Navigasi Utama">
          @if($staffUser->isAdmin())
            <a href="{{ route('admin.operators') }}" class="nav-link {{ request()->routeIs('admin.operators*') ? 'is-active' : '' }}">
              <svg class="icon"><use href="#i-users"></use></svg>
              <span class="nav-label-full">Akun Operator</span>
              <span class="nav-label-short">Operator</span>
            </a>
            <a href="{{ route('admin.audit-logs') }}" class="nav-link {{ request()->routeIs('admin.audit-logs*') ? 'is-active' : '' }}">
              <svg class="icon"><use href="#i-activity"></use></svg>
              <span class="nav-label-full">Log Audit</span>
              <span class="nav-label-short">Audit</span>
            </a>
          @elseif($staffUser->isOperator())
            <a href="{{ route('operator.elections') }}" class="nav-link {{ request()->routeIs('operator.elections') ? 'is-active' : '' }}">
              <svg class="icon"><use href="#i-dashboard"></use></svg>
              <span class="nav-label-full">Daftar Pemilihan</span>
              <span class="nav-label-short">Pemilihan</span>
            </a>
            @if(isset($activeElection) && $activeElection instanceof \App\Models\Election)
              <a href="{{ route('operator.elections.detail', $activeElection) }}" class="nav-link {{ request()->routeIs('operator.elections.detail') ? 'is-active' : '' }}">
                <svg class="icon"><use href="#i-calendar"></use></svg>
                <span class="nav-label-full">Konfigurasi &amp; Paslon</span>
                <span class="nav-label-short">Detail</span>
              </a>
              <a href="{{ route('operator.elections.monitor', $activeElection) }}" class="nav-link {{ request()->routeIs('operator.elections.monitor') ? 'is-active' : '' }}">
                <svg class="icon"><use href="#i-users"></use></svg>
                <span class="nav-label-full">Pantau Pemilih</span>
                <span class="nav-label-short">Pantau</span>
              </a>
              <a href="{{ route('operator.elections.recap', $activeElection) }}" class="nav-link {{ request()->routeIs('operator.elections.recap') ? 'is-active' : '' }}">
                <svg class="icon"><use href="#i-chart"></use></svg>
                <span class="nav-label-full">Rekapitulasi</span>
                <span class="nav-label-short">Rekap</span>
              </a>
            @endif
          @endif
        </nav>
      @elseif($isPublicRoute && isset($publicElection) && $publicElection instanceof \App\Models\Election)
        <nav class="main-nav" aria-label="Navigasi Halaman Publik">
          <a href="{{ route('public.turnout', $publicElection) }}" class="nav-link {{ request()->routeIs('public.turnout') ? 'is-active' : '' }}">
            <svg class="icon"><use href="#i-users"></use></svg>
            <span class="nav-label-full">Jumlah Pemilih</span>
            <span class="nav-label-short">Partisipasi</span>
          </a>
          <a href="{{ route('public.results', $publicElection) }}" class="nav-link {{ request()->routeIs('public.results') ? 'is-active' : '' }}">
            <svg class="icon"><use href="#i-trophy"></use></svg>
            <span class="nav-label-full">Hasil Pemilihan</span>
            <span class="nav-label-short">Hasil</span>
          </a>
        </nav>
      @endif

      <div class="header-side">
        @if($isPublicRoute)
          <span class="public-chip">
            <svg class="icon"><use href="#i-shield"></use></svg>
            <span>Halaman Publik</span>
          </span>
          @if(!$staffUser)
            <a href="{{ route('login') }}" class="public-operator-link" title="Masuk Staf">
              <svg class="icon"><use href="#i-lock"></use></svg>
              <span>Portal Staf</span>
            </a>
          @endif
        @endif

        @if($staffUser)
          <div class="operator-chip" title="{{ $staffUser->full_name }} ({{ strtoupper($staffUser->role) }})">
            <span class="operator-avatar">{{ $staffUser->initials() }}</span>
            <span class="operator-name">{{ $staffUser->full_name }} · {{ ucfirst($staffUser->role) }}</span>
          </div>
          <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-sm btn-secondary" title="Keluar dari sesi">
              <svg class="icon"><use href="#i-log-out"></use></svg>
              <span class="nav-label-full">Keluar</span>
            </button>
          </form>
        @endif
      </div>
    </div>
  </header>

  <main>
    {{ $slot }}
  </main>

  <div id="toast-root" class="toast-root" aria-live="polite" aria-atomic="false"></div>

  @livewireScripts
  <script>
    window.showToast = function (message, type = 'info') {
      const root = document.getElementById('toast-root');
      if (!root || !message) return;
      const iconMap = {
        success: '#i-check-circle',
        error: '#i-alert',
        warning: '#i-alert',
        info: '#i-info'
      };
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      toast.innerHTML = `
        <svg class="icon"><use href="${iconMap[type] || '#i-info'}"></use></svg>
        <div>${message}</div>
      `;
      root.appendChild(toast);
      requestAnimationFrame(() => toast.classList.add('is-visible'));
      setTimeout(() => {
        toast.classList.remove('is-visible');
        toast.classList.add('is-leaving');
        setTimeout(() => toast.remove(), 240);
      }, 4200);
    };

    // Saat scroll di luar modal (.modal-backdrop), geser area halaman di belakangnya;
    // saat scroll di dalam .modal, biarkan hanya konten modal (.modal-body) yang bergeser.
    document.addEventListener('wheel', function (e) {
      const backdrop = e.target.closest('.modal-backdrop');
      const modal = e.target.closest('.modal');
      if (backdrop && !modal) {
        window.scrollBy({ top: e.deltaY, left: e.deltaX, behavior: 'auto' });
      }
    }, { passive: true });

    document.addEventListener('livewire:initialized', () => {
      Livewire.on('toast', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        if (data && data.message) {
          window.showToast(data.message, data.type || 'info');
        }
      });
    });

    @if(session('success'))
      document.addEventListener('DOMContentLoaded', () => window.showToast(@json(session('success')), 'success'));
    @endif
    @if(session('error'))
      document.addEventListener('DOMContentLoaded', () => window.showToast(@json(session('error')), 'error'));
    @endif
  </script>
</body>
</html>
