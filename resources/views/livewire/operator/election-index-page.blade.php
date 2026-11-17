<div class="container page view-enter">
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-dashboard"></use></svg>
        <span>MANAJEMEN PEMILIHAN OPERATOR</span>
      </div>
      <h1>Daftar Pemilihan</h1>
      <p class="lede">
        Pilih penyelenggaraan pemungutan suara untuk mengatur jadwal, mengelola pasangan calon, mendaftarkan hak pilih QR, atau memantau jalannya pemilihan.
      </p>
    </div>
    <div class="heading-actions">
      <button type="button" class="btn btn-primary" wire:click="openCreateModal">
        <svg class="icon"><use href="#i-plus"></use></svg>
        <span>Buat Pemilihan Baru</span>
      </button>
    </div>
  </div>

  @if($elections->isEmpty())
    <section class="panel">
      <div class="empty-state">
        <div class="empty-icon"><svg class="icon"><use href="#i-ballot"></use></svg></div>
        <h3>Belum ada pemilihan</h3>
        <p>Buat pemilihan pertama Anda, tambahkan minimal 2 pasangan calon, lalu atur jadwal pelaksanaan.</p>
        <div style="margin-top: 16px;">
          <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <svg class="icon"><use href="#i-plus"></use></svg>
            <span>Buat Pemilihan Pertama</span>
          </button>
        </div>
      </div>
    </section>
  @else
    <div class="election-grid">
      @foreach($elections as $item)
        @php
          /** @var \App\Models\Election $el */
          $el = $item['model'];
          $fase = $item['fase'];
          $badgeClass = $fase === 'berlangsung' ? 'is-sedang' : ($fase === 'ditutup' ? 'is-sudah' : 'is-belum');
        @endphp
        <article class="election-card" wire:key="el-{{ $el->id }}">
          <div>
            <div class="election-card-head">
              <h2 class="election-card-title">{{ $el->name }}</h2>
              <span class="status-badge {{ $badgeClass }}">
                <span class="status-dot"></span>
                <span>{{ \App\Models\Election::phaseLabel($fase) }}</span>
              </span>
            </div>

            <dl class="election-card-meta" style="margin-top: 14px;">
              <div>
                <dt>Waktu Mulai</dt>
                <dd>{{ $el->formattedSchedule($el->starts_at) }}</dd>
              </div>
              <div>
                <dt>Waktu Selesai</dt>
                <dd>{{ $el->formattedSchedule($el->ends_at) }}</dd>
              </div>
              <div>
                <dt>Hak Pilih Terdaftar</dt>
                <dd>{{ $item['rights_count'] }} / {{ $el->max_voter_rights }} kuota</dd>
              </div>
              <div>
                <dt>Paslon &amp; Suara Masuk</dt>
                <dd>{{ $item['candidates_count'] }} paslon · {{ $item['voted_count'] }} memilih</dd>
              </div>
            </dl>
          </div>

          <div class="election-card-actions">
            <a href="{{ route('operator.elections.monitor', $el) }}" class="btn btn-sm btn-primary">
              <svg class="icon"><use href="#i-users"></use></svg>
              <span>Pantau &amp; Registrasi QR</span>
            </a>
            <a href="{{ route('operator.elections.detail', $el) }}" class="btn btn-sm btn-secondary">
              <svg class="icon"><use href="#i-calendar"></use></svg>
              <span>Konfigurasi &amp; Paslon</span>
            </a>
            <a href="{{ route('operator.elections.recap', $el) }}" class="btn btn-sm btn-soft">
              <svg class="icon"><use href="#i-chart"></use></svg>
              <span>Rekapitulasi</span>
            </a>
          </div>
        </article>
      @endforeach
    </div>
  @endif

  @if($showCreateModal)
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true" aria-labelledby="modal-create-el-title">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon"><svg class="icon"><use href="#i-ballot"></use></svg></span>
            <div>
              <h2 id="modal-create-el-title">Buat Pemilihan Baru</h2>
              <p>Pemilihan baru akan berada pada Fase Persiapan sampai jadwal Waktu Mulai tercapai.</p>
            </div>
          </div>
          <button type="button" class="icon-btn" wire:click="closeCreateModal" aria-label="Tutup dialog">
            <svg class="icon"><use href="#i-x"></use></svg>
          </button>
        </div>

        <form wire:submit="createElection" novalidate>
          <div class="modal-body">
            <div class="form-field">
              <label for="el-name" class="field-label">Nama Pemilihan <span class="req">*</span></label>
              <input
                id="el-name"
                type="text"
                wire:model="name"
                class="field-input @error('name') is-invalid @enderror"
                placeholder="Contoh: Pemilihan Ketua & Wakil Ketua OSIS 2026/2027"
                autofocus
              />
              @error('name')
                <div class="field-error" role="alert">
                  <svg class="icon"><use href="#i-alert"></use></svg>
                  <span>{{ $message }}</span>
                </div>
              @enderror
            </div>

            <div class="form-field">
              <label for="el-max" class="field-label">Batas Maksimum Hak Pilih <span class="req">*</span></label>
              <input
                id="el-max"
                type="number"
                min="1"
                wire:model="maxVoterRights"
                class="field-input @error('maxVoterRights') is-invalid @enderror"
              />
              <p class="field-hint">Sistem otomatis menolak pembuatan hak pilih baru setelah kuota ini tercapai (FR-028).</p>
              @error('maxVoterRights')
                <div class="field-error" role="alert">
                  <svg class="icon"><use href="#i-alert"></use></svg>
                  <span>{{ $message }}</span>
                </div>
              @enderror
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" wire:click="closeCreateModal">Batal</button>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
              <span wire:loading.remove wire:target="createElection">Buat Pemilihan</span>
              <span wire:loading wire:target="createElection">Menyimpan...</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif
</div>
