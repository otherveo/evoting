{{-- UI-009, FR-023, FR-025, KP-003, NFR-016: Halaman Pantau Pemilih Operator (Polling Aktif Saat Tidak Ada Modal) --}}
<div class="container page view-enter" @if(!$showRegisterModal && !$showQrModal && !$showDetailModal) wire:poll.5s.visible @endif>
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-monitor"></use></svg>
        <span>DASHBOARD MONITORING OPERATOR · PEMBARUAN OTOMATIS</span>
      </div>
      <h1>{{ $election->name }}</h1>
      <p class="lede">
        Pantau status hak pilih secara langsung, daftarkan pemilih terverifikasi untuk menerbitkan QR sekali pakai, dan tangani sesi memilih yang terputus.
      </p>
    </div>

    <div class="heading-actions">
      <a href="{{ route('operator.elections.detail', $election) }}" wire:navigate class="btn btn-secondary">
        <svg class="icon"><use href="#i-calendar"></use></svg>
        <span>Jadwal &amp; Paslon</span>
      </a>
      <x-action-button
        variant="primary"
        icon="#i-qr"
        wire:click="openRegisterModal"
        loadingText="Membuka..."
        :disabled="$phase !== 'berlangsung' || $totalRights >= $election->max_voter_rights"
      >
        Registrasi Pemilih &amp; Terbitkan QR
      </x-action-button>
    </div>
  </div>

  {{-- FR-010, BR-023, FR-028: Peringatan Fase atau Kuota --}}
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
            <p>Naikkan batas maksimum pada menu Konfigurasi apabila masih ada pemilih sah yang perlu didaftarkan.</p>
          </div>
        </div>
        <a href="{{ route('operator.elections.detail', $election) }}" class="btn btn-sm btn-secondary">Ubah Batas Kuota</a>
      </div>
    </div>
  @endif

  {{-- KP-004, FR-030: Peringatan Sesi Kedaluwarsa --}}
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
    <x-stat-card
      icon="#i-users"
      :cap="'Kuota ' . $totalRights . '/' . $election->max_voter_rights"
      :value="$totalRights"
      label="Hak Pilih Terdaftar"
      :hint="'Sisa kuota ' . max(0, $election->max_voter_rights - $totalRights) . ' hak pilih'"
      :progress="$quotaPct"
    />
    <x-stat-card
      icon="#i-check-circle"
      cap="Selesai"
      :value="$countSudah"
      label="Sudah memilih"
      hint="Suara anonim tersimpan sah"
      variant="green"
    />
    <x-stat-card
      icon="#i-clock"
      cap="Sesi 10 Menit"
      :value="$countSedang"
      label="Sedang memilih"
      :hint="$countExpired > 0 ? $countExpired . ' sesi lewat 10 menit' : 'Semua sesi dalam batas waktu'"
      variant="amber"
    />
    <x-stat-card
      icon="#i-qr"
      cap="Antrean QR"
      :value="$countBelum"
      label="Belum memilih"
      hint="QR diterbitkan, belum mulai"
      variant="violet"
    />
  </div>

  {{-- Tabel Daftar Hak Pilih & Filter --}}
  @include('livewire.operator.partials.monitor-table')

  {{-- Baris Bawah: Pemeriksaan Integritas Suara & Panduan Operasional Operator --}}
  <div class="dashboard-lower">
    {{-- FR-032, BR-019, BR-026: Panel Pemeriksaan Integritas Suara --}}
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">
            <svg class="icon"><use href="#i-shield"></use></svg>
            <span>Pemeriksaan Integritas Suara</span>
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
            <span>Total Suara Masuk (Tabel Suara)</span>
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
            <span>Kesesuaian Jumlah Suara</span>
            <strong>{{ $integrity['is_consistent'] ? 'Suara == Sudah memilih <= Hak Pilih' : 'Ketidaksesuaian Terdeteksi' }}</strong>
          </div>
        </div>

        {{-- BR-026: Hasil per paslon disembunyikan sebelum Waktu Selesai --}}
        <div class="privacy-callout" style="margin-top: 0;">
          <svg class="icon"><use href="#i-lock"></use></svg>
          <div>
            Perolehan suara per pasangan calon disembunyikan selama pemilihan berlangsung dan baru dapat dibuka pada halaman Rekapitulasi setelah Waktu Selesai ({{ $election->formattedSchedule($election->ends_at) }}).
          </div>
        </div>
      </div>
    </section>

    {{-- BR-001, FR-014, FR-029, FR-030, BR-015: Panduan Operasional Bilik Operator --}}
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
          <p><strong>Verifikasi Identitas Langsung:</strong> <span>Periksa kartu pelajar/identitas pemilih sebelum mendaftarkan namanya.</span></p>
        </div>
        <div class="guide-step">
          <span class="guide-num">2</span>
          <p><strong>Tunjukkan QR Sekali Tampil:</strong> <span>Pemilih memindai QR atau membuka tautan sekali pakai. QR tidak dapat dibuka kembali setelah dialog ditutup.</span></p>
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
        <span>Peringatan Perangkat Bersama hanya bersifat indikator audit dan tidak memblokir hak suara pemilih yang telah Anda verifikasi.</span>
      </div>
    </section>
  </div>

  {{-- Modal Registrasi (UI-010), Panel QR (UI-011), & Detail Hak Pilih (UI-012) --}}
  @include('livewire.operator.partials.monitor-modals')
</div>
