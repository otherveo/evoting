document.addEventListener('livewire:init', () => {
  if (window.livewireFeedbackInstalled) return;
  window.livewireFeedbackInstalled = true;

  const pendingActions = new Set();
  const warnedMonitors = new WeakSet();

  Livewire.on('toast', (payload) => {
    const notice = Array.isArray(payload) ? payload[0] : payload;
    if (notice?.message) window.showToast?.(notice.message, notice.type || 'info');
  });

  Livewire.interceptRequest(({ request, onFinish, onSuccess, onError, onFailure }) => {
    const calls = (request.payload?.components || []).flatMap(component => component.calls);
    const isPoll = calls.length > 0 && calls.every(call => call.metadata?.type === 'poll');
    const monitor = isPoll ? document.querySelector('[data-voter-monitor]') : null;
    const page = window.location.href;
    const recoveryKey = monitor ? 'evoting:monitor-reload:' + window.location.pathname : null;
    const signal = request.options?.signal;
    const isCurrentPage = () => window.location.href === page && !signal?.aborted;

    if (!isPoll) {
      pendingActions.add(request);
      const finish = () => pendingActions.delete(request);
      signal?.addEventListener('abort', finish, { once: true });
      onFinish(() => {
        signal?.removeEventListener('abort', finish);
        finish();
      });
    }

    onSuccess(() => {
      if (!monitor?.isConnected || !isCurrentPage()) return;
      warnedMonitors.delete(monitor);
      try {
        window.sessionStorage.removeItem(recoveryKey);
      } catch {
        // Polling still works when session storage is unavailable.
      }
    });

    onFailure(() => {
      if (!isPoll && isCurrentPage()) {
        window.showToast?.('Koneksi terputus. Periksa apakah aksi sudah tersimpan.', 'warning');
      }
    });

    onError(({ response, preventDefault }) => {
      if (!isCurrentPage()) {
        preventDefault();
        return;
      }

      if (response.status === 419) return;
      preventDefault();

      if (isPoll) {
        if (response.status !== 404 || !monitor?.isConnected || document.visibilityState !== 'visible') return;
        if (pendingActions.size > 0 || document.body.classList.contains('is-navigating')
          || !monitor.hasAttribute('wire:poll.3s.visible')) return;

        try {
          if (window.sessionStorage.getItem(recoveryKey) !== '1') {
            window.sessionStorage.setItem(recoveryKey, '1');
            // Only refresh the monitor GET. Never replay an operator action.
            window.location.reload();
            return;
          }
        } catch {
          // Avoid a reload loop if we cannot persist the recovery guard.
        }

        if (!warnedMonitors.has(monitor)) {
          warnedMonitors.add(monitor);
          window.showToast?.('Pembaruan monitor belum tersedia. Sistem akan mencoba lagi.', 'warning');
        }
        return;
      }

      if (response.status === 429) {
        window.showToast?.('Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.', 'warning');
      } else if (response.ok) {
        window.showToast?.('Respons server belum dapat dipastikan. Periksa hasil aksi sebelum mengulang.', 'warning');
      } else {
        window.showToast?.('Gagal', 'error');
      }
    });
  });
}, { once: true });
