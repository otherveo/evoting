{{-- Perubahan 7: Halaman Khusus Rekapitulasi Real-Time dengan Access Key 64 Karakter (Read-Only, Noindex) --}}
<div class="container page view-enter" @if($isAuthorized) wire:poll.3s.visible @endif>
  {{-- Banner Peringatan Keras Berwarna Merah (Wajib Selalu Terlihat) --}}
  <div class="live-recap-danger-banner" role="alert">
    <svg class="icon"><use href="#i-alert"></use></svg>
    <div>
      <strong>PERINGATAN: Halaman ini menampilkan hasil sementara secara real-time. Buka halaman ini TERPISAH dari area pemilihan — jangan tampilkan di tempat pemilih dapat melihatnya, demi menjaga asas demokrasi yang bebas.</strong>
    </div>
  </div>

  @if(!$isAuthorized)
    <div class="auth-shell" style="min-height: auto; padding-top: 18px;">
      <div class="auth-card auth-card-wide">
        <div class="auth-head">
          <div class="confirm-icon" aria-hidden="true">
            <svg class="icon"><use href="#i-lock"></use></svg>
          </div>
          <div class="eyebrow" style="justify-content: center;">
            <svg class="icon"><use href="#i-shield"></use></svg>
            <span>AKSES REKAPITULASI REAL-TIME TERBATAS</span>
          </div>
          <h1>{{ $election->name }}</h1>
          <p>Masukkan Access Key 64 karakter yang diterbitkan oleh Operator pemilihan untuk membuka pemantauan suara secara real-time.</p>
        </div>

        <form wire:submit="verifyAccessKey" novalidate>
          <div class="form-field" x-data="{ showKey: false }">
            <label for="live-recap-key" class="field-label">Access Key (64 Karakter) <span class="req">*</span></label>
            <div class="password-field-wrap">
              <input
                id="live-recap-key"
                type="password"
                :type="showKey ? 'text' : 'password'"
                wire:model="accessKeyInput"
                class="field-input has-pw-toggle @error('accessKeyInput') is-invalid @enderror"
                placeholder="Tempel 64 karakter access key..."
                autocomplete="off"
                autofocus
              />
              <button
                type="button"
                class="password-toggle-btn"
                x-on:click="showKey = !showKey"
                :aria-label="showKey ? 'Sembunyikan kunci' : 'Tampilkan kunci'"
              >
                <svg class="icon" x-show="!showKey"><use href="#i-eye"></use></svg>
                <svg class="icon" x-show="showKey" x-cloak><use href="#i-eye-off"></use></svg>
              </button>
            </div>
            @error('accessKeyInput')
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
              size="lg"
              :block="true"
              target="verifyAccessKey"
              icon="#i-check-circle"
              loadingText="Memverifikasi kunci..."
            >
              Buka Rekap Real-Time
            </x-action-button>
          </div>
        </form>
      </div>
    </div>
  @else
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 16px;">
      <span class="public-updated" style="margin: 0;">
        <span class="snapshot-dot"></span>
        <span>Diperbarui otomatis tiap 3 detik · Posisi data: <strong>{{ $loadedAtLabel }}</strong> · Fase: <strong>{{ \App\Models\Election::phaseLabel($phase) }}</strong></span>
      </span>

      <x-action-button
        variant="secondary"
        size="sm"
        icon="#i-lock"
        wire:click="lockRecapSession"
        loadingText="Mengunci..."
      >
        Kunci Sesi Pantau
      </x-action-button>
    </div>

    <x-results-breakdown
      :election="$election"
      :resultsData="$resultsData"
      :leadingCandidates="$leadingCandidates"
      :totalVotesCast="$totalVotesCast"
      eyebrow="PEMANTAUAN REKAPITULASI REAL-TIME (KHUSUS PIHAK BERWENANG)"
      subtitle="Data perolehan suara di bawah ini dihitung secara langsung dari tabel suara anonim tanpa membuka identitas pemilih."
      metaRightLabel="Hak Pilih Terdaftar"
      :metaRightValue="$integrity['total_voted'] . ' memilih dari ' . $integrity['total_rights'] . ' hak pilih'"
      :totalSubtext="'Kuota maksimum: ' . $election->max_voter_rights . ' hak pilih'"
      winnerNote="Perolehan suara sementara (real-time) dari tabel suara anonim."
    />
  @endif
</div>
