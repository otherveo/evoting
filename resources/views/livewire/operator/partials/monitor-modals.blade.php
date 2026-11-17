{{-- UI-010, FR-012, FR-013, BR-003, BR-004, AC-014, AC-015: Modal Registrasi Pemilih --}}
@if($showRegisterModal)
  <x-modal
    title="Registrasi Pemilih & Terbitkan QR"
    :subtitle="'Sisa kuota hak pilih: <strong>' . max(0, $election->max_voter_rights - $totalRights) . '</strong> dari ' . $election->max_voter_rights . '.'"
    icon="#i-user-check"
    closeAction="closeRegisterModal"
  >
    <form wire:submit="registerVoter" novalidate>
      <div class="modal-body">
        <div class="form-field">
          <label for="reg-voter-name" class="field-label">Nama Lengkap Pemilih (Terverifikasi) <span class="req">*</span></label>
          <input
            id="reg-voter-name"
            type="text"
            wire:model.live.debounce.250ms="voterName"
            class="field-input @error('voterName') is-invalid @enderror"
            placeholder="Masukkan nama lengkap sesuai identitas..."
            autocomplete="off"
            autofocus
          />
          <p class="field-hint">Nama akan dikunci setelah hak pilih diterbitkan dan tidak ditampilkan di layar pemilih.</p>
          @error('voterName')
            <div class="field-error" role="alert">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>{{ $message }}</span>
            </div>
          @enderror
        </div>

        {{-- FR-013, BR-004, AC-015: Peringatan Nama Kembar & konfirmasi eksplisit orang berbeda --}}
        @if(!empty($duplicateMatches))
          <div class="inline-alert inline-alert-warning" role="alert">
            <strong>
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>Terdeteksi Nama Kembar ({{ count($duplicateMatches) }} Hak Pilih terdaftar)</span>
            </strong>
            <p>
              Nama ini sudah terdaftar pada pemilihan ini. Pastikan pemilih di hadapan Anda adalah <strong>orang yang berbeda</strong> (bukan pemilih yang sama meminta QR kedua):
            </p>
            <ul class="confirm-list" style="margin: 8px 0;">
              @foreach($duplicateMatches as $dup)
                <li>
                  <strong>{{ $dup['name'] }}</strong> — Status: <code>{{ $dup['status'] }}</code> (terdaftar pukul {{ $dup['created_at'] }})
                </li>
              @endforeach
            </ul>
            <label class="check-row">
              <input type="checkbox" wire:model.live="confirmDifferentPerson" />
              <span>Saya telah memverifikasi identitas secara langsung dan menyatakan bahwa pemilih ini adalah <strong>orang yang berbeda</strong> dari daftar di atas.</span>
            </label>
          </div>
        @endif
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" wire:click="closeRegisterModal">Batal</button>
        <x-action-button
          type="submit"
          variant="primary"
          target="registerVoter"
          icon="#i-qr"
          loadingText="Menerbitkan Token QR..."
          :disabled="!empty($duplicateMatches) && !$confirmDifferentPerson"
        >
          Buat Hak Pilih &amp; Tampilkan QR
        </x-action-button>
      </div>
    </form>
  </x-modal>
@endif

