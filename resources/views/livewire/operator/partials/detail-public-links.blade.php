{{-- KP-008, OD-014, FR-021, FR-022 & Perubahan 7: Section 4 Tautan Halaman Publik & Access Key Rekap Real-Time --}}
<section class="panel collapsible-panel" style="margin-bottom: 18px;" x-data="{ open: true }">
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
      <span class="section-num-badge">4</span>
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-external"></use></svg>
          <span>Tautan Halaman Publik &amp; Akses Rekap Real-Time</span>
        </h2>
        <p class="panel-sub">Bagikan tautan publik kepada warga sekolah, atau kelola Access Key khusus untuk pemantauan hasil real-time.</p>
      </div>
    </div>

    <div class="collapsible-head-right">
      <span class="collapse-chevron" :class="{ 'is-open': open }">
        <svg class="icon"><use href="#i-chevron-right"></use></svg>
      </span>
    </div>
  </div>

  <div x-show="open" class="collapsible-body">
    <div style="padding: 18px 23px;">
      <div class="grid-2col" style="margin-bottom: 18px;">
        <x-copy-link-box
          label="1. HALAMAN PUBLIK JUMLAH PEMILIH (TERBUKA KAPAN SAJA)"
          :url="route('public.turnout', $election)"
          copyToast="Disalin"
          style="margin-top: 0;"
        />

        <x-copy-link-box
          label="2. HALAMAN PUBLIK HASIL (TERBUKA SETELAH WAKTU SELESAI)"
          :url="route('public.results', $election)"
          copyToast="Disalin"
          style="margin-top: 0;"
        />
      </div>

      {{-- Perubahan 7: Kontrol Access Key Rekap Real-Time (1 Kunci Aktif 64 Karakter per Pemilihan) --}}
      <div class="access-key-panel">
        <div class="access-key-head">
          <div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <h3 style="font-size: 0.92rem; font-weight: 800; color: var(--ink);">
                <svg class="icon" style="width: 16px; height: 16px; color: var(--brand); vertical-align: -3px;"><use href="#i-lock"></use></svg>
                <span>Access Key Rekapitulasi Real-Time (Khusus Pihak Berwenang)</span>
              </h3>
              @if($hasAccessKey)
                <span class="status-badge is-sudah">
                  <span class="status-dot"></span>
                  <span>1 Kunci Aktif</span>
                </span>
              @else
                <span class="status-badge is-belum">
                  <span class="status-dot"></span>
                  <span>Belum Ada Kunci Aktif</span>
                </span>
              @endif
            </div>
            <p class="field-hint" style="margin-top: 4px;">
              Membuka akses halaman rekapitulasi suara sementara secara real-time sebelum Waktu Selesai. Hanya 1 kunci (64 karakter acak) yang dapat aktif pada satu waktu; menerbitkan kunci baru otomatis mencabut kunci lama.
              @if($hasAccessKey && $accessKeyCreatedAt)
                <br />Diterbitkan terakhir pada: <strong>{{ $accessKeyCreatedAt->format('d M Y, H:i:s') }} ({{ $tz }})</strong>.
              @endif
            </p>
          </div>

          <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            @if($hasAccessKey)
              <button type="button" class="btn btn-danger-ghost btn-sm" x-on:click="$wire.showRevokeAccessKeyModal = true">
                <svg class="icon"><use href="#i-x"></use></svg>
                <span>Cabut Kunci</span>
              </button>
            @endif
            <x-action-button
              variant="primary"
              size="sm"
              icon="#i-sparkles"
              wire:click="issueAccessKey"
              loadingText="Menerbitkan..."
            >
              {{ $hasAccessKey ? 'Terbitkan Ulang Kunci Baru' : 'Terbitkan Access Key (64 Karakter)' }}
            </x-action-button>
          </div>
        </div>

        <x-copy-link-box
          label="URL KHUSUS REKAP REAL-TIME (WAJIB MEMASUKKAN ACCESS KEY 64 KARAKTER)"
          :url="$liveRecapUrl"
          copyToast="Disalin"
          style="margin-top: 12px;"
        />
      </div>
    </div>
  </div>
</section>

