{{-- UI-015, FR-017, BR-011, KP-004, AC-019: Tahap 2 Memilih Paslon --}}
<div class="ballot-shell view-enter" wire:key="stage-memilih">
  @if($isResumedSession)
    <div class="resume-banner" role="status">
      <svg class="icon"><use href="#i-info"></use></svg>
      <div>
        <strong>Sesi memilih Anda dilanjutkan.</strong> Silakan pilih pasangan calon dan tekan konfirmasi sebelum batas waktu 10 menit berakhir ({{ $sessionExpiresLabel }}).
      </div>
    </div>
  @endif

  <div class="ballot-head">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-ballot"></use></svg>
        <span>TAHAP 2 DARI 4 · SURAT SUARA ELEKTRONIK</span>
      </div>
      <h1>Pilih Satu Pasangan Calon</h1>
      <p class="lede">
        Ketuk kartu pasangan calon pilihan Anda, lalu tekan tombol <strong>Lanjut ke Konfirmasi</strong> di bagian bawah layar.
      </p>
    </div>

    @if($sessionExpiresLabel)
      <div class="ballot-voter" title="Batas waktu sesi memilih 10 menit">
        <span class="avatar" style="background:var(--amber-soft);color:var(--amber);border-color:#f4dfbd;">
          <svg class="icon" style="width:15px;height:15px;"><use href="#i-clock"></use></svg>
        </span>
        <div>
          <strong>Sesi Aktif (10 Menit)</strong>
          <small>Batas: {{ $sessionExpiresLabel }}</small>
        </div>
      </div>
    @endif
  </div>

  <div class="candidate-grid" role="radiogroup" aria-label="Daftar Pasangan Calon">
    @foreach($candidates as $cand)
      @php
        $isSelected = $selectedCandidateId === $cand->id;
        $missions = $cand->missionItems();
      @endphp
      <button
        type="button"
        role="radio"
        :aria-checked="clientSelectedId === '{{ $cand->id }}' ? 'true' : 'false'"
        class="candidate-card {{ $isSelected ? 'is-selected' : '' }}"
        :class="{ 'is-selected': clientSelectedId === '{{ $cand->id }}' }"
        x-on:click="chooseCandidate('{{ $cand->id }}')"
        wire:key="ballot-cand-{{ $cand->id }}"
      >
        <div class="candidate-media">
          <img src="{{ $cand->photoUrl() }}" alt="Foto Paslon {{ $cand->formattedNumber() }} — {{ $cand->name }}" class="candidate-photo" />
          <span class="candidate-number">NO. {{ $cand->formattedNumber() }}</span>
          <span class="candidate-check" aria-hidden="true">
            <svg class="icon"><use href="#i-check"></use></svg>
          </span>
        </div>

        <div class="candidate-body">
          <span class="candidate-kicker">PASANGAN CALON {{ $cand->formattedNumber() }}</span>
          <h2 class="candidate-names">{{ $cand->name }}</h2>

          @if($cand->vision)
            <div class="candidate-vision">
              <span>VISI</span>
              <p>{{ $cand->vision }}</p>
            </div>
          @endif

          @if(!empty($missions))
            <ul class="mission-list">
              @foreach($missions as $m)
                <li>{{ $m }}</li>
              @endforeach
            </ul>
          @endif

          <div class="candidate-select-hint">
            <svg class="icon"><use href="#i-check-circle"></use></svg>
            <span>Dipilih untuk dikonfirmasi</span>
          </div>
        </div>
      </button>
    @endforeach
  </div>

  <div class="ballot-bar">
    <div class="ballot-bar-info">
      <span>PILIHAN SEMENTARA (BELUM TERSIMPAN)</span>
      <strong x-text="selectedCand ? ('Paslon ' + selectedCand.number + ' — ' + selectedCand.name) : 'Belum ada pasangan calon yang dipilih'">
        @if($selectedCandidate)
          Paslon {{ $selectedCandidate->formattedNumber() }} — {{ $selectedCandidate->name }}
        @else
          Belum ada pasangan calon yang dipilih
        @endif
      </strong>
    </div>

    <button
      id="btn-to-confirm"
      type="button"
      class="btn btn-primary btn-lg"
      x-on:click="goToConfirm()"
      x-bind:disabled="!selectedCand"
    >
      <span class="btn-label-idle">
        <span>Lanjut ke Konfirmasi</span>
        <svg class="icon"><use href="#i-arrow-right"></use></svg>
      </span>
    </button>
  </div>

  <p class="session-note">
    <svg class="icon"><use href="#i-lock"></use></svg>
    <span>Pilihan Anda belum disimpan ke basis data sampai Anda menekan tombol <strong>Konfirmasi pilihan</strong> pada langkah berikutnya.</span>
  </p>
</div>
