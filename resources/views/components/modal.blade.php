@props([
    'title',
    'subtitle' => null,
    'icon' => '#i-info',
    'iconVariant' => '',
    'maxWidth' => '560px',
    'closeAction' => null,
    'alpineClose' => null,
])

@php
  $iconStyle = match ($iconVariant) {
      'success' => 'color:var(--green);background:var(--green-soft);',
      'danger' => 'color:var(--red);background:var(--red-soft);',
      'warning' => 'color:var(--amber);background:var(--amber-soft);',
      default => '',
  };
@endphp

@teleport('body')
  <div
    class="modal-backdrop is-visible"
    role="dialog"
    aria-modal="true"
    aria-label="{{ $title }}"
    x-data
    @if($alpineClose)
      x-on:click.self="{{ $alpineClose }}"
      x-on:keydown.escape.window="{{ $alpineClose }}"
    @elseif($closeAction)
      x-on:click.self="$wire.{{ $closeAction }}"
      x-on:keydown.escape.window="$wire.{{ $closeAction }}"
    @endif
  >
    <div class="modal" style="--modal-width: {{ $maxWidth }};">
      <div class="modal-header">
        <div class="modal-heading">
          <span class="modal-icon" @if($iconStyle !== '') style="{{ $iconStyle }}" @endif>
            <svg class="icon"><use href="{{ $icon }}"></use></svg>
          </span>
          <div>
            <h2>{{ $title }}</h2>
            @if($subtitle)
              <p>{!! $subtitle !!}</p>
            @endif
          </div>
        </div>

        @if($alpineClose)
          <button
            type="button"
            class="icon-btn"
            x-on:click="{{ $alpineClose }}"
            aria-label="Tutup dialog"
          >
            <svg class="icon"><use href="#i-x"></use></svg>
          </button>
        @elseif($closeAction)
          <button
            type="button"
            class="icon-btn"
            wire:click="{{ $closeAction }}"
            aria-label="Tutup dialog"
          >
            <svg class="icon"><use href="#i-x"></use></svg>
          </button>
        @endif
      </div>

      {{ $slot }}

      @isset($footer)
        <div class="modal-footer">
          {{ $footer }}
        </div>
      @endisset
    </div>
  </div>
@endteleport
