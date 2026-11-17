{{-- UI-004, UI-005, FR-005, FR-006, FR-007, BR-021: Halaman Admin Kelola Akun Operator --}}
<div class="container page view-enter">
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-shield"></use></svg>
        <span>ADMINISTRASI AKUN STAF</span>
      </div>
      <h1>Daftar Akun Operator</h1>
      <p class="lede">
        Kelola akun Operator pemilihan. Admin hanya berwenang mengelola akun Operator dan meninjau Log Audit, tanpa akses ke data pemilihan maupun suara.
      </p>
    </div>
    <div class="heading-actions">
      <x-action-button
        variant="primary"
        icon="#i-plus"
        wire:click="openCreateModal"
        loadingText="Membuka..."
      >
        Buat Akun Operator
      </x-action-button>
    </div>
  </div>

  <div class="stat-grid">
    <x-stat-card
      icon="#i-users"
      cap="Total"
      :value="$totalOperators"
      label="Akun Operator"
      hint="Terdaftar di sistem"
    />
    <x-stat-card
      icon="#i-user-check"
      cap="Aktif"
      :value="$activeOperators"
      label="Operator Aktif"
      hint="Dapat masuk & bertugas"
      variant="green"
    />
    <x-stat-card
      icon="#i-clock"
      cap="Aktivasi"
      :value="$pendingPasswordChange"
      label="Wajib Ganti Sandi"
      hint="Menggunakan sandi sementara"
      variant="amber"
    />
    <x-stat-card
      icon="#i-lock"
      cap="Nonaktif"
      :value="$inactiveOperators"
      label="Operator Nonaktif"
      hint="Akses dicabut sementara"
      variant="violet"
    />
  </div>

  <section class="panel" aria-label="Tabel Akun Operator">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-users"></use></svg>
          <span>Akun Operator Terdaftar</span>
        </h2>
        {{-- Amandemen PRD §1: Operator masuk menggunakan username dan kata sandi saja --}}
        <p class="panel-sub">Operator masuk menggunakan username dan kata sandi yang kuat tanpa autentikasi dua faktor.</p>
      </div>

      <div class="voter-tools">
        <div class="search-field">
          <svg class="icon"><use href="#i-search"></use></svg>
          <input
            type="search"
            wire:model.live.debounce.250ms="search"
            class="field-input"
            placeholder="Cari username atau nama..."
            aria-label="Cari akun Operator"
          />
        </div>

        <div class="filter-row" role="tablist" aria-label="Filter status akun">
          <button type="button" class="filter-pill {{ $filterStatus === 'all' ? 'is-active' : '' }}" wire:click="$set('filterStatus', 'all')">
            <span>Semua</span>
            <span class="count">{{ $totalOperators }}</span>
          </button>
          <button type="button" class="filter-pill {{ $filterStatus === 'active' ? 'is-active' : '' }}" wire:click="$set('filterStatus', 'active')">
            <span>Aktif</span>
            <span class="count">{{ $activeOperators }}</span>
          </button>
          <button type="button" class="filter-pill {{ $filterStatus === 'inactive' ? 'is-active' : '' }}" wire:click="$set('filterStatus', 'inactive')">
            <span>Nonaktif</span>
            <span class="count">{{ $inactiveOperators }}</span>
          </button>
        </div>
      </div>
    </div>

    @if($operators->isEmpty())
      <div class="empty-state">
        <div class="empty-icon"><svg class="icon"><use href="#i-users"></use></svg></div>
        <h3>Belum ada Operator</h3>
        <p>Tambahkan akun Operator pertama agar panitia dapat mulai menyiapkan pemilihan.</p>
      </div>
    @else
      <div class="data-table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Operator</th>
              <th>Username</th>
              <th>Status Akun</th>
              <th>Status Kata Sandi</th>
              <th>Dibuat</th>
              <th style="text-align: right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($operators as $op)
              <tr wire:key="op-{{ $op->id }}">
                <td>
                  <div class="voter-identity">
                    <span class="avatar">{{ $op->initials() }}</span>
                    <div class="voter-id-copy">
                      <div class="voter-name">{{ $op->full_name }}</div>
                      <div class="voter-meta">ID: {{ substr($op->id, 0, 8) }}</div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="code-pill">{{ '@' . $op->username }}</span>
                </td>
                <td>
                  @if($op->is_active)
                    <span class="status-badge is-sudah">
                      <span class="status-dot"></span>
                      <span>Aktif</span>
                    </span>
                  @else
                    <span class="status-badge is-belum">
                      <span class="status-dot"></span>
                      <span>Nonaktif</span>
                    </span>
                  @endif
                </td>
                <td>
                  @if($op->must_change_password)
                    <span class="status-badge is-sedang">
                      <span class="status-dot"></span>
                      <span>Sandi Sementara</span>
                    </span>
                  @else
                    <span class="status-badge is-sudah">
                      <span class="status-dot"></span>
                      <span>Sudah Diganti</span>
                    </span>
                  @endif
                </td>
                <td>
                  <div class="time-cell">
                    {{ $op->created_at?->setTimezone(config('evoting.display_timezone', 'Asia/Makassar'))->format('d M Y, H:i') }}
                  </div>
                </td>
                <td>
                  <div class="row-actions">
                    <x-action-button
                      variant="secondary"
                      size="xs"
                      icon="#i-rotate"
                      wire:click="promptResetPassword('{{ $op->id }}')"
                      loadingText="Memuat..."
                    >
                      Reset Kata Sandi
                    </x-action-button>

                    @if($op->is_active)
                      <x-action-button
                        variant="danger-ghost"
                        size="xs"
                        wire:click="promptToggleActive('{{ $op->id }}')"
                        loadingText="Memuat..."
                      >
                        Nonaktifkan
                      </x-action-button>
                    @else
                      <x-action-button
                        variant="soft"
                        size="xs"
                        wire:click="promptToggleActive('{{ $op->id }}')"
                        loadingText="Memuat..."
                      >
                        Aktifkan
                      </x-action-button>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <div class="panel-foot">
      <span>Menampilkan <strong>{{ $operators->count() }}</strong> dari <strong>{{ $totalOperators }}</strong> akun Operator</span>
      <span>Seluruh perubahan akun tercatat otomatis di Log Audit</span>
    </div>
  </section>

  {{-- UI-005, FR-005: Modal Buat Akun Operator --}}
  @if($showCreateModal)
    <x-modal
      title="Buat Akun Operator Baru"
      subtitle="Sistem akan menghasilkan kata sandi sementara yang hanya ditampilkan satu kali."
      icon="#i-plus"
      closeAction="closeCreateModal"
    >
      <form wire:submit="createOperator" novalidate>
        <div class="modal-body">
          <div class="form-field">
            <label for="new-op-username" class="field-label">Username Operator <span class="req">*</span></label>
            <input
              id="new-op-username"
              type="text"
              wire:model="newUsername"
              class="field-input @error('newUsername') is-invalid @enderror"
              placeholder="Contoh: operator.bilik1"
              autofocus
            />
            <p class="field-hint">3–40 karakter (huruf, angka, titik, garis bawah, atau tanda hubung).</p>
            @error('newUsername')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>

          <div class="form-field">
            <label for="new-op-fullname" class="field-label">Nama Lengkap Petugas <span class="req">*</span></label>
            <input
              id="new-op-fullname"
              type="text"
              wire:model="newFullName"
              class="field-input @error('newFullName') is-invalid @enderror"
              placeholder="Contoh: Budi Santoso"
            />
            @error('newFullName')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" wire:click="closeCreateModal">Batal</button>
          <x-action-button
            type="submit"
            variant="primary"
            target="createOperator"
            icon="#i-check"
            loadingText="Membuat akun..."
          >
            Buat Akun
          </x-action-button>
        </div>
      </form>
    </x-modal>
  @endif

  {{-- UI-005, FR-005, FR-007: Modal Kata Sandi Sementara (Sekali Tampil) --}}
  @if($showCredentialModal)
    <x-modal
      :title="$credentialContext === 'create' ? 'Akun Operator Berhasil Dibuat' : 'Kata Sandi Operator Direset'"
      subtitle="Salin dan serahkan kredensial berikut kepada petugas Operator yang bersangkutan."
      icon="#i-check-circle"
      iconVariant="success"
    >
      <div class="modal-body">
        <div class="alert alert-warning">
          <svg class="icon"><use href="#i-alert"></use></svg>
          <div>
            <strong>Hanya ditampilkan satu kali</strong>
            <p>Setelah dialog ini ditutup, kata sandi sementara tidak dapat dilihat kembali. Operator wajib menggantinya saat login pertama.</p>
          </div>
        </div>

        <dl class="detail-list" style="margin-top: 14px;">
          <div>
            <dt>Nama Lengkap</dt>
            <dd>{{ $credentialFullName }}</dd>
          </div>
          <div>
            <dt>Username</dt>
            <dd>{{ $credentialUsername }}</dd>
          </div>
        </dl>

        <div class="temp-password-box">
          <span style="font-size:0.68rem;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;color:var(--muted);">
            Kata Sandi Sementara
          </span>
          <code>{{ $credentialTempPassword }}</code>
          <div style="margin-top: 10px;">
            <button
              type="button"
              class="btn btn-sm btn-soft"
              onclick="navigator.clipboard.writeText(@js($credentialTempPassword)); window.showToast('Kata sandi sementara berhasil disalin.', 'success');"
            >
              <svg class="icon"><use href="#i-copy"></use></svg>
              <span>Salin Kata Sandi</span>
            </button>
          </div>
        </div>
      </div>

      <x-slot:footer>
        <x-action-button
          variant="primary"
          icon="#i-check"
          wire:click="closeCredentialModal"
          loadingText="Menutup..."
        >
          Saya Sudah Menyimpan Kata Sandi Ini
        </x-action-button>
      </x-slot:footer>
    </x-modal>
  @endif

  {{-- FR-006, FR-007: Modal Konfirmasi Aktif/Nonaktif & Reset Sandi --}}
  @if($showConfirmModal)
    <x-modal
      :title="$confirmActionType === 'deactivate' ? 'Nonaktifkan Akun Operator?' : ($confirmActionType === 'activate' ? 'Aktifkan Kembali Akun Operator?' : 'Reset Kata Sandi Operator?')"
      :subtitle="'Target: <strong>' . e($targetOperatorName) . '</strong>'"
      icon="#i-alert"
      :iconVariant="$confirmActionType === 'deactivate' ? 'danger' : 'warning'"
      maxWidth="500px"
      closeAction="closeConfirmModal"
    >
      <div class="modal-body">
        @if($confirmActionType === 'deactivate')
          <p class="confirm-message">
            Akun yang dinonaktifkan tidak dapat masuk ke sistem dan <strong>seluruh sesi aktifnya akan langsung diakhiri</strong>. Jejak audit akun tetap tersimpan utuh.
          </p>
        @elseif($confirmActionType === 'activate')
          <p class="confirm-message">
            Operator ini akan kembali diizinkan masuk dan mengelola pemilihan menggunakan kredensialnya.
          </p>
        @else
          <p class="confirm-message">
            Sistem akan menerbitkan kata sandi sementara baru (ditampilkan sekali), mengakhiri sesi aktif Operator saat ini, dan mewajibkan Operator mengganti kata sandi pada login berikutnya.
          </p>
        @endif
      </div>

      <x-slot:footer>
        <button type="button" class="btn btn-secondary" wire:click="closeConfirmModal">Batal</button>
        <x-action-button
          :variant="$confirmActionType === 'deactivate' ? 'danger' : 'primary'"
          wire:click="executeConfirmedAction"
          loadingText="Memproses..."
        >
          Konfirmasi
        </x-action-button>
      </x-slot:footer>
    </x-modal>
  @endif
</div>