{{-- UI-011, FR-014, FR-029, AC-016: Modal Panel QR Sekali Tampil --}}
@if($showQrModal)
  <x-modal
    :title="$issuedIsReissue ? 'Kode QR Baru Diterbitkan Ulang' : 'Kode QR Hak Pilih Diterbitkan'"
    subtitle="Tunjukkan kode QR ini kepada pemilih atau salin tautan akses untuk perangkat tanpa kamera."
    icon="#i-qr"
    maxWidth="620px"
  >
    <div class="modal-body">
      {{-- FR-014, NFR-002: QR & tautan token hanya ditampilkan sekali pada saat penerbitan --}}
      <div class="qr-success-note">
        <svg class="icon"><use href="#i-alert"></use></svg>
        <div>
          <strong>Peringatan Keamanan: QR &amp; Tautan Hanya Tampil Sekali Ini Saja</strong><br />
          Server hanya menyimpan hash satu arah (SHA-256) dari token ini. Setelah Anda menekan <strong>Selesai</strong>, kode QR ini tidak dapat dibuka kembali.
        </div>
      </div>

      <div class="qr-layout">
        <div class="qr-card">
          <div class="qr-frame qr-svg-wrap">
            {!! $issuedQrSvg !!}
          </div>
          <span class="qr-hint">
            <svg class="icon"><use href="#i-qr"></use></svg>
            <span>Pindai dengan Kamera / Pembaca QR</span>
          </span>
        </div>

        <div class="qr-details">
          <div class="voter-mini">
            <span class="avatar">{{ mb_strtoupper(mb_substr($issuedVoterName, 0, 2)) }}</span>
            <div>
              <div class="voter-name">{{ $issuedVoterName }}</div>
              <div class="voter-meta">ID Hak Pilih: #{{ strtoupper(substr($issuedVoterId, 0, 8)) }}</div>
            </div>
          </div>

          <dl class="detail-list">
            <div>
              <dt>Pemilihan</dt>
              <dd>{{ $election->name }}</dd>
            </div>
            <div>
              <dt>Status Awal</dt>
              <dd>Belum memilih</dd>
            </div>
            <div>
              <dt>Batas Sesi</dt>
              <dd>10 menit sejak Mulai memilih</dd>
            </div>
          </dl>

          <x-copy-link-box
            label="TAUTAN AKSES PEMILIH (TANPA IDENTITAS/NAMA)"
            :url="$issuedVotingUrl"
            copyToast="Tautan voting berhasil disalin ke papan klip."
          />
        </div>
      </div>
    </div>

    <x-slot:footer>
      <button type="button" class="btn btn-secondary" onclick="window.print()">
        <span>Cetak QR</span>
      </button>
      <x-action-button
        variant="primary"
        icon="#i-check"
        wire:click="closeQrModal"
        loadingText="Menutup..."
      >
        Selesai (Tutup QR)
      </x-action-button>
    </x-slot:footer>
  </x-modal>
@endif

