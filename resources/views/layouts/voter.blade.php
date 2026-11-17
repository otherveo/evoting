<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <meta name="description" content="Bilik Suara Elektronik — SuaraSekolah" />
  <meta name="theme-color" content="#132a63" />
  <title>{{ isset($title) ? $title . ' — SuaraSekolah' : 'Bilik Suara — SuaraSekolah' }}</title>
  <link rel="stylesheet" href="{{ asset('css/suarasekolah.css') }}" />
  @livewireStyles
</head>
<body>
  @include('partials.svg-sprite')

  <div class="ambient" aria-hidden="true">
    <span class="ambient-orb ambient-orb-1"></span>
    <span class="ambient-orb ambient-orb-2"></span>
    <span class="ambient-grid"></span>
  </div>

  <header class="vote-header">
    <div class="container vote-header-inner">
      <div class="brand" aria-label="SuaraSekolah">
        <span class="brand-mark" aria-hidden="true">
          <svg class="icon"><use href="#i-ballot"></use></svg>
        </span>
        <span class="brand-text">
          <strong>SuaraSekolah</strong>
          <small>Bilik Suara Elektronik</small>
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

    document.addEventListener('livewire:initialized', () => {
      Livewire.on('toast', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        if (data && data.message) {
          window.showToast(data.message, data.type || 'info');
        }
      });
      Livewire.on('step-changed', () => {
        window.scrollTo({ top: 0, behavior: 'auto' });
      });
    });
  </script>
</body>
</html>
