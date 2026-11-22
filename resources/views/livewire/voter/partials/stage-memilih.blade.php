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
        Baca visi &amp; misi setiap pasangan calon melalui tombol <strong>Lihat Visi &amp; Misi</strong>, pilih satu pasangan calon, lalu tekan <strong>Lanjut ke Konfirmasi</strong>.
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
      <div
        role="radio"
        tabindex="0"
        :aria-checked="clientSelectedId === '{{ $cand->id }}' ? 'true' : 'false'"
        class="candidate-card"
        :class="{ 'is-selected': clientSelectedId === '{{ $cand->id }}' }"
        x-on:click="chooseCandidate('{{ $cand->id }}')"
        x-on:keydown.enter.self.prevent="chooseCandidate('{{ $cand->id }}')"
        x-on:keydown.space.self.prevent="chooseCandidate('{{ $cand->id }}')"
        wire:key="ballot-cand-{{ $cand->id }}"
      >
        <span class="candidate-kicker candidate-card-label">Pasangan Calon {{ $cand->formattedNumber() }}</span>
        <div class="candidate-media">
          <x-candidate-photo :src="$cand->photoUrl()" :alt="'Foto Paslon '.$cand->formattedNumber().' — '.$cand->name" />
          <span class="candidate-check" aria-hidden="true">
            <svg class="icon"><use href="#i-check"></use></svg>
          </span>
        </div>

        <div class="candidate-body">
          <h2 class="candidate-names">{{ $cand->name }}</h2>

          <div class="candidate-card-actions">
            <button
              type="button"
              class="btn btn-sm candidate-vm-btn"
              :class="isCandidateRead('{{ $cand->id }}') ? 'is-read' : 'is-unread'"
              x-on:click.stop="openVisionMissionModal('{{ $cand->id }}')"
            >
              <span class="candidate-vm-btn-main">
                <svg class="icon" x-show="!isCandidateRead('{{ $cand->id }}')"><use href="#i-eye"></use></svg>
                <svg class="icon" x-show="isCandidateRead('{{ $cand->id }}')" x-cloak><use href="#i-check-circle"></use></svg>
                <span>Lihat Visi &amp; Misi</span>
              </span>
              <span class="candidate-vm-badge" x-text="isCandidateRead('{{ $cand->id }}') ? 'Sudah Dibaca' : 'Belum Dibaca'">
                Belum Dibaca
              </span>
            </button>

            <button
              type="button"
              class="btn btn-sm candidate-choose-btn"
              :class="clientSelectedId === '{{ $cand->id }}' ? 'btn-primary is-chosen' : 'btn-secondary'"
              x-on:click.stop="chooseCandidate('{{ $cand->id }}')"
            >
              <svg class="icon"><use href="#i-check-circle"></use></svg>
              <span>Pilih Paslon Ini</span>
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

  {{-- Indikator status baca visi & misi seluruh paslon --}}
  <div class="vm-read-progress" :class="{ 'is-complete': hasReadAllCandidates }">
    <div class="vm-read-progress-left">
      <svg class="icon" x-show="!hasReadAllCandidates"><use href="#i-info"></use></svg>
      <svg class="icon" x-show="hasReadAllCandidates" x-cloak><use href="#i-check-circle"></use></svg>
      <span x-text="hasReadAllCandidates ? 'Seluruh visi & misi pasangan calon telah Anda baca. Silakan lanjut ke konfirmasi.' : 'Wajib membaca visi & misi seluruh pasangan calon sebelum melanjutkan ke tahap konfirmasi.'">
        Wajib membaca visi &amp; misi seluruh pasangan calon sebelum melanjutkan ke tahap konfirmasi.
      </span>
    </div>
    <strong x-text="readCandidatesCount + ' / ' + allCandidateIds.length + ' Paslon Dibaca'"></strong>
  </div>

  <div class="ballot-bar">
    <div class="ballot-bar-info">
      <span>PILIHAN SEMENTARA (BELUM TERSIMPAN)</span>
      <strong x-text="selectedCand ? ('Paslon ' + selectedCand.number + ' — ' + selectedCand.name) : 'Belum ada pasangan calon yang dipilih'">
        Belum ada pasangan calon yang dipilih
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

  {{-- Update v5 Bagian A: Popup (Modal) Visi & Misi Pasangan Calon --}}
  <x-modal
    title="Visi & Misi Pasangan Calon"
    subtitle="Pelajari gagasan pasangan calon sebelum menentukan pilihan Anda."
    icon="#i-ballot"
    maxWidth="580px"
    alpineShow="activeVmCand !== null"
    alpineClose="closeVisionMissionModal()"
  >
    <div class="modal-body" x-show="activeVmCand !== null">
      <div class="vm-modal-hero">
        <img
          :src="activeVmCand ? activeVmCand.photoUrl : ''"
          :alt="activeVmCand ? ('Foto Paslon ' + activeVmCand.number) : ''"
          class="vm-modal-photo"
        />
        <div>
          <span class="vm-modal-kicker" x-text="activeVmCand ? ('PASANGAN CALON NO. ' + activeVmCand.number) : ''"></span>
          <h3 class="vm-modal-name" x-text="activeVmCand ? activeVmCand.name : ''"></h3>
        </div>
      </div>

      <div class="vm-modal-section">
        <span class="vm-modal-label">Visi</span>
        <p class="vm-modal-vision" x-text="(activeVmCand && activeVmCand.vision) ? activeVmCand.vision : 'Belum ada visi yang dicantumkan.'"></p>
      </div>

      <div class="vm-modal-section">
        <span class="vm-modal-label">Daftar Misi</span>
        <template x-if="activeVmCand && Array.isArray(activeVmCand.missions) && activeVmCand.missions.length > 0">
          <ol class="vm-modal-missions">
            <template x-for="(mItem, mIdx) in activeVmCand.missions" :key="mIdx">
              <li x-text="mItem"></li>
            </template>
          </ol>
        </template>
        <template x-if="!activeVmCand || !Array.isArray(activeVmCand.missions) || activeVmCand.missions.length === 0">
          <p class="vm-modal-vision">Belum ada misi yang dicantumkan.</p>
        </template>
      </div>
    </div>

    <x-slot:footer>
      <button type="button" class="btn btn-primary" x-on:click="closeVisionMissionModal()">
        <svg class="icon"><use href="#i-check"></use></svg>
        <span>Selesai Membaca</span>
      </button>
    </x-slot:footer>
  </x-modal>
</div>
