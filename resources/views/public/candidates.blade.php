@component('layouts.app', ['title' => 'Daftar Paslon'])
  <div class="container page public-page">
    <div class="page-heading">
      <div>
        <h1>Daftar Paslon</h1>
        @isset($election)
          <p class="lede">{{ $election->name }}</p>
        @endisset
      </div>
      <a href="{{ isset($election) ? route('public.candidates.index', ['pilih' => 1]) : route('home') }}" wire:navigate class="btn btn-secondary">
        <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"></use></svg>
        <span>{{ isset($election) ? 'Daftar Pemilihan' : 'Beranda' }}</span>
      </a>
    </div>

    @isset($election)
      <div x-data="{ visionCandidate: null }">
        <ul class="public-candidate-grid">
          @foreach($candidates as $candidate)
            <li class="public-candidate-card">
              <h2>Paslon Nomor {{ $candidate['number'] }}</h2>
              <button type="button" class="public-candidate-trigger"
                aria-haspopup="dialog" aria-controls="public-candidate-vision"
                aria-label="Lihat paslon ini, nomor {{ $candidate['number'] }}"
                x-on:click="visionCandidate = @js($candidate); $refs.visionDialog.showModal()">
                <span class="public-candidate-thumbnail" aria-hidden="true">
                  <span>{{ $candidate['number'] }}</span>
                  @if($candidate['photo'])
                    <img src="{{ $candidate['photo'] }}" alt="" loading="lazy"
                      x-init="if ($el.complete && !$el.naturalWidth) $el.hidden = true"
                      x-on:error="$el.hidden = true" />
                  @endif
                </span>
                <span class="public-candidate-trigger-label">Lihat paslon ini</span>
                <svg class="icon" aria-hidden="true"><use href="#i-expand"></use></svg>
              </button>
            </li>
          @endforeach
        </ul>
        <x-candidate-vision-dialog id="public-candidate-vision" />
      </div>
    @else
      <section class="panel public-candidate-elections" aria-label="Pilih pemilihan">
        @forelse($elections as $option)
          <a href="{{ route('public.candidates', $option) }}" wire:navigate class="btn btn-secondary">
            {{ $option->name }}
          </a>
        @empty
          <p>Belum ada daftar paslon dari pemilihan yang terjadwal.</p>
        @endforelse
      </section>
    @endisset
  </div>
@endcomponent
