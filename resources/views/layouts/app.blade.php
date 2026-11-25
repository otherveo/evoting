@php
  $appName = config('app.name') ?: 'DigiVoting';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <meta name="description" content="{{ $appName }} — Sistem Pemungutan Suara Digital Sekolah Berbasis QR Token Sekali Pakai" />
  @if(request()->routeIs('live-recap.*'))
    <meta name="robots" content="noindex, nofollow, noarchive" />
  @endif
  <meta name="theme-color" content="#132a63" />
  <link rel="icon" type="image/png" href="{{ asset('SMPN7-e-voting_logo.png') }}" />
  <title>{{ isset($title) ? $title . ' — ' . $appName : $appName . ' — Sistem Pemungutan Suara Digital' }}</title>
  <link rel="stylesheet" href="{{ asset('css/suarasekolah.css') }}?v={{ filemtime(public_path('css/suarasekolah.css')) }}" />
  @stack('styles')
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
      <a href="{{ $staffUser ? ($staffUser->isAdmin() ? route('admin.operators') : route('operator.elections')) : route('home') }}" wire:navigate class="brand">
        <img src="{{ asset('SMPN7-e-voting_logo.png') }}" alt="{{ $appName }}" class="brand-logo" />
        <span class="brand-text">
          <strong>{{ $appName }}</strong>
          <small>Sistem Voting QR Token</small>
        </span>
      </a>

      {{-- FR-004, BR-021: Navigasi utama sesuai peran staf atau publik --}}
      @if($staffUser && !$staffUser->needsActivation() && (!$staffUser->isAdmin() || session('staff_2fa_verified', false)))
        <nav class="main-nav" aria-label="Navigasi Utama">
          @if($staffUser->isAdmin())
            <a href="{{ route('admin.operators') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.operators*') ? 'is-active' : '' }}">
              <svg class="icon"><use href="#i-users"></use></svg>
              <span class="nav-label-full">Akun Operator</span>
              <span class="nav-label-short">Operator</span>
            </a>
            <a href="{{ route('admin.audit-logs') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.audit-logs*') ? 'is-active' : '' }}">
              <svg class="icon"><use href="#i-activity"></use></svg>
              <span class="nav-label-full">Log Audit</span>
              <span class="nav-label-short">Audit</span>
            </a>
            <a href="{{ route('admin.device-usage') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.device-usage*') ? 'is-active' : '' }}">
              <svg class="icon"><use href="#i-monitor"></use></svg>
              <span class="nav-label-full">Penggunaan Perangkat</span>
              <span class="nav-label-short">Perangkat</span>
            </a>
          @elseif($staffUser->isOperator())
            <a href="{{ route('operator.elections') }}" wire:navigate class="nav-link {{ request()->routeIs('operator.elections') ? 'is-active' : '' }}">
              <svg class="icon"><use href="#i-dashboard"></use></svg>
              <span class="nav-label-full">Daftar Pemilihan</span>
              <span class="nav-label-short">Pemilihan</span>
            </a>
            @if(isset($activeElection) && $activeElection instanceof \App\Models\Election)
              <a href="{{ route('operator.elections.detail', $activeElection) }}" wire:navigate class="nav-link {{ request()->routeIs('operator.elections.detail') ? 'is-active' : '' }}">
                <svg class="icon"><use href="#i-calendar"></use></svg>
                <span class="nav-label-full">Detail Pemilihan</span>
                <span class="nav-label-short">Detail</span>
              </a>
              <a href="{{ route('operator.elections.monitor', $activeElection) }}" wire:navigate class="nav-link {{ request()->routeIs('operator.elections.monitor') ? 'is-active' : '' }}">
                <svg class="icon"><use href="#i-users"></use></svg>
                <span class="nav-label-full">Pantau Pemilih</span>
                <span class="nav-label-short">Pantau</span>
              </a>
              <a href="{{ route('operator.elections.recap', $activeElection) }}" wire:navigate class="nav-link {{ request()->routeIs('operator.elections.recap') ? 'is-active' : '' }}">
                <svg class="icon"><use href="#i-chart"></use></svg>
                <span class="nav-label-full">Rekapitulasi</span>
                <span class="nav-label-short">Rekap</span>
              </a>
            @endif
          @endif
        </nav>
      @elseif($isPublicRoute && isset($publicElection) && $publicElection instanceof \App\Models\Election)
        <nav class="main-nav" aria-label="Navigasi Halaman Publik">
          <a href="{{ route('public.turnout', $publicElection) }}" wire:navigate class="nav-link {{ request()->routeIs('public.turnout') ? 'is-active' : '' }}">
            <svg class="icon"><use href="#i-users"></use></svg>
            <span class="nav-label-full">Jumlah Pemilih</span>
            <span class="nav-label-short">Partisipasi</span>
          </a>
          <a href="{{ route('public.results', $publicElection) }}" wire:navigate class="nav-link {{ request()->routeIs('public.results') ? 'is-active' : '' }}">
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
    {{-- Skeleton Loading saat berpindah halaman (wire:navigate) --}}
    <div id="page-skeleton" class="container page page-skeleton-overlay" aria-hidden="true">
      <div class="skeleton-heading">
        <div style="flex: 1;">
          <div class="skeleton-block" style="width: 180px; height: 14px; border-radius: 99px;"></div>
          <div class="skeleton-block" style="width: min(420px, 85%); height: 34px; margin-top: 10px; border-radius: 10px;"></div>
          <div class="skeleton-block" style="width: min(560px, 95%); height: 16px; margin-top: 10px; border-radius: 8px;"></div>
        </div>
        <div class="skeleton-block" style="width: 190px; height: 42px; border-radius: 12px;"></div>
      </div>
      <div class="stat-grid" style="margin-bottom: 18px;">
        <div class="stat-card"><div class="skeleton-block" style="height: 86px; border-radius: 12px;"></div></div>
        <div class="stat-card"><div class="skeleton-block" style="height: 86px; border-radius: 12px;"></div></div>
        <div class="stat-card"><div class="skeleton-block" style="height: 86px; border-radius: 12px;"></div></div>
        <div class="stat-card"><div class="skeleton-block" style="height: 86px; border-radius: 12px;"></div></div>
      </div>
      <div class="panel" style="padding: 22px;">
        <div class="skeleton-block" style="width: 240px; height: 22px; border-radius: 8px; margin-bottom: 16px;"></div>
        <div class="skeleton-block" style="height: 54px; border-radius: 12px; margin-bottom: 10px;"></div>
        <div class="skeleton-block" style="height: 54px; border-radius: 12px; margin-bottom: 10px;"></div>
        <div class="skeleton-block" style="height: 54px; border-radius: 12px;"></div>
      </div>
    </div>

    <div class="page-live-content">
      {{ $slot }}
    </div>
  </main>

  <div id="toast-root" class="toast-root" aria-live="polite" aria-atomic="false"></div>

  @livewireScripts
  <script>
    document.addEventListener('livewire:navigate', () => {
      document.body.classList.add('is-navigating');
    });
    document.addEventListener('livewire:navigated', () => {
      document.body.classList.remove('is-navigating');
    });
    window.normalizeShortToast = function (message, type = 'info') {
      const text = String(message || '').trim();
      if (!text) return type === 'error' ? 'Gagal' : 'Berhasil';
      const words = text.split(/\s+/);
      if (words.length <= 2) return text;
      const lower = text.toLowerCase();
      if (type === 'error' || type === 'warning' || lower.includes('gagal')) return 'Gagal';
      if (lower.includes('salin')) return 'Disalin';
      if (lower.includes('hapus')) return 'Terhapus';
      if (lower.includes('simpan')) return 'Tersimpan';
      if (lower.includes('perbarui')) return 'Diperbarui';
      if (lower.includes('setuju')) return 'Disetujui';
      if (lower.includes('tolak')) return 'Ditolak';
      if (lower.includes('cabut')) return 'Dicabut';
      if (lower.includes('reset')) return 'Direset';
      return 'Berhasil';
    };

    window.unlockActionButtons = function (scope = document) {
      scope.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
        delete form.dataset.submitting;
      });
      scope.querySelectorAll('button[data-locked="true"], button[data-loading="true"]').forEach((btn) => {
        delete btn.dataset.locked;
        delete btn.dataset.loading;
        btn.disabled = false;
      });
    };

    window.showToast = function (message, type = 'info') {
      const root = document.getElementById('toast-root');
      if (!root || !message) return;
      const shortMsg = window.normalizeShortToast(message, type);
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
        <div><strong></strong></div>
      `;
      toast.querySelector('strong').textContent = shortMsg;
      root.appendChild(toast);
      requestAnimationFrame(() => toast.classList.add('is-visible'));
      setTimeout(() => {
        toast.classList.remove('is-visible');
        toast.classList.add('is-leaving');
        setTimeout(() => toast.remove(), 240);
      }, 2800);
    };

    // 1. Capture phase: blokir klik ganda jika tombol atau form sudah terkunci
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('button[data-action-btn="true"], button[type="submit"]');
      if (!btn) return;
      const form = btn.closest('form');
      if (btn.dataset.locked === 'true' || (btn.type === 'submit' && form && form.dataset.submitting === 'true')) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }
    }, true);

    // 2. Bubble phase untuk tombol non-submit (wire:click): kunci tepat setelah event diterima Livewire
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('button[data-action-btn="true"]');
      if (!btn || btn.disabled || btn.type === 'submit') return;
      btn.dataset.locked = 'true';
      btn.dataset.loading = 'true';
      setTimeout(() => {
        if (btn.dataset.locked === 'true') {
          btn.disabled = true;
        }
      }, 0);
      if (btn.dataset.transientOpener === 'true') {
        setTimeout(() => {
          delete btn.dataset.locked;
          delete btn.dataset.loading;
          btn.disabled = false;
        }, 700);
      }
    }, false);

    // 3. Submit phase untuk form (wire:submit / POST): kunci tombol submit tepat saat form disubmit
    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (!form || form.tagName !== 'FORM') return;
      if (form.dataset.submitting === 'true') {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }
      form.dataset.submitting = 'true';
      const submitBtns = form.querySelectorAll('button[type="submit"], button[data-action-btn="true"][type="submit"]');
      submitBtns.forEach((btn) => {
        btn.dataset.locked = 'true';
        btn.dataset.loading = 'true';
        setTimeout(() => {
          if (btn.dataset.locked === 'true') {
            btn.disabled = true;
          }
        }, 0);
      });
    }, false);

    // Jika pengguna mengubah input di dalam form, buka kembali kunci tombol simpan di form tersebut
    document.addEventListener('input', function (e) {
      const form = e.target.closest('form');
      if (form) {
        window.unlockActionButtons(form);
      }
    }, true);

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
          const type = data.type || 'info';
          window.showToast(data.message, type);
          if (type === 'error' || type === 'warning') {
            window.unlockActionButtons(document);
          }
        }
      });

      Livewire.hook('commit', ({ succeed, fail }) => {
        succeed(() => {
          requestAnimationFrame(() => {
            if (document.querySelector('.field-error, .alert-danger')) {
              window.unlockActionButtons(document);
            }
          });
        });
        fail(() => {
          window.unlockActionButtons(document);
          window.showToast('Gagal', 'error');
        });
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
