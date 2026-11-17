{{-- FR-008, FR-009, FR-010, FR-028, BR-018, BR-023, BR-024, BR-025, BR-032: Panel Identitas, Kuota, & Jadwal --}}
<div class="grid-2col" style="margin-bottom: 18px;">
  {{-- Panel Identitas & Batas Hak Pilih --}}
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-ballot"></use></svg>
          <span>Identitas &amp; Batas Hak Pilih</span>
        </h2>
        <p class="panel-sub">Terdaftar saat ini: <strong>{{ $voterRightsCount }}</strong> dari <strong>{{ $election->max_voter_rights }}</strong> hak pilih.</p>
      </div>
      <span class="status-badge {{ $phase === 'berlangsung' ? 'is-sedang' : ($phase === 'ditutup' ? 'is-sudah' : 'is-belum') }}">
        <span class="status-dot"></span>
        <span>{{ \App\Models\Election::phaseLabel($phase) }}</span>
      </span>
    </div>

    <form wire:submit="saveElectionInfo" style="padding: 20px 23px;" novalidate>
      <div class="form-field">
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

      {{-- BR-018: Batas tidak boleh diturunkan di bawah jumlah hak pilih yang sudah dibuat --}}
      <div class="form-field">
        <label for="detail-max" class="field-label">Batas Maksimum Hak Pilih <span class="req">*</span></label>
        <input
          id="detail-max"
          type="number"
          min="{{ max(1, $voterRightsCount) }}"
          wire:model="maxVoterRights"
          class="field-input @error('maxVoterRights') is-invalid @enderror"
        />
        <p class="field-hint">Tidak boleh diturunkan di bawah jumlah hak pilih yang sudah dibuat ({{ $voterRightsCount }}).</p>
        @error('maxVoterRights')
          <div class="field-error" role="alert">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <span>{{ $message }}</span>
          </div>
        @enderror
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
  </section>

  {{-- Panel Jadwal Pemilihan --}}
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-clock"></use></svg>
          <span>Jadwal Pemilihan ({{ $tz }})</span>
        </h2>
        <p class="panel-sub">Fase pemilihan berubah otomatis mengikuti jadwal. Minimal 2 paslon diperlukan.</p>
      </div>
    </div>

    <div style="padding: 20px 23px;">
      {{-- BR-024: Jadwal hanya dapat diatur bila paslon minimal 2 --}}
      @if($candidates->count() < 2 && $phase === 'persiapan')
        <div class="alert alert-warning" style="margin-bottom: 14px;">
          <svg class="icon"><use href="#i-alert"></use></svg>
          <div>
            <strong>Minimal 2 Pasangan Calon</strong>
            <p>Tambahkan minimal 2 pasangan calon terlebih dahulu sebelum menyimpan jadwal pemilihan.</p>
          </div>
        </div>
      @endif

      <form wire:submit="saveSchedule" novalidate>
        <div class="form-field">
          <label for="sched-start" class="field-label">Waktu Mulai (Tahun-Bulan-Tanggal Jam:Menit) <span class="req">*</span></label>
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

        <div class="form-field">
          <label for="sched-end" class="field-label">Waktu Selesai (Tahun-Bulan-Tanggal Jam:Menit) <span class="req">*</span></label>
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
            <x-action-button
              variant="danger-ghost"
              icon="#i-lock"
              wire:click="$set('showEndEarlyModal', true)"
              loadingText="Membuka..."
            >
              Akhiri Pemilihan Sekarang
            </x-action-button>

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
  </section>
</div>

{{-- OD-006a, BR-025, BR-032, AC-011: Modal Konfirmasi Akhiri Pemilihan Lebih Awal --}}
@if($showEndEarlyModal)
  <x-modal
    title="Akhiri Pemilihan Sekarang?"
    subtitle="Tindakan ini bersifat permanen dan tidak dapat dibatalkan."
    icon="#i-lock"
    iconVariant="danger"
    maxWidth="500px"
    closeAction="$set('showEndEarlyModal', false)"
  >
    <div class="modal-body">
      <div class="alert alert-warning">
        <svg class="icon"><use href="#i-alert"></use></svg>
        <div>
          <strong>Tidak dapat dibuka kembali</strong>
          <p>Waktu Selesai akan dimajukan ke waktu sekarang. Pemilih yang masih berstatus <code>Sedang memilih</code> tidak dapat lagi mengirim konfirmasi, dan hasil perolehan suara akan langsung dibuka.</p>
        </div>
      </div>
    </div>

    <x-slot:footer>
      <button type="button" class="btn btn-secondary" wire:click="$set('showEndEarlyModal', false)">Batal</button>
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
