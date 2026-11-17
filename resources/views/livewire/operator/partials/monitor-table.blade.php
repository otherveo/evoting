{{-- UI-009, FR-023, FR-025, BR-016, BR-017: Tabel Monitoring Aktivitas Pemilih --}}
<section class="panel" aria-label="Daftar Hak Pilih">
  <div class="panel-head">
    <div>
      <h2 class="panel-title">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>Daftar Hak Pilih &amp; Status Sesi</span>
      </h2>
      {{-- BR-016, BR-017: Pilihan paslon dipisahkan secara struktural dari daftar hak pilih --}}
      <p class="panel-sub">
        Pilihan pasangan calon dipisahkan secara struktural dari daftar ini demi menjaga kerahasiaan suara.
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
              <x-action-button
                :variant="$row->session_expired ? 'warn-ghost' : 'secondary'"
                size="xs"
                icon="#i-rotate"
                wire:click="openDetailModal('{{ $row->id }}')"
                loadingText="Membuka..."
              >
                Reset Sesi
              </x-action-button>
            @elseif($row->status === 'belum_memilih' && $phase === 'berlangsung')
              <x-action-button
                variant="secondary"
                size="xs"
                icon="#i-qr"
                wire:click="openDetailModal('{{ $row->id }}')"
                loadingText="Membuka..."
              >
                QR Ulang
              </x-action-button>
            @endif

            <x-action-button
              variant="soft"
              size="xs"
              icon="#i-eye"
              wire:click="openDetailModal('{{ $row->id }}')"
              loadingText="Membuka..."
            >
              Detail
            </x-action-button>
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
