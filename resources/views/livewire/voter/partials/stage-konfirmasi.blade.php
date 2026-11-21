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

    <div class="confirm-summary" x-show="reviewedCand">
      <img
        :src="reviewedCand ? reviewedCand.photoUrl : ''"
        :alt="reviewedCand ? ('Foto ' + reviewedCand.name) : ''"
        class="confirm-photo"
      />
      <div class="confirm-candidate">
        <span>PASANGAN CALON NOMOR URUT <span x-text="reviewedCand ? reviewedCand.number : ''"></span></span>
        <h2 x-text="reviewedCand ? reviewedCand.name : ''"></h2>
        <p x-text="(reviewedCand && reviewedCand.vision) ? reviewedCand.vision : @js('Pemilihan: ' . $electionName)"></p>
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
      <button
        type="button"
        class="btn btn-secondary btn-lg"
        x-on:click="goBackToBallot()"
        x-bind:disabled="submittingVote"
      >
        <span class="btn-label-idle">
          <svg class="icon"><use href="#i-arrow-left"></use></svg>
          <span>Ubah pilihan</span>
        </span>
      </button>

      <button
        type="button"
        class="btn btn-primary btn-lg"
        x-on:click="submitFinalVote()"
        x-bind:disabled="submittingVote || !reviewedCand"
        x-bind:data-loading="submittingVote ? 'true' : null"
      >
        <span class="btn-label-idle" x-show="!submittingVote">
          <svg class="icon"><use href="#i-check-circle"></use></svg>
          <span>Konfirmasi pilihan</span>
        </span>
        <span class="btn-label-loading" x-show="submittingVote" x-cloak style="display: inline-flex;">
          <span class="spinner" aria-hidden="true"></span>
          <span>Menyimpan suara...</span>
        </span>
      </button>
    </div>
  </section>
</div>
