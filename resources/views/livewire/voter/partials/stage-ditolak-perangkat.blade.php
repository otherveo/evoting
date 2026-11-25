<div class="view-enter" wire:key="stage-ditolak-perangkat"
  @if($errorCode === 'device_blocked') x-data="{ deviceLimitModalOpen: true }" @endif>
  <section class="state-page" aria-labelledby="device-limit-heading">
    <div class="state-icon is-danger" aria-hidden="true">
      <svg class="icon"><use href="#i-alert"></use></svg>
    </div>

    <div class="eyebrow">
      <svg class="icon"><use href="#i-info"></use></svg>
      <span>PEMBERITAHUAN BATAS PERANGKAT</span>
    </div>

    <h1 id="device-limit-heading">{{ $errorTitle }}</h1>
    <p>{{ $errorDescription }}</p>

    @if($errorCode === 'device_blocked')
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
        Tautan QR ini tidak dapat dipakai lagi pada perangkat ini. Silakan buka tautan QR Anda dari <strong>perangkat lain</strong>, atau hubungi Operator di meja verifikasi apabila Anda merasa ini keliru.
      </div>
    </div>
    @endif
  </section>

  @if($errorCode === 'device_blocked')
  <x-modal
    title="Perangkat Tidak Dapat Digunakan"
    icon="#i-alert"
    iconVariant="danger"
    maxWidth="500px"
    alpineShow="deviceLimitModalOpen"
    alpineClose="deviceLimitModalOpen = false"
  >
    <div class="modal-body">
      <div class="alert alert-danger" role="alert">
        <svg class="icon"><use href="#i-alert"></use></svg>
        <div>
          <strong>{{ $errorTitle }}</strong>
          <p>{{ $errorDescription }}</p>
        </div>
      </div>
      <p class="field-hint" style="margin-top: 12px; text-align: center;">
        Tutup pemberitahuan ini dengan tombol X di atas.
      </p>
    </div>
  </x-modal>
  @endif
</div>
