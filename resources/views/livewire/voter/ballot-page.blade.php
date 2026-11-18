{{-- UI-014 s.d. UI-017, FR-015 s.d. FR-018, BR-028: Alur Bilik Suara 4 Tahap Linear Tanpa Identitas --}}
{{-- Update v5 — Bagian C: style kartu/popup/gate di file mandiri (bukan suarasekolah.css) --}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('css/voter/ballot.css') . '?v=' . filemtime(public_path('css/voter/ballot.css')) }}" />
@endpush
@php
  $candidatesClientMap = $candidates->mapWithKeys(fn ($c) => [
      $c->id => [
          'id' => $c->id,
          'number' => $c->formattedNumber(),
          'name' => $c->name,
          'vision' => $c->vision,
          'missions' => $c->missionItems(),
          'photoUrl' => $c->photoUrl(),
      ],
  ])->all();
@endphp
<div
  x-on:gate-rejected.window="gateUnreadNames = $event.detail.unread || []; gateRejectionOpen = true"
  x-data="{
    clientStage: $wire.entangle('stage'),
    clientSelectedId: $wire.entangle('selectedCandidateId'),
    submittingVote: false,
    candidatesMap: @js($candidatesClientMap),
    visionModalId: null,
    readIds: [],
    gateRejectionOpen: false,
    gateUnreadNames: [],
    confirming: false,
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
    get visionCand() {
      return this.visionModalId ? (this.candidatesMap[this.visionModalId] || null) : null;
    },
    get unreadCandidates() {
      return Object.values(this.candidatesMap).filter(c => !this.readIds.includes(c.id));
    },
    get readCount() {
      return this.readIds.length;
    },
    get totalCandidates() {
      return Object.keys(this.candidatesMap).length;
    },
    get readProgressLabel() {
      if (this.totalCandidates === 0) return '';
      if (this.readCount >= this.totalCandidates) return 'Semua visi & misi sudah dibaca — Anda bisa lanjut ke konfirmasi.';
      return 'Visi & misi sudah dibaca: ' + this.readCount + ' dari ' + this.totalCandidates + ' paslon.';
    },
    chooseCandidate(id) {
      this.clientSelectedId = id;
    },
    openVision(id) {
      this.visionModalId = id;
      if (!this.readIds.includes(id)) {
        this.readIds.push(id);
      }
      $wire.markVisionRead(id);
    },
    goToConfirm() {
      if (!this.clientSelectedId) {
        window.showToast('Pilih satu pasangan calon terlebih dahulu.', 'warning');
        return;
      }
      if (this.confirming) return;
      const unread = this.unreadCandidates;
      if (unread.length > 0) {
        this.gateUnreadNames = unread.map(c => 'Paslon ' + c.number + ' — ' + c.name);
        this.gateRejectionOpen = true;
        return;
      }
      this.confirming = true;
      $wire.proceedToConfirm().finally(() => {
        this.confirming = false;
      });
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

  {{-- Update v5 — Bagian A: popup visi & misi satu paslon (klien; dibuka tombol "Lihat Visi & Misi") --}}
  <template x-if="visionModalId">
    <x-modal
      title="Visi &amp; Misi Paslon"
      icon="#i-eye"
      maxWidth="560px"
      alpineClose="visionModalId = null"
    >
      <div class="modal-body vision-modal-body">
        <template x-if="visionCand">
          <div>
            <h3 class="vision-modal-name" x-text="visionCand ? ('Paslon ' + visionCand.number + ' — ' + visionCand.name) : ''"></h3>
            <template x-if="visionCand && visionCand.vision">
              <div class="vision-modal-section">
                <span class="vision-modal-label">VISI</span>
                <p x-text="visionCand.vision"></p>
              </div>
            </template>
            <template x-if="visionCand && visionCand.missions && visionCand.missions.length">
              <div class="vision-modal-section">
                <span class="vision-modal-label">MISI</span>
                <ul class="mission-list vision-modal-missions">
                  <template x-for="m in visionCand.missions" :key="m">
                    <li x-text="m"></li>
                  </template>
                </ul>
              </div>
            </template>
            <template x-if="visionCand && !visionCand.vision && !(visionCand.missions && visionCand.missions.length)">
              <p class="vision-modal-empty">Paslon ini belum mengisi visi &amp; misi.</p>
            </template>
          </div>
        </template>
      </div>
      <x-slot:footer>
        <button type="button" class="btn btn-primary" x-on:click="visionModalId = null">Tutup</button>
      </x-slot:footer>
    </x-modal>
  </template>

  {{-- Update v5 — Bagian A: penolakan gate baca (pesan persis; dibuka instan di klien atau lewat event server 'gate-rejected') --}}
  <template x-if="gateRejectionOpen">
    <x-modal
      title="Belum Bisa Lanjut"
      subtitle="Baca visi &amp; misi seluruh paslon terlebih dahulu."
      icon="#i-lock"
      iconVariant="warning"
      maxWidth="500px"
      alpineClose="gateRejectionOpen = false"
    >
      <div class="modal-body">
        <p class="gate-rejection-message">Anda belum boleh lanjut sebelum membaca seluruh visi &amp; misi setiap paslon.</p>
        <template x-if="gateUnreadNames.length">
          <div class="gate-rejection-unread">
            <span>Belum dibaca:</span>
            <ul>
              <template x-for="name in gateUnreadNames" :key="name">
                <li x-text="name"></li>
              </template>
            </ul>
          </div>
        </template>
      </div>
      <x-slot:footer>
        <button type="button" class="btn btn-primary" x-on:click="gateRejectionOpen = false">Saya mengerti</button>
      </x-slot:footer>
    </x-modal>
  </template>
</div>
