{{-- UI-001, FR-001, FR-003: Halaman Masuk Staf (Admin & Operator) --}}
<div class="auth-shell view-enter">
  <div class="auth-card">
    <div class="auth-head">
      <div class="confirm-icon" aria-hidden="true">
        <svg class="icon"><use href="#i-lock"></use></svg>
      </div>
      <div class="eyebrow" style="justify-content: center;">
        <svg class="icon"><use href="#i-shield"></use></svg>
        <span>PORTAL STAF PEMILIHAN</span>
      </div>
      <h1>Masuk Akun Staf</h1>
      <p>Gunakan kredensial Operator yang telah diberikan untuk mengelola pemilihan.</p>
    </div>

    @if($errorMessage)
      <div class="alert alert-danger" role="alert" style="margin-bottom: 16px;">
        <svg class="icon"><use href="#i-alert"></use></svg>
        <div>
          <strong>Gagal masuk</strong>
          <p>{{ $errorMessage }}</p>
        </div>
      </div>
    @endif

    <form wire:submit="login" novalidate>
      <div class="form-field">
        <label for="login-username" class="field-label">Username <span class="req">*</span></label>
        <input
          id="login-username"
          type="text"
          wire:model="username"
          class="field-input @error('username') is-invalid @enderror"
          placeholder="Contoh: operator1"
          autocomplete="username"
          autofocus
        />
        @error('username')
          <div class="field-error" role="alert">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <span>{{ $message }}</span>
          </div>
        @enderror
      </div>

      <div class="form-field">
        <label for="login-password" class="field-label">Kata Sandi <span class="req">*</span></label>
        <input
          id="login-password"
          type="password"
          wire:model="password"
          class="field-input @error('password') is-invalid @enderror"
          placeholder="Masukkan kata sandi"
          autocomplete="current-password"
        />
        @error('password')
          <div class="field-error" role="alert">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <span>{{ $message }}</span>
          </div>
        @enderror
      </div>

      <div style="margin-top: 22px;">
        <x-action-button
          type="submit"
          variant="primary"
          size="lg"
          :block="true"
          target="login"
          iconRight="#i-arrow-right"
          loadingText="Memverifikasi kredensial..."
        >
          Masuk
        </x-action-button>
      </div>
    </form>

    {{-- BR-002: Pemilih tidak memiliki akun permanen --}}
    <div class="privacy-callout" style="margin-top: 20px;">
      <svg class="icon"><use href="#i-info"></use></svg>
      <div>
        Pemilih tidak memerlukan akun atau kata sandi. Jika Anda adalah pemilih, pindai kode QR yang diberikan oleh Operator di bilik verifikasi.
      </div>
    </div>
  </div>
</div>