{{-- Perubahan 7: Modal Sekali Tampil Access Key 64 Karakter --}}
@if($showAccessKeyModal)
  <x-modal
    title="Access Key Rekap Real-Time Diterbitkan"
    subtitle="Salin kunci 64 karakter di bawah ini sekarang. Demi keamanan, kunci mentah tidak disimpan di server dan hanya ditampilkan satu kali ini saja."
    icon="#i-lock"
    iconVariant="success"
    maxWidth="600px"
  >
    <div class="modal-body">
      <div class="alert alert-warning" role="alert">
        <svg class="icon"><use href="#i-alert"></use></svg>
        <div>
          <strong>Hanya tampil satu kali &amp; rahasiakan dari area pemilihan</strong>
          <p>Server hanya menyimpan hash satu arah (SHA-256). Jika kunci ini hilang, Anda harus menerbitkan ulang kunci baru (yang otomatis mencabut kunci lama).</p>
        </div>
      </div>

      <div class="temp-password-box" style="margin-top: 14px;">
        <span style="font-size:0.68rem;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;color:var(--muted);">
          ACCESS KEY (64 KARAKTER ACAK — TANPA SPASI)
        </span>
        <code style="word-break: break-all; font-size: 0.92rem;">{{ $issuedAccessKey }}</code>
        <div style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
          <button
            type="button"
            class="btn btn-sm btn-primary"
            onclick="navigator.clipboard.writeText(@js($issuedAccessKey)); window.showToast('Disalin', 'success');"
          >
            <svg class="icon"><use href="#i-copy"></use></svg>
            <span>Salin Access Key</span>
          </button>
          <button
            type="button"
            class="btn btn-sm btn-soft"
            onclick="navigator.clipboard.writeText(@js($issuedLiveRecapUrl)); window.showToast('Disalin', 'success');"
          >
            <svg class="icon"><use href="#i-external"></use></svg>
            <span>Salin URL Halaman Rekap</span>
          </button>
        </div>
      </div>
    </div>

    <x-slot:footer>
      <x-action-button
        variant="primary"
        icon="#i-check"
        wire:click="closeAccessKeyModal"
        loadingText="Menutup..."
      >
        Saya Sudah Menyimpan Kunci Ini
      </x-action-button>
    </x-slot:footer>
  </x-modal>
@endif

{{-- Perubahan 7: Modal Konfirmasi Cabut Access Key --}}
@if($hasAccessKey)
  <x-modal
    title="Cabut Access Key Rekap Real-Time?"
    subtitle="Seluruh sesi pemantauan real-time yang sedang menggunakan kunci ini akan langsung terputus."
    icon="#i-alert"
    iconVariant="danger"
    maxWidth="480px"
    alpineShow="$wire.showRevokeAccessKeyModal"
    alpineClose="$wire.showRevokeAccessKeyModal = false"
  >
    <div class="modal-body">
      <p class="confirm-message">
        Setelah dicabut, halaman rekapitulasi real-time tidak dapat diakses lagi sampai Anda menerbitkan kunci baru.
      </p>
    </div>

    <x-slot:footer>
      <button type="button" class="btn btn-secondary" x-on:click="$wire.showRevokeAccessKeyModal = false">Batal</button>
      <x-action-button
        variant="danger"
        icon="#i-x"
        wire:click="confirmRevokeAccessKey"
        loadingText="Mencabut..."
      >
        Ya, Cabut Kunci
      </x-action-button>
    </x-slot:footer>
  </x-modal>
@endif

{{-- Perubahan 6: Modal Konfirmasi Hapus Pemilihan Permanen (Wajib Ketik Nama Pemilihan) --}}
@if($showDeleteElectionModal)
  <x-modal
    title="Hapus Pemilihan Secara Permanen?"
    :subtitle="'Target: <strong>' . e($election->name) . '</strong>'"
    icon="#i-trash"
    iconVariant="danger"
    maxWidth="540px"
    closeAction="closeDeleteElectionModal"
  >
    <form wire:submit="confirmDeleteElection" x-data="{ expectedName: @js($election->name) }" novalidate>
      <div class="modal-body">
        <div class="alert alert-danger" role="alert">
          <svg class="icon"><use href="#i-alert"></use></svg>
          <div>
            <strong>Penghapusan bersifat permanen dan tidak dapat dibatalkan</strong>
            <p>
              Seluruh data terkait pemilihan ini — termasuk pasangan calon beserta berkas fotonya, daftar hak pilih, token QR, suara tersimpan, riwayat perangkat, dan log audit pemilihan ini — akan dihapus secara total.
            </p>
          </div>
        </div>

        <div class="form-field" style="margin-top: 14px;">
          <label for="detail-del-confirm" class="field-label">
            Ketik nama pemilihan <code>{{ $election->name }}</code> untuk mengonfirmasi <span class="req">*</span>
          </label>
          <input
            id="detail-del-confirm"
            type="text"
            wire:model="deleteConfirmName"
            class="field-input @error('deleteConfirmName') is-invalid @enderror"
            placeholder="{{ $election->name }}"
            autocomplete="off"
          />
          @error('deleteConfirmName')
            <div class="field-error" role="alert">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>{{ $message }}</span>
            </div>
          @enderror
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" wire:click="closeDeleteElectionModal">Batal</button>
        <x-action-button
          type="submit"
          variant="danger"
          target="confirmDeleteElection"
          icon="#i-trash"
          loadingText="Menghapus..."
          x-bind:disabled="$wire.deleteConfirmName.trim() !== expectedName.trim()"
        >
          Hapus Permanen
        </x-action-button>
      </div>
    </form>
  </x-modal>
@endif
