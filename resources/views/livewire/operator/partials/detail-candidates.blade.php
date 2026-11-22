{{-- FR-011, BR-011, BR-024, AC-013: Section 2 Daftar Pasangan Calon (Collapsible Dropdown + Nomor 2) --}}
<section class="panel collapsible-panel" style="margin-bottom: 16px;" x-data="{ open: true, visionCandidate: null }">
  <div
    class="panel-head collapsible-head"
    role="button"
    tabindex="0"
    :aria-expanded="open ? 'true' : 'false'"
    x-on:click="open = !open"
    x-on:keydown.enter.prevent="open = !open"
    x-on:keydown.space.prevent="open = !open"
  >
    <div class="section-numbered-title">
      <span class="section-num-badge">2</span>
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-users"></use></svg>
          <span>Daftar Pasangan Calon ({{ $candidates->count() }})</span>
        </h2>
        {{-- BR-024: Paslon hanya dapat diubah pada Fase Persiapan --}}
        <p class="panel-sub">
          @if($phase === 'persiapan')
            Tambahkan minimal 2 pasangan calon beserta foto, visi, dan misi sebelum mengatur jadwal pemilihan.
          @else
            Daftar pasangan calon telah dikunci karena pemilihan sudah melewati Fase Persiapan.
          @endif
        </p>
      </div>
    </div>

    <div class="collapsible-head-right" x-on:click.stop>
      @if($phase === 'persiapan')
        <x-action-button
          variant="primary"
          size="sm"
          icon="#i-plus"
          wire:click="openAddCandidateModal"
          loadingText="Membuka..."
        >
          Tambah Paslon
        </x-action-button>
      @endif
      <span class="collapse-chevron" :class="{ 'is-open': open }" x-on:click="open = !open">
        <svg class="icon"><use href="#i-chevron-right"></use></svg>
      </span>
    </div>
  </div>

  <div x-show="open" class="collapsible-body">
    <div style="padding: 20px 23px;">
      @if($candidates->isEmpty())
        <div class="empty-state">
          <div class="empty-icon"><svg class="icon"><use href="#i-users"></use></svg></div>
          <h3>Belum ada paslon</h3>
          <p>Tambahkan minimal 2 pasangan calon agar jadwal pemilihan dapat diatur.</p>
        </div>
      @else
        <div class="candidate-grid candidate-detail-grid">
          @foreach($candidates as $cand)
            <div class="candidate-card candidate-detail-card" wire:key="cand-{{ $cand->id }}"
              :style="bentoRows ? { '--candidate-bento-rows': bentoRows } : {}"
              x-data="{
                tallPhoto: false,
                bentoRows: 0,
                sizeObserver: null,
                init() {
                  this.sizeObserver = new ResizeObserver(([entry]) => {
                    const height = entry.borderBoxSize?.[0]?.blockSize ?? this.$el.offsetHeight;
                    this.bentoRows = Math.ceil(height) + 14;
                  });
                  this.sizeObserver.observe(this.$el, { box: 'border-box' });
                },
                destroy() {
                  this.sizeObserver.disconnect();
                },
                inspectPhoto(image) {
                  this.tallPhoto = image.naturalWidth > 0 && image.naturalWidth / image.naturalHeight <= 0.75;
                }
              }">
              <div class="candidate-detail-content" :class="{ 'is-tall-portrait': tallPhoto }">
                <div class="candidate-detail-main">
                  <span class="candidate-kicker candidate-card-label">Pasangan Calon {{ $cand->formattedNumber() }}</span>
                  <div class="candidate-body">
                    <h3 class="candidate-names">{{ $cand->name }}</h3>
                    @php
                      $visionCandidate = [
                          'number' => $cand->formattedNumber(),
                          'name' => $cand->name,
                          'photo' => $cand->photoUrl(),
                          'vision' => $cand->vision,
                          'missions' => $cand->missionItems(),
                      ];
                    @endphp
                    <button type="button" class="btn btn-sm btn-secondary candidate-vision-button"
                      aria-haspopup="dialog" aria-controls="operator-candidate-vision"
                      x-on:click="visionCandidate = @js($visionCandidate); $refs.visionDialog.showModal()">
                      <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                      <span>Lihat Visi dan Misi</span>
                    </button>
                  </div>

                  @if($phase === 'persiapan')
                    <div class="candidate-detail-actions">
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
                <div class="candidate-media">
                  <x-candidate-photo :src="$cand->photoUrl()" :alt="'Foto '.$cand->name"
                    x-init="inspectPhoto($el)" x-on:load="inspectPhoto($el)" x-on:error="inspectPhoto($el)" />
                  <span class="candidate-photo-label" aria-hidden="true">Paslon {{ $cand->formattedNumber() }}</span>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  <dialog id="operator-candidate-vision" class="modal candidate-vision-dialog" x-ref="visionDialog"
    aria-labelledby="operator-candidate-vision-title" x-on:close="visionCandidate = null">
    <div class="modal-header">
      <h2 id="operator-candidate-vision-title">Visi dan Misi Pasangan Calon</h2>
      <button type="button" class="icon-btn" aria-label="Tutup dialog" x-on:click="$refs.visionDialog.close()">
        <svg class="icon" aria-hidden="true"><use href="#i-x"></use></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="candidate-vision-heading">
        <img :src="visionCandidate?.photo || null" alt="" />
        <div>
          <span class="candidate-kicker" x-text="'Pasangan Calon ' + (visionCandidate?.number ?? '')"></span>
          <h3 class="candidate-names" x-text="visionCandidate?.name"></h3>
        </div>
      </div>
      <div class="candidate-vision">
        <span>Visi</span>
        <p x-text="visionCandidate?.vision || 'Belum ada visi yang dicantumkan.'"></p>
      </div>
      <div class="candidate-vision">
        <span>Daftar Misi</span>
        <ul class="mission-list" x-show="visionCandidate?.missions.length">
          <template x-for="(mission, index) in visionCandidate?.missions ?? []" :key="index">
            <li x-text="mission"></li>
          </template>
        </ul>
        <p x-show="!visionCandidate?.missions.length">Belum ada misi yang dicantumkan.</p>
      </div>
    </div>
  </dialog>
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
