@props(['src', 'alt'])

<img src="{{ $src }}" alt="" aria-hidden="true" class="candidate-photo-backdrop" />
<img src="{{ $src }}" alt="{{ $alt }}" {{ $attributes->class(['candidate-photo']) }} />
