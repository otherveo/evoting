@php
  $appName = config('app.name') ?: 'DigiVoting';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <meta name="description" content="Bilik Suara Digital — {{ $appName }}" />
  <meta name="theme-color" content="#132a63" />
  <link rel="icon" type="image/png" href="{{ asset('SMPN7-e-voting_logo.png') }}" />
  <title>{{ isset($title) ? $title . ' — ' . $appName : 'Bilik Suara — ' . $appName }}</title>
  <link rel="stylesheet" href="{{ asset('css/suarasekolah.css') }}?v=9" />
  @livewireStyles
</head>
<body>
  @include('partials.svg-sprite')

  <div class="ambient" aria-hidden="true">
    <span class="ambient-orb ambient-orb-1"></span>
    <span class="ambient-orb ambient-orb-2"></span>
    <span class="ambient-grid"></span>
  </div>

  {{-- UI.md U5: Header bilik pemilih bersih tanpa tautan keluar yang mengganggu alur linear --}}
  <header class="vote-header">
    <div class="container vote-header-inner">
      <div class="brand" aria-label="{{ $appName }}">
        <img src="{{ asset('SMPN7-e-voting_logo.png') }}" alt="{{ $appName }}" class="brand-logo" />
        <span class="brand-text">
          <strong>{{ $appName }}</strong>
          <small>Bilik Suara Digital</small>
        </span>
      </div>

      <div class="vote-header-side">
        <span class="public-chip">
          <svg class="icon"><use href="#i-lock"></use></svg>
          <span>Anonim &amp; Rahasia</span>
        </span>
      </div>
    </div>
  </header>

  <main class="vote-main">
    {{ $slot }}
  </main>

  <div id="toast-root" class="toast-root" aria-live="polite" aria-atomic="false"></div>

  @livewireScripts
  <script>
    window.normalizeShortToast = function (message, type = 'info') {
      const text = String(message || '').trim();
      if (!text) return type === 'error' ? 'Gagal' : 'Berhasil';
      const words = text.split(/\s+/).length;
      if (words <= 2) return text;
      const lower = text.toLowerCase();
      if (type === 'error' || type === 'warning' || lower.includes('gagal')) return 'Gagal';
      if (lower.includes('simpan')) return 'Tersimpan';
      if (lower.includes('setuju') || lower.includes('diizinkan')) return 'Diizinkan';
      if (lower.includes('tolak')) return 'Ditolak';
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
        <div><strong>${shortMsg}</strong></div>
      `;
      root.appendChild(toast);
      requestAnimationFrame(() => toast.classList.add('is-visible'));
      setTimeout(() => {
        toast.classList.remove('is-visible');
        toast.classList.add('is-leaving');
        setTimeout(() => toast.remove(), 240);
      }, 2800);
    };

    document.addEventListener('click', function (e) {
      const btn = e.target.closest('button[data-action-btn="true"], button[type="submit"]');
      if (!btn) return;
      if (btn.dataset.locked === 'true') {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }
    }, true);

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
    }, false);

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
      Livewire.on('step-changed', () => {
        window.scrollTo({ top: 0, behavior: 'auto' });
      });
      Livewire.hook('commit', ({ fail }) => {
        fail(() => {
          window.unlockActionButtons(document);
          window.showToast('Gagal', 'error');
        });
      });
    });
  </script>
</body>
</html>