{{-- UI-012, FR-024, FR-025, FR-029, FR-030, WF-006, WF-007: Modal Detail Hak Pilih, Riwayat Perangkat, Reset Sesi, & Terbitkan Ulang QR --}}
@if($showDetailModal && $detailData)
  @php
    $dRow = $detailData['row'];
    $dUpdated = \Carbon\CarbonImmutable::parse($dRow->updated_at)->setTimezone($tz);
    $dStarted = $dRow->session_started_at ? \Carbon\CarbonImmutable::parse($dRow->session_started_at)->setTimezone($tz) : null;
  @endphp
  <x-modal
    title="Detail Hak Pilih & Penanganan Kendala"
    :subtitle="'ID Hak Pilih: #' . strtoupper(substr($dRow->id, 0, 12))"
    icon="#i-users"
    maxWidth="620px"
    closeAction="closeDetailModal"
  >
    <div class="modal-body">
      <div class="detail-hero">
        <span class="avatar avatar-lg">{{ mb_strtoupper(mb_substr($dRow->name, 0, 2)) }}</span>
        <div>
          <h3>{{ $dRow->name }}</h3>
          <p>Perubahan status terakhir: {{ $dUpdated->format('d M Y, H:i:s') }} ({{ $tz }})</p>
          <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
            <span class="status-badge {{ \App\Models\VoterRight::statusBadgeClass($dRow->status) }}">
              <span class="status-dot"></span>
              <span>{{ \App\Models\VoterRight::statusLabel($dRow->status) }}</span>
            </span>
            @if($dRow->session_expired)
              <span class="status-badge" style="color:var(--red);background:var(--red-soft);border:1px solid #f4cfcc;">
                <span class="status-dot"></span>
                <span>Sesi Kedaluwarsa (&gt; 10 Menit)</span>
              </span>
            @endif
            @if($dRow->device_warning)
              <span class="status-badge is-sedang">
                <span class="status-dot"></span>
                <span>Peringatan Perangkat</span>
              </span>
            @endif
          </div>
        </div>
      </div>

      <div class="meta-grid">
        <div class="meta-item">
          <span>Mulai Sesi Memilih</span>
          <strong>{{ $dStarted ? $dStarted->format('H:i:s') : 'Belum ada sesi aktif' }}</strong>
        </div>
        <div class="meta-item">
          <span>Batas Waktu Sesi (10 Menit)</span>
          <strong>{{ $detailData['session_expires_at'] ? $detailData['session_expires_at']->format('H:i:s') : '—' }}</strong>
        </div>
      </div>

      {{-- FR-025, BR-015, BR-029, AC-028: Peringatan Perangkat Bersama --}}
      @if(!empty($detailData['shared_rights']))
        <div class="alert alert-warning" style="margin-bottom: 16px;">
          <svg class="icon"><use href="#i-alert"></use></svg>
          <div>
            <strong>Peringatan Perangkat Bersama</strong>
            <p>Pengenal Browser yang mengakses Hak Pilih ini juga tercatat pernah mengakses Hak Pilih lain pada pemilihan ini:</p>
            <ul class="confirm-list" style="margin: 6px 0 0;">
              @foreach($detailData['shared_rights'] as $sr)
                <li>
                  <strong>{{ $sr->name }}</strong> ({{ \App\Models\VoterRight::statusLabel($sr->status) }}) — Browser <code>#{{ strtoupper(substr(hash('sha256', $sr->browser_id), 0, 6)) }}</code>
                </li>
              @endforeach
            </ul>
            <p style="margin-top:4px;font-size:0.72rem;">Catatan: Penggunaan perangkat bergantian diperbolehkan selama Operator telah memverifikasi langsung identitas pemilih.</p>
          </div>
        </div>
      @endif

      {{-- FR-029, FR-030, WF-006, WF-007: Aksi Penanganan Operator (Reset Sesi / Terbitkan Ulang QR) --}}
      @if($phase === 'berlangsung')
        @if($dRow->status === 'sedang_memilih')
          <div class="panel" style="padding: 16px; margin-bottom: 16px; background: #fffaf2; border-color: #f2dfbd;">
            <div class="section-title" style="color: var(--amber);">
              <svg class="icon" style="color: var(--amber);"><use href="#i-rotate"></use></svg>
              <span>Reset Sesi Memilih</span>
            </div>
            <p class="field-hint" style="margin-top: 2px;">
              Kembalikan status dari <code>Sedang memilih</code> menjadi <code>Belum memilih</code> apabila perangkat pemilih terputus, berganti browser, atau batas 10 menit telah lewat. Untuk menerbitkan ulang QR, lakukan Reset Sesi ini terlebih dahulu.
            </p>
            <form wire:submit="resetVoterSession" style="margin-top: 10px;" novalidate>
              <div class="form-field">
                <label for="reset-reason" class="field-label">Alasan Reset Sesi <span class="req">*</span></label>
                <input
                  id="reset-reason"
                  type="text"
                  wire:model="resetReason"
                  class="field-input @error('resetReason') is-invalid @enderror"
                  placeholder="Contoh: Baterai HP pemilih habis / sesi lewat 10 menit"
                />
                @error('resetReason')
                  <div class="field-error" role="alert">
                    <svg class="icon"><use href="#i-alert"></use></svg>
                    <span>{{ $message }}</span>
                  </div>
                @enderror
              </div>
              <div style="margin-top: 10px; display: flex; justify-content: flex-end;">
                <x-action-button
                  type="submit"
                  variant="primary"
                  size="sm"
                  target="resetVoterSession"
                  icon="#i-rotate"
                  loadingText="Mereset sesi..."
                >
                  Reset Sesi ke Belum Memilih
                </x-action-button>
              </div>
            </form>
          </div>
        @elseif($dRow->status === 'belum_memilih')
          <div class="panel" style="padding: 16px; margin-bottom: 16px; background: var(--surface-soft);">
            <div class="section-title">
              <svg class="icon"><use href="#i-qr"></use></svg>
              <span>Terbitkan Ulang QR</span>
            </div>
            <p class="field-hint" style="margin-top: 2px;">
              Untuk QR yang hilang, rusak, atau diduga bocor. Token QR lama akan langsung dicabut dan diganti dengan QR baru sekali tampil.
            </p>
            <form wire:submit="reissueVoterQr" style="margin-top: 10px;" novalidate>
              <div class="form-field">
                <label for="reissue-reason" class="field-label">Alasan Penerbitan Ulang QR <span class="req">*</span></label>
                <input
                  id="reissue-reason"
                  type="text"
                  wire:model="reissueReason"
                  class="field-input @error('reissueReason') is-invalid @enderror"
                  placeholder="Contoh: Cetakan QR hilang / halaman tertutup sebelum dipindai"
                />
                @error('reissueReason')
                  <div class="field-error" role="alert">
                    <svg class="icon"><use href="#i-alert"></use></svg>
                    <span>{{ $message }}</span>
                  </div>
                @enderror
              </div>
              <div style="margin-top: 10px; display: flex; justify-content: flex-end;">
                <x-action-button
                  type="submit"
                  variant="primary"
                  size="sm"
                  target="reissueVoterQr"
                  icon="#i-qr"
                  loadingText="Menerbitkan ulang..."
                >
                  Cabut QR Lama &amp; Terbitkan QR Baru
                </x-action-button>
              </div>
            </form>
          </div>
        @else
          {{-- BR-007, WF-007: Status Sudah memilih bersifat final --}}
          <div class="alert alert-success" style="margin-bottom: 16px;">
            <svg class="icon"><use href="#i-check-circle"></use></svg>
            <div>
              <strong>Suara telah tersimpan (Status Final)</strong>
              <p>Hak pilih ini sudah digunakan. Status <code>Sudah memilih</code> bersifat final dan tidak dapat direset atau diterbitkan ulang.</p>
            </div>
          </div>
        @endif
      @endif

      {{-- FR-024, AC-027: Riwayat Akses Perangkat --}}
      <div class="section-title">
        <svg class="icon"><use href="#i-monitor"></use></svg>
        <span>Riwayat Akses Perangkat ({{ count($detailData['access_logs']) }})</span>
      </div>

      @if(empty($detailData['access_logs']))
        <p class="field-hint">Belum ada catatan akses perangkat untuk Hak Pilih ini.</p>
      @else
        <div class="device-list">
          @foreach($detailData['access_logs'] as $log)
            @php
              $logTime = \Carbon\CarbonImmutable::parse($log->accessed_at)->setTimezone($tz);
            @endphp
            <div class="device-item">
              <div class="device-item-head">
                <span class="{{ $log->access_type === 'akses_ditolak' ? 'result-denied' : '' }}">
                  {{ \App\Models\DeviceAccessLog::accessTypeLabel($log->access_type) }}
                </span>
                <span>{{ $logTime->format('d M Y, H:i:s') }}</span>
              </div>
              <div class="device-item-meta">
                <span>Browser: <strong>{{ \App\Services\TokenService::shortBrowserLabel($log->browser_id, $log->user_agent) }}</strong></span>
                <span>IP: <code>{{ $log->ip_address ?? '—' }}</code></span>
                @if($log->user_agent)
                  <span style="display:block;width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->user_agent }}">
                    UA: {{ $log->user_agent }}
                  </span>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>

    <x-slot:footer>
      <button type="button" class="btn btn-secondary" wire:click="closeDetailModal">Tutup</button>
    </x-slot:footer>
  </x-modal>
@endif
