{{-- UI-017, FR-015, AC-012, AC-017, AC-018, AC-034, AC-039: Layar Informasi Kendala Pemilih --}}
<div class="view-enter" wire:key="stage-kendala">
  <section class="state-page" aria-labelledby="trouble-heading">
    <div class="state-icon {{ in_array($errorCode, ['invalid_token', 'wrong_browser'], true) ? 'is-danger' : '' }}" aria-hidden="true">
      <svg class="icon">
        <use href="{{ $errorCode === 'session_expired' ? '#i-clock' : ($errorCode === 'wrong_phase' ? '#i-calendar' : '#i-alert') }}"></use>
      </svg>
    </div>

    <div class="eyebrow">
      <svg class="icon"><use href="#i-info"></use></svg>
      <span>INFORMASI KENDALA AKSES VOTING</span>
    </div>

    <h1 id="trouble-heading">{{ $errorTitle }}</h1>
    <p>{{ $errorDescription }}</p>

    <dl class="state-details">
      <div>
        <dt>Pemilihan</dt>
        <dd>{{ $electionName }}</dd>
      </div>
      <div>
        <dt>Langkah Berikutnya</dt>
        <dd>{{ $errorNextStep }}</dd>
      </div>
    </dl>

    <div class="privacy-callout" style="text-align: left;">
      <svg class="icon"><use href="#i-shield"></use></svg>
      <div>
        Tunjukkan layar ini kepada <strong>Operator</strong> di meja verifikasi. Operator dapat memeriksa status hak pilih Anda dan melakukan <strong>Reset Sesi</strong> atau <strong>Terbitkan Ulang QR</strong> sesuai prosedur.
      </div>
    </div>
  </section>
</div>
