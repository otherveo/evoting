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

  <script src="{{ asset('js/livewire-feedback.js') }}?v={{ filemtime(public_path('js/livewire-feedback.js')) }}"></script>
  @livewireScripts
  <script>
    window.normalizeShortToast = function (message, type = 'info') {
      const text = String(message || '').trim();
      if (!text) return type === 'error' ? 'Gagal' : 'Berhasil';
      const lower = text.toLowerCase();
      if (lower.includes('membaca seluruh visi') || lower.includes('pasangan calon terlebih dahulu')) {
        return text;
      }
      const words = text.split(/\s+/).length;
      if (words <= 2) return text;
      if (type === 'error' || type === 'warning') return text;
      if (lower.includes('simpan')) return 'Tersimpan';
      if (lower.includes('setuju') || lower.includes('diizinkan')) return 'Diizinkan';
      if (lower.includes('tolak')) return 'Ditolak';
      return 'Berhasil';
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

    document.addEventListener('livewire:initialized', () => {
      Livewire.on('step-changed', () => {
        window.scrollTo({ top: 0, behavior: 'auto' });
      });
    });
  </script>
</body>
</html>
