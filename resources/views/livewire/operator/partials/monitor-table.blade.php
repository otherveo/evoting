{{-- UI-009, FR-023, FR-025, BR-016, BR-017: Tabel Monitoring Aktivitas Pemilih (Filter & Pencarian Instan di Client) --}}
<section
  class="panel"
  aria-label="Daftar Hak Pilih"
  x-data="{
    clientSearch: $wire.entangle('search'),
    clientStatus: $wire.entangle('statusFilter'),
    matchesRow(name, status, attention) {
      if (this.clientStatus === 'belum_memilih' && status !== 'belum_memilih') return false;
      if (this.clientStatus === 'sedang_memilih' && status !== 'sedang_memilih') return false;
      if (this.clientStatus === 'sudah_memilih' && status !== 'sudah_memilih') return false;
      if (this.clientStatus === 'attention' && !attention) return false;
      const q = (this.clientSearch || '').trim().toLowerCase();
      if (q !== '' && !name.toLowerCase().includes(q)) return false;
      return true;
    }
  }"
>
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
          x-model="clientSearch"
          class="field-input"
          placeholder="Cari nama pemilih..."
          aria-label="Cari nama pemilih"
        />
      </div>

      <div class="filter-row" role="tablist" aria-label="Filter Status Hak Pilih">
        <button type="button" class="filter-pill" :class="{ 'is-active': clientStatus === 'all' }" x-on:click="clientStatus = 'all'">
          <span>Semua</span>
          <span class="count">{{ $totalRights }}</span>
        </button>
        <button type="button" class="filter-pill" :class="{ 'is-active': clientStatus === 'belum_memilih' }" x-on:click="clientStatus = 'belum_memilih'">
          <span>Belum memilih</span>
          <span class="count">{{ $countBelum }}</span>
        </button>
        <button type="button" class="filter-pill" :class="{ 'is-active': clientStatus === 'sedang_memilih' }" x-on:click="clientStatus = 'sedang_memilih'">
          <span>Sedang memilih</span>
          <span class="count">{{ $countSedang }}</span>
        </button>
        <button type="button" class="filter-pill" :class="{ 'is-active': clientStatus === 'sudah_memilih' }" x-on:click="clientStatus = 'sudah_memilih'">
          <span>Sudah memilih</span>
          <span class="count">{{ $countSudah }}</span>
        </button>
        @if($countAttention > 0)
          <button type="button" class="filter-pill" :class="{ 'is-active': clientStatus === 'attention' }" x-on:click="clientStatus = 'attention'">
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
        <div
          class="voter-row"
          wire:key="vr-{{ $row->id }}"
          x-show="matchesRow(@js((string) $row->name), @js((string) $row->status), @js((bool) ($row->session_expired || $row->device_warning)))"
        >
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
              <div class="shared-device-box">
                <div class="shared-flag">
                  <svg class="icon"><use href="#i-alert"></use></svg>
                  <span>Peringatan Perangkat Bersama</span>
                  @if($row->shared_group_tag)
                    <span class="shared-group-pill">Grup {{ $row->shared_group_tag }}</span>
                  @endif
                </div>
                @if(!empty($row->shared_peers))
                  <div class="shared-peers-text">
                    Perangkat ini juga dipakai oleh:
                    @foreach($row->shared_peers as $pIdx => $peer)
                      <strong>{{ $peer['name'] }} (#{{ $peer['short_id'] }})</strong>{{ $pIdx < count($row->shared_peers) - 1 ? ', ' : '' }}
                    @endforeach
                  </div>
                @endif
              </div>
            @endif

            @if(($row->device_approval_status ?? 'tidak_perlu') === 'menunggu' && $row->status === 'sedang_memilih')
              <div class="shared-flag" style="color: var(--amber); font-weight: 800; margin-top: 4px;">
                <svg class="icon"><use href="#i-clock"></use></svg>
                <span>Menunggu Persetujuan Operator</span>
              </div>
            @elseif(($row->device_approval_status ?? 'tidak_perlu') === 'disetujui')
              <div class="shared-flag" style="color: var(--green); margin-top: 4px;">
                <svg class="icon"><use href="#i-check-circle"></use></svg>
                <span>Perangkat Bersama Diizinkan</span>
              </div>
            @elseif(($row->device_approval_status ?? 'tidak_perlu') === 'ditolak')
              <div class="shared-flag" style="color: var(--red); margin-top: 4px;">
                <svg class="icon"><use href="#i-x"></use></svg>
                <span>Perangkat Bersama Ditolak (QR Dicabut)</span>
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
                wire:click="openDetailModal('{{ $row->id }}', 'reset')"
                loadingText="Membuka..."
              >
                Reset Sesi
              </x-action-button>
            @elseif($row->status === 'belum_memilih' && $phase === 'berlangsung')
              <x-action-button
                variant="secondary"
                size="xs"
                icon="#i-qr"
                wire:click="openDetailModal('{{ $row->id }}', 'reissue')"
                loadingText="Membuka..."
              >
                QR Ulang
              </x-action-button>
            @endif

            <x-action-button
              variant="soft"
              size="xs"
              icon="#i-eye"
              wire:click="openDetailModal('{{ $row->id }}', 'detail')"
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
