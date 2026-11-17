{{-- FR-011, BR-011, BR-024, AC-013: Panel Daftar Pasangan Calon & Modal Tambah/Ubah/Hapus Paslon --}}
<section class="panel">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>Daftar Pasangan Calon ({{ $candidates->count() }})</span>
      </h2>
      {{-- BR-024: Paslon hanya dapat diubah pada Fase Persiapan --}}
      <p class="panel-sub">
        @if($phase === 'persiapan')
          Tambahkan atau ubah pasangan calon beserta foto, visi, dan misi sebelum pemilihan berlangsung.
        @else
          Daftar pasangan calon telah dikunci karena pemilihan sudah melewati Fase Persiapan.
        @endif
      </p>
    </div>

    @if($phase === 'persiapan')
      <x-action-button
        variant="primary"
        icon="#i-plus"
        wire:click="openAddCandidateModal"
        loadingText="Membuka..."
      >
        Tambah Paslon
      </x-action-button>
    @endif
  </div>

  <div style="padding: 20px 23px;">
    @if($candidates->isEmpty())
      <div class="empty-state">
        <div class="empty-icon"><svg class="icon"><use href="#i-users"></use></svg></div>
        <h3>Belum ada paslon</h3>
        <p>Tambahkan minimal 2 pasangan calon agar jadwal pemilihan dapat diatur.</p>
      </div>
    @else
      <div class="candidate-grid">
        @foreach($candidates as $cand)
          <div class="candidate-card" style="cursor: default;" wire:key="cand-{{ $cand->id }}">
            <div class="candidate-media">
              <img src="{{ $cand->photoUrl() }}" alt="Foto {{ $cand->name }}" class="candidate-photo" />
              <span class="candidate-number">NO. {{ $cand->formattedNumber() }}</span>
            </div>
            <div class="candidate-body">
              <span class="candidate-kicker">PASANGAN CALON {{ $cand->formattedNumber() }}</span>
              <h3 class="candidate-names">{{ $cand->name }}</h3>

              @if($cand->vision)
                <div class="candidate-vision">
                  <span>VISI</span>
                  <p>{{ $cand->vision }}</p>
                </div>
              @endif

              @php $missions = $cand->missionItems(); @endphp
              @if(!empty($missions))
                <ul class="mission-list">
                  @foreach($missions as $m)
                    <li>{{ $m }}</li>
                  @endforeach
                </ul>
              @endif

              @if($phase === 'persiapan')
                <div style="margin-top: auto; padding-top: 14px; display: flex; gap: 8px; border-top: 1px solid #eef2f8;">
                  <x-action-button
                    variant="secondary"
                    size="xs"
                    icon="#i-edit"
                    wire:click="openEditCandidateModal('{{ $cand->id }}')"
                    loadingText="Memuat..."
                  >
                    Ubah
                  </x-action-button>

                  <x-action-button
                    variant="danger-ghost"
                    size="xs"
                    icon="#i-trash"
                    wire:click="promptDeleteCandidate('{{ $cand->id }}')"
                    loadingText="Memuat..."
                  >
                    Hapus
                  </x-action-button>
                </div>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</section>

{{-- FR-011: Modal Tambah/Ubah Paslon --}}
@if($showCandidateModal)
  <x-modal
    :title="$editingCandidateId ? 'Ubah Pasangan Calon' : 'Tambah Pasangan Calon'"
    subtitle="Nomor urut dan nama wajib diisi. Foto, visi, dan misi bersifat opsional."
    icon="#i-users"
    closeAction="closeCandidateModal"
  >
    <form wire:submit="saveCandidate" novalidate>
      <div class="modal-body">
        <div class="grid-2col" style="gap: 12px;">
          <div class="form-field">
            <label for="cand-num" class="field-label">Nomor Urut <span class="req">*</span></label>
            <input
              id="cand-num"
              type="number"
              min="1"
              max="99"
              wire:model="candidateNumber"
              class="field-input @error('candidateNumber') is-invalid @enderror"
            />
            @error('candidateNumber')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>

          <div class="form-field" style="margin-top: 0;">
            <label for="cand-photo" class="field-label">Foto Paslon (Opsional)</label>
            <input
              id="cand-photo"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              wire:model="candidatePhoto"
              class="field-input"
              style="padding: 7px 10px;"
            />
            <div wire:loading.inline-flex wire:target="candidatePhoto" class="field-hint" style="color:var(--brand);align-items:center;gap:6px;">
              <span class="spinner" style="border-color:rgba(22,53,125,0.2);border-top-color:var(--brand);"></span>
              <span>Mengunggah pratinjau foto...</span>
            </div>
            @error('candidatePhoto')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>
        </div>

        <div class="form-field">
          <label for="cand-name" class="field-label">Nama Pasangan Calon <span class="req">*</span></label>
          <input
            id="cand-name"
            type="text"
            wire:model="candidateName"
            class="field-input @error('candidateName') is-invalid @enderror"
            placeholder="Contoh: Raka Pratama & Nadira Putri"
          />
          @error('candidateName')
            <div class="field-error" role="alert">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>{{ $message }}</span>
            </div>
          @enderror
        </div>

        <div class="form-field">
          <label for="cand-vision" class="field-label">Visi (Opsional)</label>
          <textarea
            id="cand-vision"
            wire:model="candidateVision"
            class="field-textarea"
            style="min-height: 68px;"
            placeholder="Ringkasan visi pasangan calon..."
          ></textarea>
          @error('candidateVision')
            <div class="field-error" role="alert">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>{{ $message }}</span>
            </div>
          @enderror
        </div>

        <div class="form-field">
          <label for="cand-missions" class="field-label">Daftar Misi (Opsional — satu poin per baris)</label>
          <textarea
            id="cand-missions"
            wire:model="candidateMissionsText"
            class="field-textarea"
            placeholder="Meningkatkan literasi digital sekolah&#10;Mengadakan forum aspirasi bulanan&#10;Memperkuat kolaborasi ekstrakurikuler"
          ></textarea>
          @error('candidateMissionsText')
            <div class="field-error" role="alert">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>{{ $message }}</span>
            </div>
          @enderror
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" wire:click="closeCandidateModal">Batal</button>
        <x-action-button
          type="submit"
          variant="primary"
          target="saveCandidate,candidatePhoto"
          icon="#i-check"
          loadingText="Menyimpan paslon..."
        >
          Simpan Paslon
        </x-action-button>
      </div>
    </form>
  </x-modal>
@endif

{{-- FR-011, BR-024: Modal Konfirmasi Hapus Paslon --}}
@if($showDeleteCandidateModal)
  <x-modal
    title="Hapus Pasangan Calon?"
    :subtitle="e($deletingCandidateName)"
    icon="#i-trash"
    iconVariant="danger"
    maxWidth="480px"
    closeAction="$set('showDeleteCandidateModal', false)"
  >
    <div class="modal-body">
      <p class="confirm-message">
        Data pasangan calon ini beserta berkas fotonya akan dihapus dari pemilihan ini.
      </p>
    </div>

    <x-slot:footer>
      <button type="button" class="btn btn-secondary" wire:click="$set('showDeleteCandidateModal', false)">Batal</button>
      <x-action-button
        variant="danger"
        icon="#i-trash"
        wire:click="confirmDeleteCandidate"
        loadingText="Menghapus..."
      >
        Hapus Paslon
      </x-action-button>
    </x-slot:footer>
  </x-modal>
@endif
