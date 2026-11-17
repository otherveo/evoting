<div class="container page view-enter" wire:poll.3s>
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-monitor"></use></svg>
        <span>DASHBOARD MONITORING OPERATOR · PEMBARUAN OTOMATIS TIAP 3 DETIK</span>
      </div>
      <h1>{{ $election->name }}</h1>
      <p class="lede">
        Pantau status hak pilih secara langsung, daftarkan pemilih terverifikasi untuk menerbitkan QR sekali pakai, dan tangani sesi memilih yang terputus.
      </p>
    </div>

    <div class="heading-actions">
      <a href="{{ route('operator.elections.detail', $election) }}" class="btn btn-secondary">
        <svg class="icon"><use href="#i-calendar"></use></svg>
        <span>Jadwal &amp; Paslon</span>
      </a>
      <button
        type="button"
        class="btn btn-primary"
        wire:click="openRegisterModal"
        {{ $phase !== 'berlangsung' || $totalRights >= $election->max_voter_rights ? 'disabled' : '' }}
      >
        <svg class="icon"><use href="#i-qr"></use></svg>
        <span>Registrasi Pemilih &amp; Terbitkan QR</span>
      </button>
    </div>
  </div>

  {{-- Peringatan Fase atau Kuota --}}
  @if($phase !== 'berlangsung')
    <div class="page-alert">
      <div class="alert {{ $phase === 'persiapan' ? 'alert-warning' : 'alert-info' }}">
        <div class="alert-flex">
          <svg class="icon"><use href="{{ $phase === 'persiapan' ? '#i-clock' : '#i-lock' }}"></use></svg>
          <div>
            <strong>Fase Pemilihan saat ini: {{ \App\Models\Election::phaseLabel($phase) }}</strong>
            <p>
              @if($phase === 'persiapan')
                Pendaftaran hak pilih dan pemungutan suara hanya dapat dilakukan saat Fase <code>Berlangsung</code> (Waktu Mulai: {{ $election->formattedSchedule($election->starts_at) }}).
              @else
                Masa pemilihan telah berakhir pada {{ $election->formattedSchedule($election->ends_at) }}. Pendaftaran hak pilih baru dan pemungutan suara telah ditutup.
              @endif
            </p>
          </div>
        </div>
        <a href="{{ $phase === 'persiapan' ? route('operator.elections.detail', $election) : route('operator.elections.recap', $election) }}" class="btn btn-sm btn-secondary">
          <span>{{ $phase === 'persiapan' ? 'Atur Jadwal' : 'Lihat Rekapitulasi' }}</span>
        </a>
      </div>
    </div>
  @elseif($totalRights >= $election->max_voter_rights)
    <div class="page-alert">
      <div class="alert alert-warning">
        <div class="alert-flex">
          <svg class="icon"><use href="#i-alert"></use></svg>
          <div>
            <strong>Batas maksimum hak pilih tercapai ({{ $totalRights }} / {{ $election->max_voter_rights }})</strong>
            <p>Naikkan batas maksimum pada menu Konfigurasi apabila masih ada pemilih sah yang perlu didaftarkan (FR-028).</p>
          </div>
        </div>
        <a href="{{ route('operator.elections.detail', $election) }}" class="btn btn-sm btn-secondary">Ubah Batas Kuota</a>
      </div>
    </div>
  @endif

  {{-- Peringatan Sesi Kedaluwarsa --}}
  @if($countExpired > 0 && $phase === 'berlangsung')
    <div class="page-alert">
      <div class="alert alert-warning">
        <div class="alert-flex">
          <svg class="icon"><use href="#i-alert"></use></svg>
          <div>
            <strong>{{ $countExpired }} pemilih memiliki Sesi Kedaluwarsa (&gt; 10 menit)</strong>
            <p>Buka detail hak pilih terkait untuk melakukan <strong>Reset Sesi</strong> agar pemilih dapat memindai ulang QR dan menyelesaikan suaranya.</p>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-warn-ghost" wire:click="$set('statusFilter', 'attention')">
          Tampilkan yang Perlu Perhatian
        </button>
      </div>
    </div>
  @endif

  {{-- 4 Kartu Statistik Utama (UI.md §3) --}}
  @php
    $quotaPct = $election->max_voter_rights > 0
      ? min(100, round(($totalRights / $election->max_voter_rights) * 100, 1))
      : 0;
  @endphp
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-top">
        <span class="stat-icon"><svg class="icon"><use href="#i-users"></use></svg></span>
        <span class="stat-cap">Kuota {{ $totalRights }}/{{ $election->max_voter_rights }}</span>
      </div>
      <div class="stat-value">{{ $totalRights }}</div>
      <div class="stat-label">Hak Pilih Terdaftar</div>
      <div class="stat-hint">Sisa kuota {{ max(0, $election->max_voter_rights - $totalRights) }} hak pilih</div>
      <div class="progress" aria-label="Penggunaan kuota hak pilih">
        <span style="width: {{ $quotaPct }}%;"></span>
      </div>
    </div>

    <div class="stat-card stat-green">
      <div class="stat-top">
        <span class="stat-icon"><svg class="icon"><use href="#i-check-circle"></use></svg></span>
        <span class="stat-cap">Selesai</span>
      </div>
      <div class="stat-value">{{ $countSudah }}</div>
      <div class="stat-label">Sudah memilih</div>
      <div class="stat-hint">Suara anonim tersimpan sah</div>
    </div>

    <div class="stat-card stat-amber">
      <div class="stat-top">
        <span class="stat-icon"><svg class="icon"><use href="#i-clock"></use></svg></span>
        <span class="stat-cap">Sesi 10 Menit</span>
      </div>
      <div class="stat-value">{{ $countSedang }}</div>
      <div class="stat-label">Sedang memilih</div>
      <div class="stat-hint">
        @if($countExpired > 0)
          {{ $countExpired }} sesi lewat 10 menit
        @else
          Semua sesi dalam batas waktu
        @endif
      </div>
    </div>

    <div class="stat-card stat-violet">
      <div class="stat-top">
        <span class="stat-icon"><svg class="icon"><use href="#i-qr"></use></svg></span>
        <span class="stat-cap">Antrean QR</span>
      </div>
      <div class="stat-value">{{ $countBelum }}</div>
      <div class="stat-label">Belum memilih</div>
      <div class="stat-hint">QR diterbitkan, belum mulai</div>
    </div>
  </div>

  {{-- Tabel Monitoring Aktivitas Pemilih (UI-009) --}}
  <section class="panel" aria-label="Daftar Hak Pilih">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-users"></use></svg>
          <span>Daftar Hak Pilih &amp; Status Sesi</span>
        </h2>
        <p class="panel-sub">
          Pilihan paslon dipisahkan secara struktural dari daftar ini demi menjaga kerahasiaan suara (BR-016, BR-017).
        </p>
      </div>

      <div class="voter-tools">
        <div class="search-field">
          <svg class="icon"><use href="#i-search"></use></svg>
          <input
            type="search"
            wire:model.live.debounce.250ms="search"
            class="field-input"
            placeholder="Cari nama pemilih..."
            aria-label="Cari nama pemilih"
          />
        </div>

        <div class="filter-row" role="tablist" aria-label="Filter Status Hak Pilih">
          <button type="button" class="filter-pill {{ $statusFilter === 'all' ? 'is-active' : '' }}" wire:click="$set('statusFilter', 'all')">
            <span>Semua</span>
            <span class="count">{{ $totalRights }}</span>
          </button>
          <button type="button" class="filter-pill {{ $statusFilter === 'belum_memilih' ? 'is-active' : '' }}" wire:click="$set('statusFilter', 'belum_memilih')">
            <span>Belum memilih</span>
            <span class="count">{{ $countBelum }}</span>
          </button>
          <button type="button" class="filter-pill {{ $statusFilter === 'sedang_memilih' ? 'is-active' : '' }}" wire:click="$set('statusFilter', 'sedang_memilih')">
            <span>Sedang memilih</span>
            <span class="count">{{ $countSedang }}</span>
          </button>
          <button type="button" class="filter-pill {{ $statusFilter === 'sudah_memilih' ? 'is-active' : '' }}" wire:click="$set('statusFilter', 'sudah_memilih')">
            <span>Sudah memilih</span>
            <span class="count">{{ $countSudah }}</span>
          </button>
          @if($countAttention > 0)
            <button type="button" class="filter-pill {{ $statusFilter === 'attention' ? 'is-active' : '' }}" wire:click="$set('statusFilter', 'attention')">
              <span>Perhatian</span>
              <span class="count">{{ $countAttention }}</span>
            </button>
          @endif
        </div>
      </div>
    </div>

    @if($voterRows->isEmpty())
      <div class="empty-state">
        <div class="empty-icon"><svg class="icon"><use href="#i-users"></use></svg></div>
        <h3>{{ $totalRights === 0 ? 'Belum ada Hak Pilih' : 'Tidak ada pemilih sesuai filter' }}</h3>
        <p>
          @if($totalRights === 0)
            Verifikasi identitas pemilih secara langsung lalu tekan tombol Registrasi Pemilih untuk menerbitkan QR sekali pakai.
          @else
            Ubah kata kunci pencarian atau filter status untuk menampilkan data hak pilih lainnya.
          @endif
        </p>
      </div>
    @else
      <div class="voter-table-head" aria-hidden="true">
        <div>Pemilih</div>
        <div>Status Hak Pilih</div>
        <div>Indikator Perangkat</div>
        <div>Perubahan Terakhir</div>
        <div style="text-align: right;">Tindakan</div>
      </div>

      <div class="voter-list">
        @foreach($voterRows as $row)
          @php
            $initials = '';
            foreach (array_slice(preg_split('/\s+/u', trim((string) $row->name)) ?: [], 0, 2) as $part) {
              $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            }
            $hue = abs(crc32((string) $row->id)) % 360;
            $updatedAt = \Carbon\CarbonImmutable::parse($row->updated_at)->setTimezone($tz);
          @endphp
          <div class="voter-row" wire:key="vr-{{ $row->id }}">
            <div class="voter-identity">
              <span class="avatar" style="--avatar-h: {{ $hue }};">{{ $initials }}</span>
              <div class="voter-id-copy">
                <div class="voter-name-line">
                  <span class="voter-name">{{ $row->name }}</span>
                  @if((int) $row->same_name_count > 1)
                    <span class="name-badge" title="Terdapat pemilih lain dengan nama serupa yang telah dikonfirmasi berbeda orang">Nama Kembar Terverifikasi</span>
                  @endif
                </div>
                <div class="voter-meta">ID Hak Pilih: #{{ strtoupper(substr($row->id, 0, 8)) }}</div>
              </div>
            </div>

            <div class="cell">
              <span class="cell-label">Status Hak Pilih</span>
              <div>
                <span class="status-badge {{ \App\Models\VoterRight::statusBadgeClass($row->status) }}">
                  <span class="status-dot"></span>
                  <span>{{ \App\Models\VoterRight::statusLabel($row->status) }}</span>
                </span>
                @if($row->session_expired)
                  <div class="shared-flag" style="color:var(--red);">
                    <svg class="icon"><use href="#i-clock"></use></svg>
                    <span>Sesi Kedaluwarsa (&gt;10 mnt)</span>
                  </div>
                @endif
              </div>
            </div>

            <div class="cell device-cell">
              <span class="cell-label">Perangkat</span>
              @if($row->last_browser_id)
                <div class="device-name">
                  <svg class="icon"><use href="#i-monitor"></use></svg>
                  <span>{{ \App\Services\TokenService::shortBrowserLabel($row->last_browser_id, $row->last_user_agent) }}</span>
                </div>
              @else
                <div class="device-name">
                  <span class="muted">Belum memindai QR</span>
                </div>
              @endif

              @if($row->device_warning)
                <div class="shared-flag" title="Browser ini juga pernah mengakses Hak Pilih lain pada pemilihan yang sama">
                  <svg class="icon"><use href="#i-alert"></use></svg>
                  <span>Peringatan Perangkat Bersama</span>
                </div>
              @endif
            </div>

            <div class="cell time-cell">
              <span class="cell-label">Perubahan Terakhir</span>
              <div>
                {{ $updatedAt->format('H:i:s') }}
                <small>{{ $updatedAt->diffForHumans() }}</small>
              </div>
            </div>

            <div class="row-actions">
              @if($row->status === 'sedang_memilih' && $phase === 'berlangsung')
                <button
                  type="button"
                  class="btn btn-xs {{ $row->session_expired ? 'btn-warn-ghost' : 'btn-secondary' }}"
                  wire:click="openDetailModal('{{ $row->id }}')"
                >
                  <svg class="icon"><use href="#i-rotate"></use></svg>
                  <span>Reset Sesi</span>
                </button>
              @elseif($row->status === 'belum_memilih' && $phase === 'berlangsung')
                <button
                  type="button"
                  class="btn btn-xs btn-secondary"
                  wire:click="openDetailModal('{{ $row->id }}')"
                >
                  <svg class="icon"><use href="#i-qr"></use></svg>
                  <span>QR Ulang</span>
                </button>
              @endif

              <button
                type="button"
                class="btn btn-xs btn-soft"
                wire:click="openDetailModal('{{ $row->id }}')"
              >
                <svg class="icon"><use href="#i-eye"></use></svg>
                <span>Detail</span>
              </button>
            </div>
          </div>
        @endforeach
      </div>
    @endif

    <div class="panel-foot">
      <span>Menampilkan <strong>{{ $voterRows->count() }}</strong> dari <strong>{{ $totalRights }}</strong> Hak Pilih</span>
      <span>Diperbarui otomatis setiap 3 detik · Waktu server: <strong>{{ now()->setTimezone($tz)->format('H:i:s') }}</strong></span>
    </div>
  </section>

  {{-- Baris Bawah: Pemeriksaan Integritas Suara (FR-032) & Panduan Operasional Operator --}}
  <div class="dashboard-lower">
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">
            <svg class="icon"><use href="#i-shield"></use></svg>
            <span>Pemeriksaan Integritas Suara (FR-032)</span>
          </h2>
          <p class="panel-sub">Verifikasi otomatis kesesuaian jumlah suara anonim terhadap status hak pilih.</p>
        </div>
        <span class="status-badge {{ $integrity['is_consistent'] ? 'is-sudah' : 'is-sedang' }}">
          <span class="status-dot"></span>
          <span>{{ $integrity['is_consistent'] ? 'Konsisten & Sah' : 'Perlu Pemeriksaan' }}</span>
        </span>
      </div>

      <div style="padding: 18px 23px;">
        <div class="meta-grid" style="margin: 0 0 14px;">
          <div class="meta-item">
            <span>Total Suara Masuk (Tabel Votes)</span>
            <strong>{{ $integrity['total_votes'] }} suara</strong>
          </div>
          <div class="meta-item">
            <span>Hak Pilih Berstatus "Sudah memilih"</span>
            <strong>{{ $integrity['total_voted'] }} pemilih</strong>
          </div>
          <div class="meta-item">
            <span>Total Hak Pilih Diterbitkan</span>
            <strong>{{ $integrity['total_rights'] }} dari {{ $election->max_voter_rights }} kuota</strong>
          </div>
          <div class="meta-item">
            <span>Aturan Integritas (BR-019)</span>
            <strong>{{ $integrity['is_consistent'] ? 'Suara == Sudah memilih <= Hak Pilih' : 'Ketidaksesuaian Terdeteksi' }}</strong>
          </div>
        </div>

        <div class="privacy-callout" style="margin-top: 0;">
          <svg class="icon"><use href="#i-lock"></use></svg>
          <div>
            Perolehan suara per pasangan calon disembunyikan selama pemilihan berlangsung (BR-026) dan baru dapat dibuka pada halaman Rekapitulasi setelah Waktu Selesai ({{ $election->formattedSchedule($election->ends_at) }}).
          </div>
        </div>
      </div>
    </section>

    <section class="panel guide-panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">
            <svg class="icon"><use href="#i-info"></use></svg>
            <span>Prosedur Bilik Operator</span>
          </h2>
          <p class="panel-sub">Panduan penanganan pemilih &amp; kendala di lapangan.</p>
        </div>
      </div>

      <div class="guide-steps">
        <div class="guide-step">
          <span class="guide-num">1</span>
          <p><strong>Verifikasi Identitas Langsung:</strong> <span>Periksa kartu pelajar/identitas pemilih sebelum mendaftarkan namanya (BR-001).</span></p>
        </div>
        <div class="guide-step">
          <span class="guide-num">2</span>
          <p><strong>Tunjukkan QR Sekali Tampil:</strong> <span>Pemilih memindai QR atau membuka tautan sekali pakai. QR tidak dapat dibuka kembali setelah dialog ditutup (FR-014).</span></p>
        </div>
        <div class="guide-step">
          <span class="guide-num">3</span>
          <p><strong>Penanganan Sesi Terputus:</strong> <span>Bila baterai habis atau lewat 10 menit saat <code>Sedang memilih</code>, buka Detail lalu jalankan <strong>Reset Sesi</strong> dengan alasan.</span></p>
        </div>
        <div class="guide-step">
          <span class="guide-num">4</span>
          <p><strong>QR Hilang / Bermasalah:</strong> <span>Pastikan status <code>Belum memilih</code>, lalu jalankan <strong>Terbitkan Ulang QR</strong> untuk mencabut token lama.</span></p>
        </div>
      </div>

      <div class="guide-note">
        <svg class="icon"><use href="#i-shield"></use></svg>
        <span>Peringatan Perangkat Bersama hanya bersifat indikator audit dan tidak memblokir hak suara pemilih yang telah Anda verifikasi (BR-015).</span>
      </div>
    </section>
  </div>

  {{-- UI-010: Modal Registrasi Pemilih --}}
  @if($showRegisterModal)
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true" aria-labelledby="modal-reg-title">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon"><svg class="icon"><use href="#i-user-check"></use></svg></span>
            <div>
              <h2 id="modal-reg-title">Registrasi Pemilih &amp; Terbitkan QR</h2>
              <p>Sisa kuota hak pilih: <strong>{{ max(0, $election->max_voter_rights - $totalRights) }}</strong> dari {{ $election->max_voter_rights }}.</p>
            </div>
          </div>
          <button type="button" class="icon-btn" wire:click="closeRegisterModal" aria-label="Tutup dialog">
            <svg class="icon"><use href="#i-x"></use></svg>
          </button>
        </div>

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

            @if(!empty($duplicateMatches))
              <div class="inline-alert inline-alert-warning" role="alert">
                <strong>
                  <svg class="icon"><use href="#i-alert"></use></svg>
                  <span>Terdeteksi Nama Kembar ({{ count($duplicateMatches) }} Hak Pilih terdaftar)</span>
                </strong>
                <p>
                  Nama ini sudah terdaftar pada pemilihan ini. Sesuai <strong>FR-013 &amp; BR-004</strong>, pastikan pemilih di hadapan Anda adalah <strong>orang yang berbeda</strong> (bukan pemilih yang sama meminta QR kedua):
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
            <button
              type="submit"
              class="btn btn-primary"
              wire:loading.attr="disabled"
              {{ (!empty($duplicateMatches) && !$confirmDifferentPerson) ? 'disabled' : '' }}
            >
              <span wire:loading.remove wire:target="registerVoter" style="display:inline-flex;align-items:center;gap:8px;">
                <svg class="icon"><use href="#i-qr"></use></svg>
                <span>Buat Hak Pilih &amp; Tampilkan QR</span>
              </span>
              <span wire:loading wire:target="registerVoter" style="display:inline-flex;align-items:center;gap:8px;">
                <span class="spinner" aria-hidden="true"></span>
                <span>Menerbitkan Token QR...</span>
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif

  {{-- UI-011: Modal Panel QR Sekali Tampil (FR-014, FR-029) --}}
  @if($showQrModal)
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true" aria-labelledby="modal-qr-title">
      <div class="modal" style="--modal-width: 620px;">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon"><svg class="icon"><use href="#i-qr"></use></svg></span>
            <div>
              <h2 id="modal-qr-title">{{ $issuedIsReissue ? 'Kode QR Baru Diterbitkan Ulang' : 'Kode QR Hak Pilih Diterbitkan' }}</h2>
              <p>Tunjukkan kode QR ini kepada pemilih atau salin tautan akses untuk perangkat tanpa kamera.</p>
            </div>
          </div>
        </div>

        <div class="modal-body">
          <div class="qr-success-note">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <div>
              <strong>Peringatan Keamanan: QR &amp; Tautan Hanya Tampil Sekali Ini Saja (FR-014)</strong><br />
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

              <div class="token-box">
                <div>
                  <span>TAUTAN AKSES PEMILIH (TANPA IDENTITAS/NAMA)</span>
                  <code>{{ $issuedVotingUrl }}</code>
                </div>
                <button
                  type="button"
                  class="icon-btn"
                  title="Salin tautan voting"
                  onclick="navigator.clipboard.writeText('{{ $issuedVotingUrl }}'); window.showToast('Tautan voting disalin ke papan klip.', 'success');"
                >
                  <svg class="icon"><use href="#i-copy"></use></svg>
                </button>
                <a
                  href="{{ $issuedVotingUrl }}"
                  target="_blank"
                  rel="noopener"
                  class="icon-btn"
                  title="Buka halaman voting di tab baru"
                >
                  <svg class="icon"><use href="#i-external"></use></svg>
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="window.print()">
            <span>Cetak QR</span>
          </button>
          <button type="button" class="btn btn-primary" wire:click="closeQrModal">
            <svg class="icon"><use href="#i-check"></use></svg>
            <span>Selesai (Tutup QR)</span>
          </button>
        </div>
      </div>
    </div>
  @endif

  {{-- UI-012: Modal Detail Hak Pilih, Riwayat Perangkat, Reset Sesi, & Terbitkan Ulang QR --}}
  @if($showDetailModal && $detailData)
    @php
      $dRow = $detailData['row'];
      $dUpdated = \Carbon\CarbonImmutable::parse($dRow->updated_at)->setTimezone($tz);
      $dStarted = $dRow->session_started_at ? \Carbon\CarbonImmutable::parse($dRow->session_started_at)->setTimezone($tz) : null;
    @endphp
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true" aria-labelledby="modal-detail-title">
      <div class="modal" style="--modal-width: 620px;">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon"><svg class="icon"><use href="#i-users"></use></svg></span>
            <div>
              <h2 id="modal-detail-title">Detail Hak Pilih &amp; Penanganan Kendala</h2>
              <p>ID Hak Pilih: #{{ strtoupper(substr($dRow->id, 0, 12)) }}</p>
            </div>
          </div>
          <button type="button" class="icon-btn" wire:click="closeDetailModal" aria-label="Tutup dialog">
            <svg class="icon"><use href="#i-x"></use></svg>
          </button>
        </div>

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

          {{-- Peringatan Perangkat Bersama (FR-025) --}}
          @if(!empty($detailData['shared_rights']))
            <div class="alert alert-warning" style="margin-bottom: 16px;">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <div>
                <strong>Peringatan Perangkat Bersama (FR-025)</strong>
                <p>Pengenal Browser yang mengakses Hak Pilih ini juga tercatat pernah mengakses Hak Pilih lain pada pemilihan ini:</p>
                <ul class="confirm-list" style="margin: 6px 0 0;">
                  @foreach($detailData['shared_rights'] as $sr)
                    <li>
                      <strong>{{ $sr->name }}</strong> ({{ \App\Models\VoterRight::statusLabel($sr->status) }}) — Browser <code>#{{ strtoupper(substr(hash('sha256', $sr->browser_id), 0, 6)) }}</code>
                    </li>
                  @endforeach
                </ul>
                <p style="margin-top:4px;font-size:0.72rem;">Catatan: Penggunaan perangkat bergantian diperbolehkan selama Operator telah memverifikasi langsung identitas pemilih (BR-015).</p>
              </div>
            </div>
          @endif

          {{-- Aksi Penanganan Operator: Reset Sesi (FR-030) atau Terbitkan Ulang QR (FR-029) --}}
          @if($phase === 'berlangsung')
            @if($dRow->status === 'sedang_memilih')
              <div class="panel" style="padding: 16px; margin-bottom: 16px; background: #fffaf2; border-color: #f2dfbd;">
                <div class="section-title" style="color: var(--amber);">
                  <svg class="icon" style="color: var(--amber);"><use href="#i-rotate"></use></svg>
                  <span>Reset Sesi Memilih (FR-030)</span>
                </div>
                <p class="field-hint" style="margin-top: 2px;">
                  Kembalikan status dari <code>Sedang memilih</code> menjadi <code>Belum memilih</code> apabila perangkat pemilih terputus, berganti browser, atau batas 10 menit telah lewat. Untuk menerbitkan ulang QR, lakukan Reset Sesi ini terlebih dahulu (WF-007).
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
                    <button type="submit" class="btn btn-sm btn-primary" wire:loading.attr="disabled">
                      <svg class="icon"><use href="#i-rotate"></use></svg>
                      <span>Reset Sesi ke Belum Memilih</span>
                    </button>
                  </div>
                </form>
              </div>
            @elseif($dRow->status === 'belum_memilih')
              <div class="panel" style="padding: 16px; margin-bottom: 16px; background: var(--surface-soft);">
                <div class="section-title">
                  <svg class="icon"><use href="#i-qr"></use></svg>
                  <span>Terbitkan Ulang QR (FR-029)</span>
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
                    <button type="submit" class="btn btn-sm btn-primary" wire:loading.attr="disabled">
                      <svg class="icon"><use href="#i-qr"></use></svg>
                      <span>Cabut QR Lama &amp; Terbitkan QR Baru</span>
                    </button>
                  </div>
                </form>
              </div>
            @else
              <div class="alert alert-success" style="margin-bottom: 16px;">
                <svg class="icon"><use href="#i-check-circle"></use></svg>
                <div>
                  <strong>Suara telah tersimpan (Status Final)</strong>
                  <p>Hak pilih ini sudah digunakan. Sesuai BR-007 dan WF-007, status <code>Sudah memilih</code> bersifat final dan tidak dapat direset atau diterbitkan ulang.</p>
                </div>
              </div>
            @endif
          @endif

          {{-- Riwayat Akses Perangkat (FR-024) --}}
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

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" wire:click="closeDetailModal">Tutup</button>
        </div>
      </div>
    </div>
  @endif
</div>
