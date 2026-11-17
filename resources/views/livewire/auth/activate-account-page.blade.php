{{-- UI-003, FR-002, Amandemen PRD §2.8: Aktivasi akun pertama (Admin: Sandi + TOTP; Operator: Sandi saja) --}}
<div class="auth-shell view-enter">
  <div class="auth-card {{ $user->isAdmin() ? 'auth-card-wide' : '' }}">
    <div class="auth-head">
      <div class="confirm-icon" aria-hidden="true">
        <svg class="icon"><use href="#i-shield"></use></svg>
      </div>
      <div class="eyebrow" style="justify-content: center;">
        <svg class="icon"><use href="#i-lock"></use></svg>
        <span>AKTIVASI AKUN WAJIB</span>
      </div>
      <h1>Aktivasi Akun {{ $user->isAdmin() ? 'Admin' : 'Operator' }}</h1>
      <p>
        @if($user->isAdmin())
          Demi keamanan pengelolaan akun, ganti kata sandi sementara Anda dan aktifkan autentikasi dua faktor (TOTP) sebelum mengakses sistem.
        @else
          Ganti kata sandi sementara Anda dengan kata sandi baru yang kuat sebelum mulai mengelola pemilihan.
        @endif
      </p>
    </div>

    @if($errorMessage)
      <div class="alert alert-danger" role="alert" style="margin-bottom: 16px;">
        <svg class="icon"><use href="#i-alert"></use></svg>
        <div>
          <strong>Aktivasi belum berhasil</strong>
          <p>{{ $errorMessage }}</p>
        </div>
      </div>
    @endif

    @if($user->must_change_password)
      <div class="panel" style="padding: 20px; margin-bottom: 18px; background: var(--surface-soft);">
        <div class="section-title">
          <svg class="icon"><use href="#i-lock"></use></svg>
          <span>Langkah 1 — Ganti Kata Sandi Sementara</span>
        </div>

        <form wire:submit="updatePassword" novalidate style="margin-top: 12px;">
          <div class="form-field">
            <label for="act-current-pw" class="field-label">Kata Sandi Sementara Saat Ini <span class="req">*</span></label>
            <input
              id="act-current-pw"
              type="password"
              wire:model="currentPassword"
              class="field-input @error('currentPassword') is-invalid @enderror"
              placeholder="Masukkan kata sandi sementara"
              autocomplete="current-password"
            />
            @error('currentPassword')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>

          <div class="form-field">
            <label for="act-new-pw" class="field-label">Kata Sandi Baru (Minimal 8 Karakter) <span class="req">*</span></label>
            <input
              id="act-new-pw"
              type="password"
              wire:model="newPassword"
              class="field-input @error('newPassword') is-invalid @enderror"
              placeholder="Buat kata sandi baru yang kuat"
              autocomplete="new-password"
            />
            @error('newPassword')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>

          <div class="form-field">
            <label for="act-confirm-pw" class="field-label">Ulangi Kata Sandi Baru <span class="req">*</span></label>
            <input
              id="act-confirm-pw"
              type="password"
              wire:model="newPasswordConfirmation"
              class="field-input @error('newPasswordConfirmation') is-invalid @enderror"
              placeholder="Ketik ulang kata sandi baru"
              autocomplete="new-password"
            />
            @error('newPasswordConfirmation')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>

          <div style="margin-top: 18px;">
            <x-action-button
              type="submit"
              variant="primary"
              :block="true"
              target="updatePassword"
              icon="#i-check"
              loadingText="Menyimpan kata sandi..."
            >
              Simpan Kata Sandi Baru
            </x-action-button>
          </div>
        </form>
      </div>
    @else
      <div class="alert alert-success" style="margin-bottom: 18px;">
        <svg class="icon"><use href="#i-check-circle"></use></svg>
        <div>
          <strong>Langkah 1 selesai</strong>
          <p>Kata sandi sementara telah berhasil diganti.</p>
        </div>
      </div>
    @endif

    @if($user->isAdmin() && !$user->totp_enabled)
      <div class="panel" style="padding: 20px; background: var(--surface-soft);">
        <div class="section-title">
          <svg class="icon"><use href="#i-fingerprint"></use></svg>
          <span>Langkah 2 — Aktifkan Autentikasi Dua Faktor (TOTP) Admin</span>
        </div>
        <p class="field-hint" style="margin-top: 4px;">
          Pindai kode QR di bawah menggunakan aplikasi autentikator (Google Authenticator, Ente Auth, Authy, atau sejenisnya), atau salin kunci rahasia manual.
        </p>

        <div class="qr-layout" style="margin-top: 16px;">
          <div class="qr-card">
            <div class="qr-frame qr-svg-wrap">
              {!! $qrSvg !!}
            </div>
            <span class="qr-hint">
              <svg class="icon"><use href="#i-qr"></use></svg>
              <span>Pindai di Autentikator</span>
            </span>
          </div>

          <div class="qr-details">
            <x-copy-link-box
              label="KUNCI RAHASIA MANUAL (BASE32)"
              :url="$pendingTotpSecret"
              copyToast="Kunci TOTP disalin ke papan klip."
              :showOpenLink="false"
              style="margin-top: 0;"
            />

            <form wire:submit="confirmTotp" novalidate style="margin-top: 16px;">
              <div class="form-field">
                <label for="act-totp-code" class="field-label">Kode Konfirmasi 6 Digit <span class="req">*</span></label>
                <input
                  id="act-totp-code"
                  type="text"
                  inputmode="numeric"
                  maxlength="6"
                  wire:model="totpCode"
                  class="field-input @error('totpCode') is-invalid @enderror"
                  placeholder="Contoh: 123456"
                  style="font-weight: 800; letter-spacing: 0.16em;"
                  {{ $user->must_change_password ? 'disabled' : '' }}
                />
                @error('totpCode')
                  <div class="field-error" role="alert">
                    <svg class="icon"><use href="#i-alert"></use></svg>
                    <span>{{ $message }}</span>
                  </div>
                @enderror
              </div>

              <div style="margin-top: 14px;">
                <x-action-button
                  type="submit"
                  variant="primary"
                  :block="true"
                  target="confirmTotp"
                  icon="#i-check-circle"
                  loadingText="Memverifikasi TOTP..."
                  :disabled="$user->must_change_password"
                >
                  Konfirmasi &amp; Aktifkan TOTP
                </x-action-button>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endif
  </div>
</div>
