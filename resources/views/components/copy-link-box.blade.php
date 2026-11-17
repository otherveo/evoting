@props([
    'label',
    'url',
    'copyToast' => 'Tautan berhasil disalin ke papan klip.',
    'showOpenLink' => true,
])

<div {{ $attributes->merge(['class' => 'token-box']) }}>
  <div class="token-box-copy">
    <span>{{ $label }}</span>
    <code>{{ $url }}</code>
  </div>

  <div class="token-box-actions">
    <button
      type="button"
      class="icon-btn"
      title="Salin tautan"
      aria-label="Salin tautan"
      onclick="navigator.clipboard.writeText(@js($url)); window.showToast(@js($copyToast), 'success');"
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
