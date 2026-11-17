@props([
    'icon' => '#i-users',
    'cap' => '',
    'value' => '0',
    'label' => '',
    'hint' => '',
    'variant' => '',
    'progress' => null,
])

<div class="stat-card {{ $variant !== '' ? 'stat-' . $variant : '' }}">
  <div class="stat-top">
    <span class="stat-icon"><svg class="icon"><use href="{{ $icon }}"></use></svg></span>
    @if($cap !== '')
      <span class="stat-cap">{{ $cap }}</span>
    @endif
  </div>
  <div class="stat-value">{{ $value }}</div>
  <div class="stat-label">{{ $label }}</div>
  @if($hint !== '')
    <div class="stat-hint">{{ $hint }}</div>
  @endif
  @if($progress !== null)
    <div class="progress" aria-label="{{ $label }}">
      <span style="width: {{ $progress }}%;"></span>
    </div>
  @endif
</div>
