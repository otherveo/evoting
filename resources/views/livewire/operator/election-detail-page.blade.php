<div class="container page view-enter">
  <div class="page-heading">
    <div>
      <div class="eyebrow">
        <svg class="icon"><use href="#i-calendar"></use></svg>
        <span>KONFIGURASI PEMILIHAN · FASE {{ strtoupper(\App\Models\Election::phaseLabel($phase)) }}</span>
      </div>
      <h1>{{ $election->name }}</h1>
      <p class="lede">
        Atur identitas pemilihan, batas maksimum hak pilih, jadwal pelaksanaan berpresisi menit, serta daftar pasangan calon.
      </p>
    </div>
    <div class="heading-actions">
      <a href="{{ route('operator.elections.monitor', $election) }}" class="btn btn-primary">
        <svg class="icon"><use href="#i-users"></use></svg>
        <span>Buka Pantau Pemilih</span>
      </a>
      <a href="{{ route('operator.elections.recap', $election) }}" class="btn btn-secondary">
        <svg class="icon"><use href="#i-chart"></use></svg>
        <span>Rekapitulasi</span>
      </a>
    </div>
  </div>

  <div class="grid-2col" style="margin-bottom: 18px;">
    {{-- Panel 1: Identitas & Kuota Hak Pilih --}}
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">
            <svg class="icon"><use href="#i-ballot"></use></svg>
            <span>Identitas &amp; Batas Hak Pilih</span>
          </h2>
          <p class="panel-sub">Terdaftar saat ini: <strong>{{ $voterRightsCount }}</strong> dari <strong>{{ $election->max_voter_rights }}</strong> hak pilih.</p>
        </div>
        <span class="status-badge {{ $phase === 'berlangsung' ? 'is-sedang' : ($phase === 'ditutup' ? 'is-sudah' : 'is-belum') }}">
          <span class="status-dot"></span>
          <span>{{ \App\Models\Election::phaseLabel($phase) }}</span>
        </span>
      </div>

      <form wire:submit="saveElectionInfo" style="padding: 20px 23px;" novalidate>
        <div class="form-field">
          <label for="detail-name" class="field-label">Nama Pemilihan <span class="req">*</span></label>
          <input
            id="detail-name"
            type="text"
            wire:model="name"
            class="field-input @error('name') is-invalid @enderror"
          />
          @error('name')
            <div class="field-error" role="alert">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>{{ $message }}</span>
            </div>
          @enderror
        </div>

        <div class="form-field">
          <label for="detail-max" class="field-label">Batas Maksimum Hak Pilih <span class="req">*</span></label>
          <input
            id="detail-max"
            type="number"
            min="{{ max(1, $voterRightsCount) }}"
            wire:model="maxVoterRights"
            class="field-input @error('maxVoterRights') is-invalid @enderror"
          />
          <p class="field-hint">Tidak boleh diturunkan di bawah jumlah hak pilih yang sudah dibuat ({{ $voterRightsCount }}).</p>
          @error('maxVoterRights')
            <div class="field-error" role="alert">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span>{{ $message }}</span>
            </div>
          @enderror
        </div>

        <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
          <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
            <svg class="icon"><use href="#i-check"></use></svg>
            <span>Simpan Perubahan</span>
          </button>
        </div>
      </form>
    </section>

    {{-- Panel 2: Jadwal Pemilihan (FR-009) --}}
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2 class="panel-title">
            <svg class="icon"><use href="#i-clock"></use></svg>
            <span>Jadwal Pemilihan ({{ $tz }})</span>
          </h2>
          <p class="panel-sub">Fase pemilihan berubah otomatis mengikuti jadwal. Minimal 2 paslon diperlukan.</p>
        </div>
      </div>

      <div style="padding: 20px 23px;">
        @if($candidates->count() < 2 && $phase === 'persiapan')
          <div class="alert alert-warning" style="margin-bottom: 14px;">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <div>
              <strong>Minimal 2 Pasangan Calon</strong>
              <p>Tambahkan minimal 2 pasangan calon terlebih dahulu sebelum menyimpan jadwal pemilihan (BR-024).</p>
            </div>
          </div>
        @endif

        <form wire:submit="saveSchedule" novalidate>
          <div class="form-field">
            <label for="sched-start" class="field-label">Waktu Mulai (Tahun-Bulan-Tanggal Jam:Menit) <span class="req">*</span></label>
            <input
              id="sched-start"
              type="datetime-local"
              wire:model="startsAtInput"
              class="field-input @error('startsAtInput') is-invalid @enderror"
              {{ $phase !== 'persiapan' ? 'disabled' : '' }}
            />
            @if($phase === 'berlangsung')
              <p class="field-hint">Waktu Mulai terkunci karena pemilihan sedang berlangsung (BR-025).</p>
            @endif
            @error('startsAtInput')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>

          <div class="form-field">
            <label for="sched-end" class="field-label">Waktu Selesai (Tahun-Bulan-Tanggal Jam:Menit) <span class="req">*</span></label>
            <input
              id="sched-end"
              type="datetime-local"
              wire:model="endsAtInput"
              class="field-input @error('endsAtInput') is-invalid @enderror"
              {{ $phase === 'ditutup' ? 'disabled' : '' }}
            />
            <p class="field-hint">Setelah Waktu Selesai tercapai, pemungutan suara ditutup otomatis dan hasil dibuka.</p>
            @error('endsAtInput')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>

          <div style="margin-top: 18px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center;">
            @if($phase === 'persiapan')
              <button
                type="button"
                class="btn btn-soft"
                wire:click="startElectionNow"
                {{ $candidates->count() < 2 ? 'disabled' : '' }}
              >
                <svg class="icon"><use href="#i-sparkles"></use></svg>
                <span>Mulai Sekarang</span>
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                {{ $candidates->count() < 2 ? 'disabled' : '' }}
              >
                <svg class="icon"><use href="#i-calendar"></use></svg>
                <span>Simpan Jadwal</span>
              </button>
            @elseif($phase === 'berlangsung')
              <button
                type="button"
                class="btn btn-danger-ghost"
                wire:click="$set('showEndEarlyModal', true)"
              >
                <svg class="icon"><use href="#i-lock"></use></svg>
                <span>Akhiri Pemilihan Sekarang</span>
              </button>
              <button type="submit" class="btn btn-primary">
                <svg class="icon"><use href="#i-calendar"></use></svg>
                <span>Perbarui Waktu Selesai</span>
              </button>
            @else
              <div class="alert alert-info" style="width: 100%;">
                <svg class="icon"><use href="#i-lock"></use></svg>
                <div>
                  <strong>Pemilihan telah ditutup</strong>
                  <p>Jadwal pemilihan telah berakhir pada {{ $election->formattedSchedule($election->ends_at) }} dan tidak dapat dibuka kembali (BR-032).</p>
                </div>
              </div>
            @endif
          </div>
        </form>
      </div>
    </section>
  </div>

  {{-- Panel 3: Tautan Halaman Publik (KP-008, OD-014) --}}
  <section class="panel" style="margin-bottom: 18px;">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-external"></use></svg>
          <span>Tautan Halaman Publik</span>
        </h2>
        <p class="panel-sub">Bagikan kedua tautan ini kepada publik/warga sekolah untuk memantau partisipasi dan hasil akhir.</p>
      </div>
    </div>

    <div class="grid-2col" style="padding: 18px 23px;">
      <div class="token-box" style="margin-top: 0;">
        <div>
          <span>1. HALAMAN PUBLIK JUMLAH PEMILIH (TERBUKA KAPAN SAJA)</span>
          <code>{{ route('public.turnout', $election) }}</code>
        </div>
        <button
          type="button"
          class="icon-btn"
          title="Salin tautan Jumlah Pemilih"
          onclick="navigator.clipboard.writeText('{{ route('public.turnout', $election) }}'); window.showToast('Tautan Halaman Jumlah Pemilih disalin.', 'success');"
        >
          <svg class="icon"><use href="#i-copy"></use></svg>
        </button>
        <a href="{{ route('public.turnout', $election) }}" target="_blank" rel="noopener" class="icon-btn" title="Buka di tab baru">
          <svg class="icon"><use href="#i-external"></use></svg>
        </a>
      </div>

      <div class="token-box" style="margin-top: 0;">
        <div>
          <span>2. HALAMAN PUBLIK HASIL (TERBUKA SETELAH WAKTU SELESAI)</span>
          <code>{{ route('public.results', $election) }}</code>
        </div>
        <button
          type="button"
          class="icon-btn"
          title="Salin tautan Hasil Pemilihan"
          onclick="navigator.clipboard.writeText('{{ route('public.results', $election) }}'); window.showToast('Tautan Halaman Hasil disalin.', 'success');"
        >
          <svg class="icon"><use href="#i-copy"></use></svg>
        </button>
        <a href="{{ route('public.results', $election) }}" target="_blank" rel="noopener" class="icon-btn" title="Buka di tab baru">
          <svg class="icon"><use href="#i-external"></use></svg>
        </a>
      </div>
    </div>
  </section>

  {{-- Panel 4: Daftar Pasangan Calon (FR-011) --}}
  <section class="panel">
    <div class="panel-head">
      <div>
        <h2 class="panel-title">
          <svg class="icon"><use href="#i-users"></use></svg>
          <span>Daftar Pasangan Calon ({{ $candidates->count() }})</span>
        </h2>
        <p class="panel-sub">
          @if($phase === 'persiapan')
            Tambahkan atau ubah pasangan calon beserta foto (bucket Supabase), visi, dan misi sebelum pemilihan berlangsung.
          @else
            Daftar pasangan calon telah dikunci karena pemilihan sudah melewati Fase Persiapan (BR-024).
          @endif
        </p>
      </div>

      @if($phase === 'persiapan')
        <button type="button" class="btn btn-primary" wire:click="openAddCandidateModal">
          <svg class="icon"><use href="#i-plus"></use></svg>
          <span>Tambah Paslon</span>
        </button>
      @endif
    </div>

    <div style="padding: 20px 23px;">
      @if($candidates->isEmpty())
        <div class="empty-state">
          <div class="empty-icon"><svg class="icon"><use href="#i-users"></use></svg></div>
          <h3>Belum ada paslon</h3>
          <p>Tambahkan minimal 2 pasangan calon agar jadwal pemilihan dapat diatur.</p>
        </div>
      @else
        <div class="candidate-grid">
          @foreach($candidates as $cand)
            <div class="candidate-card" style="cursor: default;" wire:key="cand-{{ $cand->id }}">
              <div class="candidate-media">
                <img src="{{ $cand->photoUrl() }}" alt="Foto {{ $cand->name }}" class="candidate-photo" />
                <span class="candidate-number">NO. {{ $cand->formattedNumber() }}</span>
              </div>
              <div class="candidate-body">
                <span class="candidate-kicker">PASANGAN CALON {{ $cand->formattedNumber() }}</span>
                <h3 class="candidate-names">{{ $cand->name }}</h3>

                @if($cand->vision)
                  <div class="candidate-vision">
                    <span>VISI</span>
                    <p>{{ $cand->vision }}</p>
                  </div>
                @endif

                @php $missions = $cand->missionItems(); @endphp
                @if(!empty($missions))
                  <ul class="mission-list">
                    @foreach($missions as $m)
                      <li>{{ $m }}</li>
                    @endforeach
                  </ul>
                @endif

                @if($phase === 'persiapan')
                  <div style="margin-top: auto; padding-top: 14px; display: flex; gap: 8px; border-top: 1px solid #eef2f8;">
                    <button type="button" class="btn btn-xs btn-secondary" wire:click="openEditCandidateModal('{{ $cand->id }}')">
                      <svg class="icon"><use href="#i-edit"></use></svg>
                      <span>Ubah</span>
                    </button>
                    <button type="button" class="btn btn-xs btn-danger-ghost" wire:click="promptDeleteCandidate('{{ $cand->id }}')">
                      <svg class="icon"><use href="#i-trash"></use></svg>
                      <span>Hapus</span>
                    </button>
                  </div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </section>

  {{-- Modal Tambah/Ubah Paslon --}}
  @if($showCandidateModal)
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true" aria-labelledby="modal-cand-title">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon"><svg class="icon"><use href="#i-users"></use></svg></span>
            <div>
              <h2 id="modal-cand-title">{{ $editingCandidateId ? 'Ubah Pasangan Calon' : 'Tambah Pasangan Calon' }}</h2>
              <p>Nomor urut dan nama wajib diisi. Foto, visi, dan misi bersifat opsional.</p>
            </div>
          </div>
          <button type="button" class="icon-btn" wire:click="closeCandidateModal" aria-label="Tutup dialog">
            <svg class="icon"><use href="#i-x"></use></svg>
          </button>
        </div>

        <form wire:submit="saveCandidate" novalidate>
          <div class="modal-body">
            <div class="grid-2col" style="gap: 12px;">
              <div class="form-field">
                <label for="cand-num" class="field-label">Nomor Urut <span class="req">*</span></label>
                <input
                  id="cand-num"
                  type="number"
                  min="1"
                  max="99"
                  wire:model="candidateNumber"
                  class="field-input @error('candidateNumber') is-invalid @enderror"
                />
                @error('candidateNumber')
                  <div class="field-error" role="alert">
                    <svg class="icon"><use href="#i-alert"></use></svg>
                    <span>{{ $message }}</span>
                  </div>
                @enderror
              </div>

              <div class="form-field" style="margin-top: 0;">
                <label for="cand-photo" class="field-label">Foto Paslon (Opsional)</label>
                <input
                  id="cand-photo"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  wire:model="candidatePhoto"
                  class="field-input"
                  style="padding: 7px 10px;"
                />
                <div wire:loading wire:target="candidatePhoto" class="field-hint" style="color:var(--brand);">
                  Memuat pratinjau berkas foto...
                </div>
                @error('candidatePhoto')
                  <div class="field-error" role="alert">
                    <svg class="icon"><use href="#i-alert"></use></svg>
                    <span>{{ $message }}</span>
                  </div>
                @enderror
              </div>
            </div>

            <div class="form-field">
              <label for="cand-name" class="field-label">Nama Pasangan Calon <span class="req">*</span></label>
              <input
                id="cand-name"
                type="text"
                wire:model="candidateName"
                class="field-input @error('candidateName') is-invalid @enderror"
                placeholder="Contoh: Raka Pratama & Nadira Putri"
              />
              @error('candidateName')
                <div class="field-error" role="alert">
                  <svg class="icon"><use href="#i-alert"></use></svg>
                  <span>{{ $message }}</span>
                </div>
              @enderror
            </div>

            <div class="form-field">
              <label for="cand-vision" class="field-label">Visi (Opsional)</label>
              <textarea
                id="cand-vision"
                wire:model="candidateVision"
                class="field-textarea"
                style="min-height: 68px;"
                placeholder="Ringkasan visi pasangan calon..."
              ></textarea>
              @error('candidateVision')
                <div class="field-error" role="alert">
                  <svg class="icon"><use href="#i-alert"></use></svg>
                  <span>{{ $message }}</span>
                </div>
              @enderror
            </div>

            <div class="form-field">
              <label for="cand-missions" class="field-label">Daftar Misi (Opsional — satu poin per baris)</label>
              <textarea
                id="cand-missions"
                wire:model="candidateMissionsText"
                class="field-textarea"
                placeholder="Meningkatkan literasi digital sekolah&#10;Mengadakan forum aspirasi bulanan&#10;Memperkuat kolaborasi ekstrakurikuler"
              ></textarea>
              @error('candidateMissionsText')
                <div class="field-error" role="alert">
                  <svg class="icon"><use href="#i-alert"></use></svg>
                  <span>{{ $message }}</span>
                </div>
              @enderror
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" wire:click="closeCandidateModal">Batal</button>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveCandidate,candidatePhoto">
              <span wire:loading.remove wire:target="saveCandidate">Simpan Paslon</span>
              <span wire:loading wire:target="saveCandidate">Menyimpan...</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif

  {{-- Modal Konfirmasi Hapus Paslon --}}
  @if($showDeleteCandidateModal)
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true">
      <div class="modal" style="--modal-width: 480px;">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon" style="color:var(--red);background:var(--red-soft);">
              <svg class="icon"><use href="#i-trash"></use></svg>
            </span>
            <div>
              <h2>Hapus Pasangan Calon?</h2>
              <p>{{ $deletingCandidateName }}</p>
            </div>
          </div>
        </div>
        <div class="modal-body">
          <p class="confirm-message">
            Data pasangan calon ini beserta berkas fotonya akan dihapus dari pemilihan ini.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" wire:click="$set('showDeleteCandidateModal', false)">Batal</button>
          <button type="button" class="btn btn-danger" wire:click="confirmDeleteCandidate">Hapus Paslon</button>
        </div>
      </div>
    </div>
  @endif

  {{-- Modal Konfirmasi Akhiri Pemilihan Lebih Awal --}}
  @if($showEndEarlyModal)
    <div class="modal-backdrop is-visible" role="dialog" aria-modal="true">
      <div class="modal" style="--modal-width: 500px;">
        <div class="modal-header">
          <div class="modal-heading">
            <span class="modal-icon" style="color:var(--red);background:var(--red-soft);">
              <svg class="icon"><use href="#i-lock"></use></svg>
            </span>
            <div>
              <h2>Akhiri Pemilihan Sekarang?</h2>
              <p>Tindakan ini bersifat permanen (BR-032)</p>
            </div>
          </div>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <div>
              <strong>Tidak dapat dibuka kembali</strong>
              <p>Waktu Selesai akan dimajukan ke waktu sekarang. Pemilih yang masih berstatus <code>Sedang memilih</code> tidak dapat lagi mengirim konfirmasi, dan hasil perolehan suara akan langsung dibuka.</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" wire:click="$set('showEndEarlyModal', false)">Batal</button>
          <button type="button" class="btn btn-danger" wire:click="confirmEndEarly">Ya, Akhiri Pemilihan</button>
        </div>
      </div>
    </div>
  @endif
</div>
