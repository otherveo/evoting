{{-- UI-015, FR-018, BR-009, BR-010, AC-020: Tahap 3 Konfirmasi Pilihan --}}
<div class="view-enter" wire:key="stage-konfirmasi">
  <section class="vote-card confirm-card" aria-labelledby="confirm-heading">
    <div class="confirm-top">
      <div class="confirm-icon" aria-hidden="true">
        <svg class="icon"><use href="#i-shield"></use></svg>
      </div>
      <div class="eyebrow" style="justify-content: center;">
        <svg class="icon"><use href="#i-check-circle"></use></svg>
        <span>TAHAP 3 DARI 4 · PERIKSA KEMBALI PILIHAN ANDA</span>
      </div>
      <h1 id="confirm-heading">Konfirmasi Pilihan Suara</h1>
      <p>
        Pastikan pasangan calon di bawah ini sudah sesuai dengan pilihan Anda sebelum menyimpan suara secara permanen.
      </p>
    </div>

    <div class="confirm-summary">
      <img
        src="{{ $selectedCandidate->photoUrl() }}"
        alt="Foto {{ $selectedCandidate->name }}"
        class="confirm-photo"
      />
      <div class="confirm-candidate">
        <span>PASANGAN CALON NOMOR URUT {{ $selectedCandidate->formattedNumber() }}</span>
        <h2>{{ $selectedCandidate->name }}</h2>
        @if($selectedCandidate->vision)
          <p>{{ $selectedCandidate->vision }}</p>
        @else
          <p>Pemilihan: {{ $electionName }}</p>
        @endif
      </div>
    </div>

    <div class="alert alert-warning" role="alert">
      <svg class="icon"><use href="#i-alert"></use></svg>
      <div>
        <strong>Tindakan ini tidak dapat dibatalkan</strong>
        <p>Setelah Anda menekan <strong>Konfirmasi pilihan</strong>, suara akan disimpan secara anonim dan status hak pilih berubah menjadi <b>Sudah memilih</b>.</p>
      </div>
    </div>

    <div class="confirm-actions">
      <x-action-button
        variant="secondary"
        size="lg"
        icon="#i-arrow-left"
        wire:click="backToBallot"
        loadingText="Kembali..."
      >
        Ubah pilihan
      </x-action-button>

      <x-action-button
        variant="primary"
        size="lg"
        icon="#i-check-circle"
        wire:click="confirmVote"
        loadingText="Menyimpan suara..."
      >
        Konfirmasi pilihan
      </x-action-button>
    </div>
  </section>
</div>
