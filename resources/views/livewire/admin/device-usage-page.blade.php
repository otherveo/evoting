{{-- Update v7: Log Audit Penggunaan Perangkat (Admin) — batas 2x per pemilihan --}}
<div class="container page view-enter">
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-monitor"></use></svg>
        <span>PENGGUNAAN PERANGKAT PER PEMILIHAN</span>
      </div>
      <h1>Penggunaan Perangkat</h1>
      <p class="lede">
        Perangkat (browser) yang membuka tautan bilik pada pemilihan ini, dihitung sejak batas v7 berlaku. Satu perangkat hanya diizinkan maksimal 2x; perangkat yang sudah 2x dapat diblokir manual oleh Admin sehingga langsung terblokir dari sisi pemilih.
      </p>
    </div>
  </div>

  <section class="panel" aria-label="Penggunaan Perangkat">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-monitor"></use></svg>
          <span>Perangkat per Pemilihan</span>
        </h2>
        <p class="panel-sub">Pemilihan training/latihan/tes tidak dihitung dan tidak tampil di sini. Penolakan otomatis dan pemblokiran tercatat di Log Audit.</p>
      </div>

      <div class="voter-tools">
        <select
          class="field-input"
          style="width:auto;height:38px;font-size:0.78rem;"
          aria-label="Pilih Pemilihan"
          wire:change="selectElection($event.target.value)"
        >
          @forelse($elections as $election)
            <option value="{{ $election->id }}" @selected($electionId === (string) $election->id)>{{ $election->name }}</option>
          @empty
            <option value="">Belum ada pemilihan terjadwal</option>
          @endforelse
        </select>
        <input
          type="text"
          class="field-input"
          style="height:38px;font-size:0.78rem;"
          placeholder="Alasan blokir (opsional)"
          aria-label="Alasan blokir perangkat"
          wire:model="blockReason"
        />
      </div>
    </div>

    @if($usage === null || $usage->isEmpty())
      <div class="empty-state">
        <div class="empty-icon"><svg class="icon"><use href="#i-monitor"></use></svg></div>
        <h3>Belum ada penggunaan perangkat</h3>
        <p>Belum ada tautan bilik yang dibuka pada pemilihan ini sejak batas v7 berlaku, atau belum ada pemilihan yang dipilih.</p>
      </div>
    @else
      <div class="data-table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Perangkat</th>
              <th>Penggunaan</th>
              <th>Tautan Dibuka</th>
              <th>Terakhir Dibuka ({{ $tz }})</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($usage as $row)
              <tr wire:key="device-{{ $row->browser_id }}">
                <td>
                  <div style="font-weight:700;font-size:0.8rem;">{{ \App\Livewire\Admin\DeviceUsagePage::deviceLabel($row->browser_id, $row->last_user_agent) }}</div>
                  <div class="voter-meta">#{{ strtoupper(substr(hash('sha256', (string) $row->browser_id), 0, 6)) }}</div>
                </td>
                <td>
                  <strong>{{ (int) $row->use_count }} / 2</strong>
                </td>
                <td>{{ (int) $row->open_count }}x</td>
                <td style="white-space:nowrap;">
                  {{ \Carbon\CarbonImmutable::parse($row->last_accessed_at)->setTimezone($tz)->format('d M Y, H:i:s') }}
                </td>
                <td>
                  @if($row->is_blocked)
                    <span class="status-badge is-belum">
                      <span class="status-dot"></span>
                      <span>TERBLOKIR</span>
                    </span>
                  @elseif((int) $row->use_count >= 2)
                    <span class="status-badge is-sedang">
                      <span class="status-dot"></span>
                      <span>PENUH (2/2)</span>
                    </span>
                  @else
                    <span class="status-badge is-sudah">
                      <span class="status-dot"></span>
                      <span>AKTIF</span>
                    </span>
                  @endif
                </td>
                <td>
                  @if($row->is_blocked)
                    <x-action-button
                      variant="secondary"
                      size="sm"
                      wire:click="unblockDevice('{{ $row->browser_id }}')"
                      wire:confirm="Buka blokir perangkat ini? Pemilih dari perangkat ini dapat memilih kembali."
                      loadingText="Memproses..."
                    >
                      Buka blokir
                    </x-action-button>
                  @else
                    <x-action-button
                      variant="secondary"
                      size="sm"
                      wire:click="blockDevice('{{ $row->browser_id }}')"
                      wire:confirm="Blokir perangkat ini pada pemilihan ini? Pemilih dari perangkat ini langsung tidak dapat memilih dan harus memakai perangkat lain."
                      loadingText="Memproses..."
                    >
                      Blokir
                    </x-action-button>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($usage !== null)
      <div class="panel-foot">
        <span>Total <strong>{{ $usage->total() }}</strong> perangkat</span>
        <div style="display:flex;gap:8px;align-items:center;">
          @if($usage->onFirstPage())
            <button type="button" class="btn btn-xs btn-secondary" disabled>Sebelumnya</button>
          @else
            <x-action-button variant="secondary" size="xs" wire:click="previousPage" loadingText="Memuat...">
              Sebelumnya
            </x-action-button>
          @endif
          <span>Halaman <strong>{{ $usage->currentPage() }}</strong> / {{ max(1, $usage->lastPage()) }}</span>
          @if($usage->hasMorePages())
            <x-action-button variant="secondary" size="xs" wire:click="nextPage" loadingText="Memuat...">
              Berikutnya
            </x-action-button>
          @else
            <button type="button" class="btn btn-xs btn-secondary" disabled>Berikutnya</button>
          @endif
        </div>
      </div>
    @endif
  </section>
</div>
