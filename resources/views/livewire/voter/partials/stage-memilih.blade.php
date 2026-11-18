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
        <span>TAHAP 2 DARI 4 · SURAT SUARA Digital</span>
      </div>
      <h1>Pilih Satu Pasangan Calon</h1>
      <p class="lede">
        Ketuk kartu pasangan calon pilihan Anda, baca visi &amp; misi <strong>seluruh paslon</strong> lewat tombol <strong>Lihat Visi &amp; Misi</strong> di tiap kartu, lalu tekan tombol <strong>Lanjut ke Konfirmasi</strong> di bagian bawah layar.
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
      @endphp
      {{-- Update v5 — Bagian A: kartu BUKAN lagi <button> (tombol di dalamnya harus valid);
           <div role="radio"> dengan keyboard/ARIA setara (tabindex, Enter/Space, aria-checked). --}}
      <div
        role="radio"
        tabindex="0"
        :aria-checked="clientSelectedId === '{{ $cand->id }}' ? 'true' : 'false'"
        class="candidate-card {{ $isSelected ? 'is-selected' : '' }}"
        :class="{ 'is-selected': clientSelectedId === '{{ $cand->id }}' }"
        x-on:click="chooseCandidate('{{ $cand->id }}')"
        x-on:keydown.enter.prevent="chooseCandidate('{{ $cand->id }}')"
        x-on:keydown.space.prevent="chooseCandidate('{{ $cand->id }}')"
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

          {{-- Update v5 — Bagian A: visi & misi TIDAK lagi tampil di kartu; lewat popup. --}}
          <div class="candidate-actions">
            <button
              type="button"
              class="btn btn-primary candidate-select-btn"
              x-on:click.stop="chooseCandidate('{{ $cand->id }}')"
            >
              <svg class="icon"><use href="#i-check"></use></svg>
              <span x-text="clientSelectedId === '{{ $cand->id }}' ? 'Dipilih' : 'Pilih Paslon Ini'"></span>
            </button>
            <button
              type="button"
              class="btn btn-secondary candidate-vision-btn"
              :class="{ 'is-read': readIds.includes('{{ $cand->id }}') }"
              x-on:click.stop="openVision('{{ $cand->id }}')"
            >
              <svg class="icon"><use href="#i-eye"></use></svg>
              <span>Lihat Visi &amp; Misi</span>
              <svg class="icon vision-read-check" x-show="readIds.includes('{{ $cand->id }}')" x-cloak><use href="#i-check"></use></svg>
            </button>
          </div>

          <div class="candidate-select-hint">
            <svg class="icon"><use href="#i-check-circle"></use></svg>
            <span>Dipilih untuk dikonfirmasi</span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Update v5 — Bagian A: progres baca visi & misi (cerminan klien; gate ditegakkan di server). --}}
  <p class="read-progress" role="status">
    <svg class="icon"><use href="#i-eye"></use></svg>
    <span x-text="readProgressLabel"></span>
  </p>

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
      x-bind:disabled="!selectedCand || confirming"
    >
      <span class="btn-label-idle" x-show="!confirming">
        <span>Lanjut ke Konfirmasi</span>
        <svg class="icon"><use href="#i-arrow-right"></use></svg>
      </span>
      <span class="btn-label-loading" x-show="confirming" x-cloak style="display: inline-flex;">
        <span class="spinner" aria-hidden="true"></span>
        <span>Memeriksa...</span>
      </span>
    </button>
  </div>

  <p class="session-note">
    <svg class="icon"><use href="#i-lock"></use></svg>
    <span>Pilihan Anda belum disimpan ke basis data sampai Anda menekan tombol <strong>Konfirmasi pilihan</strong> pada langkah berikutnya.</span>
  </p>
</div>
