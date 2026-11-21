{{-- UI-006, FR-026, FR-027, BR-020, NFR-008: Halaman Peninjauan Log Audit (Admin Baca-Saja) --}}
<div class="container page view-enter">
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-activity"></use></svg>
        <span>REKAM JEJAK KEAMANAN &amp; OPERASIONAL</span>
      </div>
      <h1>Log Audit Sistem</h1>
      <p class="lede">
        Catatan permanen seluruh aktivitas penting staf dan peristiwa pemilihan. Entri bersifat kekal (tidak dapat diubah atau dihapus) dan tidak pernah memuat pilihan suara.
      </p>
    </div>
  </div>

  <section class="panel" aria-label="Daftar Log Audit">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-shield"></use></svg>
          <span>Jejak Aktivitas (Baca Saja)</span>
        </h2>
        {{-- NFR-008: Kekekalan audit_logs dijaga oleh trigger PostgreSQL --}}
        <p class="panel-sub">Dilindungi secara permanen oleh basis data.</p>
      </div>

      <div class="voter-tools">
        <form
          x-data="{ actor: @js($actorType), action: @js($actionFilter), search: @js($search) }"
          x-on:submit.prevent.stop="$wire.applyFilters(actor, action, search)"
          style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;"
        >
          <select x-model="actor" class="field-input" style="width:auto;height:38px;font-size:0.78rem;" aria-label="Filter Peran Aktor">
            <option value="all">Semua Aktor</option>
            <option value="admin">Admin</option>
            <option value="operator">Operator</option>
            <option value="pemilih">Pemilih</option>
            <option value="sistem">Sistem (CLI)</option>
          </select>

          <select x-model="action" class="field-input" style="width:auto;height:38px;font-size:0.78rem;" aria-label="Filter Jenis Aksi">
            <option value="all">Semua Jenis Aksi</option>
            @foreach($distinctActions as $act)
              <option value="{{ $act }}">{{ $act }}</option>
            @endforeach
          </select>

          <div class="search-field">
            <svg class="icon"><use href="#i-search"></use></svg>
            <input
              type="search"
              x-model="search"
              class="field-input"
              placeholder="Cari aksi atau detail..."
              aria-label="Cari entri log audit"
            />
          </div>
          <x-action-button type="submit" variant="secondary" size="sm" target="applyFilters" loadingText="Memuat...">
            Terapkan filter
          </x-action-button>
        </form>
      </div>
    </div>

    @if($logs->isEmpty())
      <div class="empty-state">
        <div class="empty-icon"><svg class="icon"><use href="#i-activity"></use></svg></div>
        <h3>Tidak ada entri sesuai filter</h3>
        <p>Ubah filter pencarian untuk melihat catatan aktivitas lainnya.</p>
      </div>
    @else
      <div class="data-table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Waktu ({{ $tz }})</th>
              <th>Aktor</th>
              <th>Aksi</th>
              <th>Objek</th>
              <th>Rincian Konteks</th>
            </tr>
          </thead>
          <tbody>
            @foreach($logs as $log)
              <tr wire:key="audit-{{ $log->id }}">
                <td style="white-space:nowrap;">
                  <div class="time-cell">
                    <strong>{{ $log->created_at?->setTimezone($tz)->format('d M Y, H:i:s') }}</strong>
                    <small>{{ $log->created_at?->diffForHumans() }}</small>
                  </div>
                </td>
                <td>
                  <span class="status-badge {{ $log->actor_type === 'admin' ? 'is-sedang' : ($log->actor_type === 'operator' ? 'is-sudah' : 'is-belum') }}">
                    <span class="status-dot"></span>
                    <span>{{ strtoupper($log->actor_type) }}</span>
                  </span>
                  @if($log->actor_id)
                    <div class="voter-meta" style="margin-top:3px;">ID: {{ substr($log->actor_id, 0, 8) }}</div>
                  @endif
                </td>
                <td>
                  <span class="code-pill">{{ $log->action }}</span>
                </td>
                <td>
                  @if($log->object_type)
                    <div style="font-weight:700;font-size:0.76rem;">{{ $log->object_type }}</div>
                    @if($log->object_id)
                      <div class="voter-meta">{{ substr($log->object_id, 0, 12) }}…</div>
                    @endif
                  @else
                    <span class="muted">—</span>
                  @endif
                </td>
                <td>
                  @if(!empty($log->detail))
                    <code style="font-size:0.72rem;color:var(--ink-soft);word-break:break-all;">
                      {{ json_encode($log->detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                    </code>
                  @else
                    <span class="muted">—</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <div class="panel-foot">
      <span>Total <strong>{{ $logs->total() }}</strong> entri log audit</span>
      <div style="display:flex;gap:8px;align-items:center;">
        @if($logs->onFirstPage())
          <button type="button" class="btn btn-xs btn-secondary" disabled>Sebelumnya</button>
        @else
          <x-action-button variant="secondary" size="xs" wire:click="previousPage" loadingText="Memuat...">
            Sebelumnya
          </x-action-button>
        @endif
        <span>Halaman <strong>{{ $logs->currentPage() }}</strong> / {{ max(1, $logs->lastPage()) }}</span>
        @if($logs->hasMorePages())
          <x-action-button variant="secondary" size="xs" wire:click="nextPage" loadingText="Memuat...">
            Berikutnya
          </x-action-button>
        @else
          <button type="button" class="btn btn-xs btn-secondary" disabled>Berikutnya</button>
        @endif
      </div>
    </div>
  </section>
</div>
