@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => '',
    'block' => false,
    'target' => null,
    'loadingText' => 'Memproses...',
    'icon' => null,
    'iconRight' => null,
    'disabled' => false,
])

@php
  $wireClick = $attributes->get('wire:click');
  $resolvedTarget = $target;
  if ($resolvedTarget === null && is_string($wireClick) && $wireClick !== '') {
      $resolvedTarget = trim($wireClick);
  }

  $classes = collect([
      'btn',
      'btn-' . $variant,
      $size !== '' ? 'btn-' . $size : null,
      $block ? 'btn-block' : null,
  ])->filter()->implode(' ');
@endphp

<button
  type="{{ $type }}"
  {{ $attributes->merge(['class' => $classes]) }}
  @if($disabled) disabled @endif
  wire:loading.attr="disabled"
  wire:loading.attr="data-loading"
  @if($resolvedTarget) wire:target="{{ $resolvedTarget }}" @endif
>
  <span
    class="btn-label-idle"
    wire:loading.remove
    @if($resolvedTarget) wire:target="{{ $resolvedTarget }}" @endif
  >
    @if($icon)
      <svg class="icon"><use href="{{ $icon }}"></use></svg>
    @endif
    <span>{{ $slot }}</span>
    @if($iconRight)
      <svg class="icon"><use href="{{ $iconRight }}"></use></svg>
    @endif
  </span>

  <span
    class="btn-label-loading"
    wire:loading.inline-flex
    @if($resolvedTarget) wire:target="{{ $resolvedTarget }}" @endif
  >
    <span class="spinner" aria-hidden="true"></span>
    <span>{{ $loadingText }}</span>
  </span>
</button>
