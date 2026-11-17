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
      <button type="button" class="btn btn-primary" wire:click="openCreateModal">
        <svg class="icon"><use href="#i-plus"></use></svg>
        <span>Buat Akun Operator</span>
      </button>
    </div>
  </div>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-top">
        <span class="stat-icon"><svg class="icon"><use href="#i-users"></use></svg></span>
        <span class="stat-cap">Total</span>
      </div>
      <div class="stat-value">{{ $totalOperators }}</div>
      <div class="stat-label">Akun Operator</div>
      <div class="stat-hint">Terdaftar di sistem</div>
    </div>

    <div class="stat-card stat-green">
      <div class="stat-top">
        <span class="stat-icon"><svg class="icon"><use href="#i-user-check"></use></svg></span>
        <span class="stat-cap">Aktif</span>
      </div>
      <div class="stat-value">{{ $activeOperators }}</div>
      <div class="stat-label">Operator Aktif</div>
      <div class="stat-hint">Dapat masuk &amp; bertugas</div>
    </div>

    <div class="stat-card stat-amber">
      <div class="stat-top">
        <span class="stat-icon"><svg class="icon"><use href="#i-clock"></use></svg></span>
        <span class="stat-cap">Aktivasi</span>
      </div>
      <div class="stat-value">{{ $pendingPasswordChange }}</div>
      <div class="stat-label">Wajib Ganti Sandi</div>
      <div class="stat-hint">Menggunakan sandi sementara</div>
    </div>

    <div class="stat-card stat-violet">
      <div class="stat-top">
        <span class="stat-icon"><svg class="icon"><use href="#i-lock"></use></svg></span>
        <span class="stat-cap">Nonaktif</span>
      </div>
      <div class="stat-value">{{ $inactiveOperators }}</div>
      <div class="stat-label">Operator Nonaktif</div>
      <div class="stat-hint">Akses dicabut sementara</div>
    </div>
  </div>

  <section class="panel" aria-label="Tabel Akun Operator">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-users"></use></svg>
          <span>Akun Operator Terdaftar</span>
        </h2>
        <p class="panel-sub">Sesuai Amandemen PRD, Operator masuk menggunakan username dan kata sandi (tanpa TOTP).</p>
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
                    <button
                      type="button"
                      class="btn btn-xs btn-secondary"
                      wire:click="promptResetPassword('{{ $op->id }}')"
                    >
                      <svg class="icon"><use href="#i-rotate"></use></svg>
                      <span>Reset Kata Sandi</span>
                    </button>

                    @if($op->is_active)
                      <button
                        type="button"
                        class="btn btn-xs btn-danger-ghost"
                        wire:click="promptToggleActive('{{ $op->id }}')"
                      >
                        <span>Nonaktifkan</span>
                      </button>
                    @else
                      <button
                        type="button"
                        class="btn btn-xs btn-soft"
                        wire:click="promptToggleActive('{{ $op->id }}')"
                      >
                        <span>Aktifkan</span>
                      </button>
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

  {{-- UI-005: Modal Buat Akun Operator --}}
  @if($showCreateModal)
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true" aria-labelledby="modal-create-op-title">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon"><svg class="icon"><use href="#i-plus"></use></svg></span>
            <div>
              <h2 id="modal-create-op-title">Buat Akun Operator Baru</h2>
              <p>Sistem akan menghasilkan kata sandi sementara yang hanya ditampilkan satu kali.</p>
            </div>
          </div>
          <button type="button" class="icon-btn" wire:click="closeCreateModal" aria-label="Tutup dialog">
            <svg class="icon"><use href="#i-x"></use></svg>
          </button>
        </div>

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
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
              <span wire:loading.remove wire:target="createOperator">Buat Akun</span>
              <span wire:loading wire:target="createOperator">Membuat...</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif

  {{-- Modal Kata Sandi Sementara (Sekali Tampil) --}}
  @if($showCredentialModal)
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true" aria-labelledby="modal-cred-title">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon" style="color:var(--green);background:var(--green-soft);">
              <svg class="icon"><use href="#i-check-circle"></use></svg>
            </span>
            <div>
              <h2 id="modal-cred-title">
                {{ $credentialContext === 'create' ? 'Akun Operator Berhasil Dibuat' : 'Kata Sandi Operator Direset' }}
              </h2>
              <p>Salin dan serahkan kredensial berikut kepada petugas Operator yang bersangkutan.</p>
            </div>
          </div>
        </div>

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
                onclick="navigator.clipboard.writeText('{{ $credentialTempPassword }}'); window.showToast('Kata sandi sementara berhasil disalin.', 'success');"
              >
                <svg class="icon"><use href="#i-copy"></use></svg>
                <span>Salin Kata Sandi</span>
              </button>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-primary" wire:click="closeCredentialModal">
            Saya Sudah Menyimpan Kata Sandi Ini
          </button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modal Konfirmasi Aktif/Nonaktif & Reset Sandi --}}
  @if($showConfirmModal)
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true">
      <div class="modal" style="--modal-width: 500px;">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon" style="{{ $confirmActionType === 'deactivate' ? 'color:var(--red);background:var(--red-soft);' : '' }}">
              <svg class="icon"><use href="#i-alert"></use></svg>
            </span>
            <div>
              <h2>
                @if($confirmActionType === 'deactivate')
                  Nonaktifkan Akun Operator?
                @elseif($confirmActionType === 'activate')
                  Aktifkan Kembali Akun Operator?
                @else
                  Reset Kata Sandi Operator?
                @endif
              </h2>
              <p>Target: <strong>{{ $targetOperatorName }}</strong></p>
            </div>
          </div>
          <button type="button" class="icon-btn" wire:click="closeConfirmModal" aria-label="Tutup">
            <svg class="icon"><use href="#i-x"></use></svg>
          </button>
        </div>

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

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" wire:click="closeConfirmModal">Batal</button>
          <button
            type="button"
            class="btn {{ $confirmActionType === 'deactivate' ? 'btn-danger' : 'btn-primary' }}"
            wire:click="executeConfirmedAction"
          >
            Konfirmasi
          </button>
        </div>
      </div>
    </div>
  @endif
</div>
