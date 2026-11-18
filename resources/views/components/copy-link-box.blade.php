@props([
    'label' => null,
    'url',
    'copyToast' => 'Disalin',
    'showOpenLink' => true,
])

<div {{ $attributes->merge(['class' => 'copy-link-group']) }}>
  @if($label)
    <div class="copy-link-label">{{ $label }}</div>
  @endif

  <div class="token-box">
    <div class="token-box-copy">
      <code>{{ $url }}</code>
    </div>

    <div class="token-box-actions">
      <button
        type="button"
        class="icon-btn"
        title="Salin tautan"
        aria-label="Salin tautan"
        onclick="navigator.clipboard.writeText(@js($url)); window.showToast('Disalin', 'success');"
      >
        <svg class="icon"><use href="#i-copy"></use></svg>
      </button>

      @if($showOpenLink)
        <a
          href="{{ $url }}"
          target="_blank"
          rel="noopener"
          class="icon-btn"
          title="Buka di tab baru"
          aria-label="Buka di tab baru"
        >
          <svg class="icon"><use href="#i-external"></use></svg>
        </a>
      @endif
    </div>
  </div>
</div>
