{{-- FR-008, FR-009, FR-010, FR-028, BR-018, BR-023, BR-024, BR-025, BR-032: Urutan 1 Identitas -> 2 Paslon -> 3 Jadwal --}}

{{-- SECTION 1: Identitas & Batas Hak Pilih (Collapsible Dropdown + Nomor 1) --}}
<section class="panel collapsible-panel" style="margin-bottom: 16px;" x-data="{ open: true }">
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
      <span class="section-num-badge">1</span>
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-ballot"></use></svg>
          <span>Identitas &amp; Batas Hak Pilih</span>
        </h2>
        <p class="panel-sub">Terdaftar saat ini: <strong>{{ $voterRightsCount }}</strong> dari <strong>{{ $election->max_voter_rights }}</strong> hak pilih (batas 3–10.000).</p>
      </div>
    </div>

    <div class="collapsible-head-right">
      <span class="status-badge {{ $phase === 'berlangsung' ? 'is-sedang' : ($phase === 'ditutup' ? 'is-sudah' : 'is-belum') }}">
        <span class="status-dot"></span>
        <span>{{ \App\Models\Election::phaseLabel($phase) }}</span>
      </span>
      <span class="collapse-chevron" :class="{ 'is-open': open }">
        <svg class="icon"><use href="#i-chevron-right"></use></svg>
      </span>
    </div>
  </div>

  <div x-show="open" class="collapsible-body">
    <form wire:submit="saveElectionInfo" style="padding: 20px 23px;" novalidate>
      <div class="grid-2col" style="gap: 16px;">
        <div class="form-field" style="margin-top: 0;">
          <label for="detail-name" class="field-label">Nama Pemilihan <span class="req">*</span></label>
          <input
            id="detail-name"
            type="text"
            wire:model="name"
            class="field-input @error('name') is-invalid @enderror"
          />
          @error('name')
            <div class="field-error" role="alert">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>{{ $message }}</span>
            </div>
          @enderror
        </div>

        {{-- BR-018 & Perubahan 1: Batas 3 s.d. 10000 dan tidak boleh di bawah jumlah hak pilih yang sudah dibuat --}}
        <div class="form-field" style="margin-top: 0;">
          <label for="detail-max" class="field-label">Batas Maksimum Hak Pilih (3 – 10.000) <span class="req">*</span></label>
          <input
            id="detail-max"
            type="number"
            min="{{ max(3, $voterRightsCount) }}"
            max="10000"
            wire:model="maxVoterRights"
            class="field-input @error('maxVoterRights') is-invalid @enderror"
          />
          <p class="field-hint">Minimal {{ max(3, $voterRightsCount) }} (mengikuti hak pilih terdaftar: {{ $voterRightsCount }}) dan maksimal 10.000.</p>
          @error('maxVoterRights')
            <div class="field-error" role="alert">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>{{ $message }}</span>
            </div>
          @enderror
        </div>
      </div>

      <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
        <x-action-button
          type="submit"
          variant="primary"
          target="saveElectionInfo"
          icon="#i-check"
          loadingText="Menyimpan..."
        >
          Simpan Perubahan
        </x-action-button>
      </div>
    </form>
  </div>
</section>

{{-- SECTION 2: Daftar Pasangan Calon (Collapsible Dropdown + Nomor 2) --}}
@include('livewire.operator.partials.detail-candidates')

