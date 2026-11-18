{{-- UI-014 s.d. UI-017, FR-015 s.d. FR-018, BR-028: Alur Bilik Suara 4 Tahap Linear Tanpa Identitas --}}
@php
  $candidatesClientMap = $candidates->mapWithKeys(fn ($c) => [
      $c->id => [
          'id' => $c->id,
          'number' => $c->formattedNumber(),
          'name' => $c->name,
          'vision' => $c->vision,
          'photoUrl' => $c->photoUrl(),
      ],
  ])->all();
@endphp
<div
  x-data="{
    clientStage: $wire.entangle('stage'),
    clientSelectedId: $wire.entangle('selectedCandidateId'),
    submittingVote: false,
    candidatesMap: @js($candidatesClientMap),
    get activeStepIndex() {
      if (this.clientStage === 'verifikasi') return 1;
      if (this.clientStage === 'memilih') return 2;
      if (this.clientStage === 'konfirmasi') return 3;
      if (this.clientStage === 'selesai') return 4;
      return 0;
    },
    get selectedCand() {
      return this.clientSelectedId ? (this.candidatesMap[this.clientSelectedId] || null) : null;
    },
    chooseCandidate(id) {
      this.clientSelectedId = id;
    },
    goToConfirm() {
      if (!this.clientSelectedId) {
        window.showToast('Pilih satu pasangan calon terlebih dahulu.', 'warning');
        return;
      }
      this.clientStage = 'konfirmasi';
      window.scrollTo({ top: 0, behavior: 'auto' });
    },
    goBackToBallot() {
      if (this.submittingVote) return;
      this.clientStage = 'memilih';
      window.scrollTo({ top: 0, behavior: 'auto' });
    },
    submitFinalVote() {
      if (this.submittingVote || !this.clientSelectedId) return;
      this.submittingVote = true;
      $wire.confirmVote(this.clientSelectedId).finally(() => {
        this.submittingVote = false;
      });
    }
  }"
>
  @if($stage !== 'kendala')
    {{-- UI.md §1.1: Indikator 4 Langkah Linear (Verifikasi -> Memilih -> Konfirmasi -> Selesai) --}}
    <ol class="vote-steps" aria-label="Tahapan Memilih">
      <li
        class="{{ $stepIndex === 1 ? 'is-active' : ($stepIndex > 1 ? 'is-done' : '') }}"
        :class="{ 'is-active': activeStepIndex === 1, 'is-done': activeStepIndex > 1 }"
      >
        <span class="step-dot">
          <template x-if="activeStepIndex > 1">
            <svg class="icon"><use href="#i-check"></use></svg>
          </template>
          <template x-if="activeStepIndex <= 1">
            <span>1</span>
          </template>
        </span>
        <span class="step-label">Verifikasi</span>
      </li>
      <li
        class="{{ $stepIndex === 2 ? 'is-active' : ($stepIndex > 2 ? 'is-done' : '') }}"
        :class="{ 'is-active': activeStepIndex === 2, 'is-done': activeStepIndex > 2 }"
      >
        <span class="step-dot">
          <template x-if="activeStepIndex > 2">
            <svg class="icon"><use href="#i-check"></use></svg>
          </template>
          <template x-if="activeStepIndex <= 2">
            <span>2</span>
          </template>
        </span>
        <span class="step-label">Memilih</span>
      </li>
      <li
        class="{{ $stepIndex === 3 ? 'is-active' : ($stepIndex > 3 ? 'is-done' : '') }}"
        :class="{ 'is-active': activeStepIndex === 3, 'is-done': activeStepIndex > 3 }"
      >
        <span class="step-dot">
          <template x-if="activeStepIndex > 3">
            <svg class="icon"><use href="#i-check"></use></svg>
          </template>
          <template x-if="activeStepIndex <= 3">
            <span>3</span>
          </template>
        </span>
        <span class="step-label">Konfirmasi</span>
      </li>
      <li
        class="{{ $stepIndex === 4 ? 'is-active' : '' }}"
        :class="{ 'is-active': activeStepIndex === 4 }"
      >
        <span class="step-dot">4</span>
        <span class="step-label">Selesai</span>
      </li>
    </ol>
  @endif

  @if($stage === 'verifikasi')
    @include('livewire.voter.partials.stage-verifikasi')
  @elseif(in_array($stage, ['memilih', 'konfirmasi'], true))
    <div x-show="clientStage === 'memilih'">
      @include('livewire.voter.partials.stage-memilih')
    </div>
    <div x-show="clientStage === 'konfirmasi'" x-cloak>
      @include('livewire.voter.partials.stage-konfirmasi')
    </div>
  @elseif($stage === 'selesai')
    @include('livewire.voter.partials.stage-selesai')
  @elseif($stage === 'kendala')
    @include('livewire.voter.partials.stage-kendala')
  @endif

  {{-- Perubahan 3.2: Popup Kuncian "Menunggu Persetujuan Operator" (Unclosable) Saat Perangkat Bersama Terdeteksi --}}
  @if($waitingForDeviceApproval)
    <div wire:poll.3s="pollDeviceApproval">
      <x-modal
        title="Menunggu Persetujuan Operator"
        subtitle="Perangkat ini terdeteksi sudah pernah digunakan oleh pemilih lain pada pemilihan ini."
        icon="#i-clock"
        iconVariant="warning"
        maxWidth="500px"
      >
        <div
          class="modal-body"
          x-data="{
            checking: false,
            btnState: $wire.entangle('approvalButtonState'),
            checkPermission() {
              if (this.checking) return;
              this.checking = true;
              $wire.checkDeviceApprovalManual().finally(() => {
                this.checking = false;
              });
            }
          }"
        >
          <div class="alert alert-warning" role="alert">
            <svg class="icon"><use href="#i-shield"></use></svg>
            <div>
              <strong>Layar bilik suara dikunci sementara</strong>
              <p>
                Silakan tunggu petugas Operator menyetujui penggunaan perangkat bersama ini dari layar pemantauan, atau tekan tombol <strong>Cek izin</strong> di bawah.
              </p>
            </div>
          </div>

          @if($sessionExpiresLabel)
            <p class="field-hint" style="margin-top: 12px; text-align: center;">
              Batas waktu tunggu sesi (10 menit): <strong>{{ $sessionExpiresLabel }}</strong>
            </p>
          @endif

          <div style="margin-top: 18px;">
            <button
              type="button"
              class="btn btn-lg btn-block approval-check-btn"
              :class="{
                'is-approved': !checking && btnState === 'approved',
                'is-rejected': !checking && btnState === 'rejected',
                'is-pending': !checking && (btnState === 'idle' || btnState === 'pending')
              }"
              x-on:click="checkPermission()"
              x-bind:disabled="checking"
              x-bind:data-loading="checking ? 'true' : null"
            >
              <span class="btn-label-idle" x-show="!checking">
                <svg class="icon"><use href="#i-refresh"></use></svg>
                <span x-text="btnState === 'approved' ? 'Diizinkan' : (btnState === 'pending' ? 'Belum diizinkan' : (btnState === 'rejected' ? 'Tidak diizinkan' : 'Cek izin'))">Cek izin</span>
              </span>
              <span class="btn-label-loading" x-show="checking" x-cloak style="display: inline-flex;">
                <span class="spinner" aria-hidden="true"></span>
                <span>Mengecek izin...</span>
              </span>
            </button>
          </div>
        </div>
      </x-modal>
    </div>
  @endif
</div>
