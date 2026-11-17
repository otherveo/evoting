{{-- UI-014 s.d. UI-017, FR-015 s.d. FR-018, BR-028: Alur Bilik Suara 4 Tahap Linear Tanpa Identitas --}}
<div>
  @if($stage !== 'kendala')
    {{-- UI.md §1.1: Indikator 4 Langkah Linear (Verifikasi -> Memilih -> Konfirmasi -> Selesai) --}}
    <ol class="vote-steps" aria-label="Tahapan Memilih">
      <li class="{{ $stepIndex === 1 ? 'is-active' : ($stepIndex > 1 ? 'is-done' : '') }}">
        <span class="step-dot">
          @if($stepIndex > 1)
            <svg class="icon"><use href="#i-check"></use></svg>
          @else
            1
          @endif
        </span>
        <span class="step-label">Verifikasi</span>
      </li>
      <li class="{{ $stepIndex === 2 ? 'is-active' : ($stepIndex > 2 ? 'is-done' : '') }}">
        <span class="step-dot">
          @if($stepIndex > 2)
            <svg class="icon"><use href="#i-check"></use></svg>
          @else
            2
          @endif
        </span>
        <span class="step-label">Memilih</span>
      </li>
      <li class="{{ $stepIndex === 3 ? 'is-active' : ($stepIndex > 3 ? 'is-done' : '') }}">
        <span class="step-dot">
          @if($stepIndex > 3)
            <svg class="icon"><use href="#i-check"></use></svg>
          @else
            3
          @endif
        </span>
        <span class="step-label">Konfirmasi</span>
      </li>
      <li class="{{ $stepIndex === 4 ? 'is-active' : '' }}">
        <span class="step-dot">4</span>
        <span class="step-label">Selesai</span>
      </li>
    </ol>
  @endif

  @if($stage === 'verifikasi')
    @include('livewire.voter.partials.stage-verifikasi')
  @elseif($stage === 'memilih')
    @include('livewire.voter.partials.stage-memilih')
  @elseif($stage === 'konfirmasi' && $selectedCandidate)
    @include('livewire.voter.partials.stage-konfirmasi')
  @elseif($stage === 'selesai')
    @include('livewire.voter.partials.stage-selesai')
  @elseif($stage === 'kendala')
    @include('livewire.voter.partials.stage-kendala')
  @endif
</div>