{{-- SECTION 3: Jadwal Pemilihan (Collapsible Dropdown + Nomor 3) --}}
<section class="panel collapsible-panel" style="margin-bottom: 16px;" x-data="{ open: true }">
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
      <span class="section-num-badge">3</span>
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-clock"></use></svg>
          <span>Jadwal Pemilihan ({{ $tz }})</span>
        </h2>
        <p class="panel-sub">Fase pemilihan berubah otomatis mengikuti jadwal. Minimal 2 pasangan calon diperlukan.</p>
      </div>
    </div>

    <div class="collapsible-head-right">
      <span class="collapse-chevron" :class="{ 'is-open': open }">
        <svg class="icon"><use href="#i-chevron-right"></use></svg>
      </span>
    </div>
  </div>

  <div x-show="open" class="collapsible-body">
    <div style="padding: 20px 23px;">
      {{-- BR-024: Jadwal hanya dapat diatur bila paslon minimal 2 --}}
      @if($candidates->count() < 2 && $phase === 'persiapan')
        <div class="alert alert-warning" style="margin-bottom: 14px;">
          <svg class="icon"><use href="#i-alert"></use></svg>
          <div>
            <strong>Minimal 2 Pasangan Calon</strong>
            <p>Tambahkan minimal 2 pasangan calon pada bagian <strong>② Daftar Pasangan Calon</strong> di atas sebelum menyimpan jadwal pemilihan.</p>
          </div>
        </div>
      @endif

      <form wire:submit="saveSchedule" novalidate>
        <div class="grid-2col" style="gap: 16px;">
          <div class="form-field" style="margin-top: 0;">
            <label for="sched-start" class="field-label">Waktu Mulai<span class="req">*</span></label>
            <input
              id="sched-start"
              type="datetime-local"
              wire:model="startsAtInput"
              class="field-input @error('startsAtInput') is-invalid @enderror"
              {{ $phase !== 'persiapan' ? 'disabled' : '' }}
            />
            {{-- BR-025: Waktu Mulai terkunci setelah Berlangsung --}}
            @if($phase === 'berlangsung')
              <p class="field-hint">Waktu Mulai terkunci karena pemilihan sedang berlangsung.</p>
            @endif
            @error('startsAtInput')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>

          <div class="form-field" style="margin-top: 0;">
            <label for="sched-end" class="field-label">Waktu Selesai<span class="req">*</span></label>
            <input
              id="sched-end"
              type="datetime-local"
              wire:model="endsAtInput"
              class="field-input @error('endsAtInput') is-invalid @enderror"
              {{ $phase === 'ditutup' ? 'disabled' : '' }}
            />
            <p class="field-hint">Setelah Waktu Selesai tercapai, pemungutan suara ditutup otomatis dan hasil dibuka.</p>
            @error('endsAtInput')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>
        </div>

        <div style="margin-top: 18px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center;">
          @if($phase === 'persiapan')
            <x-action-button
              variant="soft"
              icon="#i-sparkles"
              wire:click="startElectionNow"
              loadingText="Memulai..."
              :disabled="$candidates->count() < 2"
            >
              Mulai Sekarang
            </x-action-button>

            <x-action-button
              type="submit"
              variant="primary"
              target="saveSchedule"
              icon="#i-calendar"
              loadingText="Menyimpan jadwal..."
              :disabled="$candidates->count() < 2"
            >
              Simpan Jadwal
            </x-action-button>
          @elseif($phase === 'berlangsung')
            <button type="button" class="btn btn-danger-ghost" x-on:click="$wire.showEndEarlyModal = true">
              <svg class="icon"><use href="#i-lock"></use></svg>
              <span>Akhiri Pemilihan Sekarang</span>
            </button>

            <x-action-button
              type="submit"
              variant="primary"
              target="saveSchedule"
              icon="#i-calendar"
              loadingText="Memperbarui..."
            >
              Perbarui Waktu Selesai
            </x-action-button>
          @else
            {{-- BR-032: Pemilihan tidak dapat dibuka kembali setelah Ditutup --}}
            <div class="alert alert-info" style="width: 100%;">
              <svg class="icon"><use href="#i-lock"></use></svg>
              <div>
                <strong>Pemilihan telah ditutup</strong>
                <p>Jadwal pemilihan telah berakhir pada {{ $election->formattedSchedule($election->ends_at) }} dan tidak dapat dibuka kembali.</p>
              </div>
            </div>
          @endif
        </div>
      </form>
    </div>
  </div>
</section>

{{-- OD-006a, BR-025, BR-032, AC-011: Modal Konfirmasi Akhiri Pemilihan Lebih Awal --}}
@if($phase === 'berlangsung')
  <x-modal
    title="Akhiri Pemilihan Sekarang?"
    subtitle="Tindakan ini bersifat permanen dan tidak dapat dibatalkan."
    icon="#i-lock"
    iconVariant="danger"
    maxWidth="500px"
    alpineShow="$wire.showEndEarlyModal"
    alpineClose="$wire.showEndEarlyModal = false"
  >
    <div class="modal-body">
      <div class="alert alert-warning">
        <svg class="icon"><use href="#i-alert"></use></svg>
        <div>
          <strong>Tidak dapat dibuka kembali</strong>
          <p>Waktu Selesai akan dimajukan ke waktu sekarang. Pemilih yang masih dalam keadaan <code>Sedang memilih</code> tidak dapat lagi mengirim konfirmasi, dan hasil perolehan suara akan langsung dibuka.</p>
        </div>
      </div>
    </div>

    <x-slot:footer>
      <button type="button" class="btn btn-secondary" x-on:click="$wire.showEndEarlyModal = false">Batal</button>
      <x-action-button
        variant="danger"
        icon="#i-lock"
        wire:click="confirmEndEarly"
        loadingText="Mengakhiri..."
      >
        Ya, Akhiri Pemilihan
      </x-action-button>
    </x-slot:footer>
  </x-modal>
@endif
