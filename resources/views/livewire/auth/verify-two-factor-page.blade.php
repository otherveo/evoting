<div class="auth-shell view-enter">
  <div class="auth-card">
    <div class="auth-head">
      <div class="confirm-icon" aria-hidden="true">
        <svg class="icon"><use href="#i-fingerprint"></use></svg>
      </div>
      <div class="eyebrow" style="justify-content: center;">
        <svg class="icon"><use href="#i-shield"></use></svg>
        <span>VERIFIKASI DUA FAKTOR (ADMIN)</span>
      </div>
      <h1>Masukkan Kode TOTP</h1>
      <p>Buka aplikasi autentikator Anda dan masukkan kode 6 digit sekali pakai untuk melanjutkan ke panel Admin.</p>
    </div>

    @if($errorMessage)
      <div class="alert alert-danger" role="alert" style="margin-bottom: 16px;">
        <svg class="icon"><use href="#i-alert"></use></svg>
        <div>
          <strong>Verifikasi gagal</strong>
          <p>{{ $errorMessage }}</p>
        </div>
      </div>
    @endif

    <form wire:submit="verify" novalidate>
      <div class="form-field">
        <label for="totp-code" class="field-label">Kode Autentikator (6 Digit) <span class="req">*</span></label>
        <input
          id="totp-code"
          type="text"
          inputmode="numeric"
          maxlength="6"
          wire:model="code"
          class="field-input @error('code') is-invalid @enderror"
          placeholder="000000"
          autocomplete="one-time-code"
          style="font-size: 1.25rem; font-weight: 800; letter-spacing: 0.22em; text-align: center;"
          autofocus
        />
        @error('code')
          <div class="field-error" role="alert">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <span>{{ $message }}</span>
          </div>
        @enderror
      </div>

      <div style="margin-top: 22px; display: grid; gap: 10px;">
        <button type="submit" class="btn btn-primary btn-lg btn-block" wire:loading.attr="disabled">
          <span wire:loading.remove wire:target="verify" style="display:inline-flex;align-items:center;gap:8px;">
            <svg class="icon"><use href="#i-check-circle"></use></svg>
            <span>Verifikasi &amp; Masuk</span>
          </span>
          <span wire:loading wire:target="verify" style="display:inline-flex;align-items:center;gap:8px;">
            <span class="spinner" aria-hidden="true"></span>
            <span>Memeriksa kode TOTP...</span>
          </span>
        </button>
      </div>
    </form>
  </div>
</div>
