<div>
  @if($stage !== 'kendala')
    {{-- Indikator 4 Langkah Linear (UI.md §1.1) --}}
    <ol class="vote-steps" aria-label="Tahapan Memilih">
      <li class="{{ $stepIndex === 1 ? 'is-active' : ($stepIndex > 1 ? 'is-done' : '') }}">
        <span class="step-dot">
          @if($stepIndex > 1)
            <svg class="icon"><use href="#i-check"></use></svg>
          @else
            1
          @endif
        </span>
        <span class="step-label">Verifikasi</span>
      </li>
      <li class="{{ $stepIndex === 2 ? 'is-active' : ($stepIndex > 2 ? 'is-done' : '') }}">
        <span class="step-dot">
          @if($stepIndex > 2)
            <svg class="icon"><use href="#i-check"></use></svg>
          @else
            2
          @endif
        </span>
        <span class="step-label">Memilih</span>
      </li>
      <li class="{{ $stepIndex === 3 ? 'is-active' : ($stepIndex > 3 ? 'is-done' : '') }}">
        <span class="step-dot">
          @if($stepIndex > 3)
            <svg class="icon"><use href="#i-check"></use></svg>
          @else
            3
          @endif
        </span>
        <span class="step-label">Konfirmasi</span>
      </li>
      <li class="{{ $stepIndex === 4 ? 'is-active' : '' }}">
        <span class="step-dot">4</span>
        <span class="step-label">Selesai</span>
      </li>
    </ol>
  @endif

  {{-- TAHAP 1: VERIFIKASI / MULAI MEMILIH (UI-014) --}}
  @if($stage === 'verifikasi')
    <div class="view-enter" wire:key="stage-verifikasi">
      <section class="vote-card intro-layout" aria-labelledby="intro-heading">
        <div class="intro-main">
          <div class="eyebrow">
            <svg class="icon"><use href="#i-ballot"></use></svg>
            <span>TAHAP 1 DARI 4 · BILIK SUARA SIAP</span>
          </div>
          <h1 id="intro-heading">{{ $electionName }}</h1>
          <p class="lede">
            Token QR Anda telah tervalidasi. Tekan tombol <strong>Mulai memilih</strong> di bawah untuk membuka surat suara dan menentukan satu pasangan calon pilihan Anda.
          </p>

          <div class="intro-rules">
            <div class="rule-item">
              <span class="rule-icon"><svg class="icon"><use href="#i-lock"></use></svg></span>
              <div>
                <strong>Sepenuhnya Rahasia &amp; Tanpa Identitas</strong>
                <span>Halaman ini tidak menampilkan nama Anda, dan suara yang disimpan tidak terhubung ke identitas pemilih (BR-016, BR-028).</span>
              </div>
            </div>
            <div class="rule-item">
              <span class="rule-icon"><svg class="icon"><use href="#i-clock"></use></svg></span>
              <div>
                <strong>Sesi Memilih Berbatas 10 Menit</strong>
                <span>Sejak menekan <code>Mulai memilih</code>, Anda memiliki waktu 10 menit pada browser ini untuk mengonfirmasi pilihan (KP-004).</span>
              </div>
            </div>
            <div class="rule-item">
              <span class="rule-icon"><svg class="icon"><use href="#i-check-circle"></use></svg></span>
              <div>
                <strong>Satu Token QR untuk Satu Suara Sah</strong>
                <span>Setelah dikonfirmasi pada tahap ke-3, pilihan bersifat final dan QR tidak dapat digunakan kembali.</span>
              </div>
            </div>
          </div>

          <button
            type="button"
            class="btn btn-primary btn-lg btn-block"
            wire:click="startVoting"
            wire:loading.attr="disabled"
          >
            <span wire:loading.remove wire:target="startVoting" style="display:inline-flex;align-items:center;gap:8px;">
              <span>Mulai memilih</span>
              <svg class="icon"><use href="#i-arrow-right"></use></svg>
            </span>
            <span wire:loading wire:target="startVoting" style="display:inline-flex;align-items:center;gap:8px;">
              <span class="spinner" aria-hidden="true"></span>
              <span>Memulai sesi...</span>
            </span>
          </button>

          <p class="micro">
            Jika koneksi terputus sebelum konfirmasi, buka kembali QR di browser yang sama dalam waktu 10 menit atau hubungi Operator.
          </p>
        </div>

        {{-- Kartu Tiket Anonim (.voter-pass tanpa nama pemilih sesuai BR-028) --}}
        <aside class="voter-pass" aria-label="Status Tiket Suara Anonim">
          <div class="pass-top">
            <span class="pass-brand">
              <svg class="icon"><use href="#i-shield"></use></svg>
              <span>TIKET SUARA ANONIM</span>
            </span>
            <span class="pass-live">TERVERIFIKASI</span>
          </div>

          <div class="pass-name">Hak Pilih Sekali Pakai</div>
          <div class="pass-sub">Identitas pemilih tidak ditampilkan demi kerahasiaan bilik suara</div>

          <dl class="pass-grid">
            <div>
              <dt>Status Hak Pilih</dt>
              <dd>
                <span class="status-badge">
                  <span class="status-dot"></span>
                  <span>Belum memilih</span>
                </span>
              </dd>
            </div>
            <div>
              <dt>Durasi Sesi</dt>
              <dd>10 Menit</dd>
            </div>
            <div>
              <dt>Jaminan Privasi</dt>
              <dd>Suara Terpisah dari Identitas</dd>
            </div>
          </dl>

          <div class="pass-stripe" aria-hidden="true"></div>
          <div class="pass-foot">
            <svg class="icon"><use href="#i-lock"></use></svg>
            <span>Dijaga secara kriptografis dengan SHA-256 satu arah</span>
          </div>
        </aside>
      </section>

      <p class="vote-footnote">
        <svg class="icon"><use href="#i-shield"></use></svg>
        <span>SuaraSekolah — Sistem E-Voting Berbasis QR Token Sekali Pakai</span>
      </p>
    </div>
  @endif

  {{-- TAHAP 2: MEMILIH PASLON (UI-015 bagian 1) --}}
  @if($stage === 'memilih')
    <div class="ballot-shell view-enter" wire:key="stage-memilih">
      @if($isResumedSession)
        <div class="resume-banner" role="status">
          <svg class="icon"><use href="#i-info"></use></svg>
          <div>
            <strong>Sesi memilih Anda dilanjutkan.</strong> Silakan pilih pasangan calon dan tekan konfirmasi sebelum batas waktu 10 menit berakhir ({{ $sessionExpiresLabel }}).
          </div>
        </div>
      @endif

      <div class="ballot-head">
        <div>
          <div class="eyebrow">
            <svg class="icon"><use href="#i-ballot"></use></svg>
            <span>TAHAP 2 DARI 4 · SURAT SUARA ELEKTRONIK</span>
          </div>
          <h1>Pilih Satu Pasangan Calon</h1>
          <p class="lede">
            Ketuk kartu pasangan calon pilihan Anda, lalu tekan tombol <strong>Lanjut ke Konfirmasi</strong> di bagian bawah layar.
          </p>
        </div>

        @if($sessionExpiresLabel)
          <div class="ballot-voter" title="Batas waktu sesi memilih 10 menit">
            <span class="avatar" style="background:var(--amber-soft);color:var(--amber);border-color:#f4dfbd;">
              <svg class="icon" style="width:15px;height:15px;"><use href="#i-clock"></use></svg>
            </span>
            <div>
              <strong>Sesi Aktif (10 Menit)</strong>
              <small>Batas: {{ $sessionExpiresLabel }}</small>
            </div>
          </div>
        @endif
      </div>

      <div class="candidate-grid" role="radiogroup" aria-label="Daftar Pasangan Calon">
        @foreach($candidates as $cand)
          @php
            $isSelected = $selectedCandidateId === $cand->id;
            $missions = $cand->missionItems();
          @endphp
          <button
            type="button"
            role="radio"
            aria-checked="{{ $isSelected ? 'true' : 'false' }}"
            class="candidate-card {{ $isSelected ? 'is-selected' : '' }}"
            wire:click="selectCandidate('{{ $cand->id }}')"
            wire:key="ballot-cand-{{ $cand->id }}"
          >
            <div class="candidate-media">
              <img src="{{ $cand->photoUrl() }}" alt="Foto Paslon {{ $cand->formattedNumber() }} — {{ $cand->name }}" class="candidate-photo" />
              <span class="candidate-number">NO. {{ $cand->formattedNumber() }}</span>
              <span class="candidate-check" aria-hidden="true">
                <svg class="icon"><use href="#i-check"></use></svg>
              </span>
            </div>

            <div class="candidate-body">
              <span class="candidate-kicker">PASANGAN CALON {{ $cand->formattedNumber() }}</span>
              <h2 class="candidate-names">{{ $cand->name }}</h2>

              @if($cand->vision)
                <div class="candidate-vision">
                  <span>VISI</span>
                  <p>{{ $cand->vision }}</p>
                </div>
              @endif

              @if(!empty($missions))
                <ul class="mission-list">
                  @foreach($missions as $m)
                    <li>{{ $m }}</li>
                  @endforeach
                </ul>
              @endif

              <div class="candidate-select-hint">
                <svg class="icon"><use href="#i-check-circle"></use></svg>
                <span>Dipilih untuk dikonfirmasi</span>
              </div>
            </div>
          </button>
        @endforeach
      </div>

      <div class="ballot-bar">
        <div class="ballot-bar-info">
          <span>PILIHAN SEMENTARA (BELUM TERSIMPAN)</span>
          @if($selectedCandidate)
            <strong>Paslon {{ $selectedCandidate->formattedNumber() }} — {{ $selectedCandidate->name }}</strong>
          @else
            <strong>Belum ada pasangan calon yang dipilih</strong>
          @endif
        </div>

        <button
          type="button"
          id="btn-to-confirm"
          class="btn btn-primary btn-lg"
          wire:click="proceedToConfirm"
          {{ !$selectedCandidate ? 'disabled' : '' }}
        >
          <span>Lanjut ke Konfirmasi</span>
          <svg class="icon"><use href="#i-arrow-right"></use></svg>
        </button>
      </div>

      <p class="session-note">
        <svg class="icon"><use href="#i-lock"></use></svg>
        <span>Pilihan Anda belum disimpan ke basis data sampai Anda menekan tombol <strong>Konfirmasi pilihan</strong> pada langkah berikutnya.</span>
      </p>
    </div>
  @endif

  {{-- TAHAP 3: KONFIRMASI PILIHAN (UI-015 bagian 2) --}}
  @if($stage === 'konfirmasi' && $selectedCandidate)
    <div class="view-enter" wire:key="stage-konfirmasi">
      <section class="vote-card confirm-card" aria-labelledby="confirm-heading">
        <div class="confirm-top">
          <div class="confirm-icon" aria-hidden="true">
            <svg class="icon"><use href="#i-shield"></use></svg>
          </div>
          <div class="eyebrow" style="justify-content: center;">
            <svg class="icon"><use href="#i-check-circle"></use></svg>
            <span>TAHAP 3 DARI 4 · PERIKSA KEMBALI PILIHAN ANDA</span>
          </div>
          <h1 id="confirm-heading">Konfirmasi Pilihan Suara</h1>
          <p>
            Pastikan pasangan calon di bawah ini sudah sesuai dengan pilihan Anda sebelum menyimpan suara secara permanen.
          </p>
        </div>

        <div class="confirm-summary">
          <img
            src="{{ $selectedCandidate->photoUrl() }}"
            alt="Foto {{ $selectedCandidate->name }}"
            class="confirm-photo"
          />
          <div class="confirm-candidate">
            <span>PASANGAN CALON NOMOR URUT {{ $selectedCandidate->formattedNumber() }}</span>
            <h2>{{ $selectedCandidate->name }}</h2>
            @if($selectedCandidate->vision)
              <p>{{ $selectedCandidate->vision }}</p>
            @else
              <p>Pemilihan: {{ $electionName }}</p>
            @endif
          </div>
        </div>

        <div class="alert alert-warning" role="alert">
          <svg class="icon"><use href="#i-alert"></use></svg>
          <div>
            <strong>Tindakan ini tidak dapat dibatalkan</strong>
            <p>Setelah Anda menekan <strong>Konfirmasi pilihan</strong>, suara akan disimpan secara anonim dan status hak pilih berubah menjadi <code>Sudah memilih</code>.</p>
          </div>
        </div>

        <div class="confirm-actions">
          <button
            type="button"
            class="btn btn-secondary btn-lg"
            wire:click="backToBallot"
            wire:loading.attr="disabled"
          >
            <svg class="icon"><use href="#i-arrow-left"></use></svg>
            <span>Ubah pilihan</span>
          </button>

          <button
            type="button"
            class="btn btn-primary btn-lg"
            wire:click="confirmVote"
            wire:loading.attr="disabled"
          >
            <span wire:loading.remove wire:target="confirmVote" style="display:inline-flex;align-items:center;gap:8px;">
              <svg class="icon"><use href="#i-check-circle"></use></svg>
              <span>Konfirmasi pilihan</span>
            </span>
            <span wire:loading wire:target="confirmVote" style="display:inline-flex;align-items:center;gap:8px;">
              <span class="spinner" aria-hidden="true"></span>
              <span>Menyimpan suara...</span>
            </span>
          </button>
        </div>
      </section>
    </div>
  @endif

  {{-- TAHAP 4: SELESAI (UI-016 — Tanpa nama pemilih & tanpa pilihan sesuai BR-028) --}}
  @if($stage === 'selesai')
    <div class="view-enter" wire:key="stage-selesai">
      <section class="vote-card success-card" aria-labelledby="success-heading">
        <div class="success-badge" aria-hidden="true">
          <svg class="icon"><use href="#i-check"></use></svg>
        </div>

        <div class="eyebrow" style="justify-content: center;">
          <svg class="icon"><use href="#i-check-circle"></use></svg>
          <span>TAHAP 4 DARI 4 · SUARA TERSIMPAN SAH</span>
        </div>

        <h1 id="success-heading">Terima Kasih, Suara Anda Telah Tersimpan</h1>
        <p>
          Pilihan Anda pada <strong>{{ $electionName }}</strong> telah tercatat secara anonim di dalam basis data. Demi menjaga kerahasiaan pemungutan suara, halaman ini tidak menampilkan identitas maupun pilihan Anda (BR-028).
        </p>

        <div class="success-meta">
          <div>
            <span>STATUS HAK PILIH</span>
            <strong>Sudah memilih (Final)</strong>
          </div>
          <div>
            <span>KERAHASIAAN SUARA</span>
            <strong>Anonim &amp; Terpisah dari Identitas</strong>
          </div>
        </div>

        <div class="alert alert-success" style="text-align: left;">
          <svg class="icon"><use href="#i-shield"></use></svg>
          <div>
            <strong>Token QR ini telah selesai digunakan</strong>
            <p>Anda kini dapat menutup halaman browser ini. Apabila perangkat ini digunakan bersama di bilik suara, silakan kembalikan perangkat kepada petugas.</p>
          </div>
        </div>

        @if($electionId)
          <div class="success-actions">
            <a href="{{ route('public.turnout', $electionId) }}" class="btn btn-secondary">
              <svg class="icon"><use href="#i-users"></use></svg>
              <span>Lihat Halaman Publik Jumlah Pemilih</span>
            </a>
          </div>
        @endif
      </section>
    </div>
  @endif

  {{-- LAYAR KENDALA / INFORMASI (UI-017) --}}
  @if($stage === 'kendala')
    <div class="view-enter" wire:key="stage-kendala">
      <section class="state-page" aria-labelledby="trouble-heading">
        <div class="state-icon {{ in_array($errorCode, ['invalid_token', 'wrong_browser'], true) ? 'is-danger' : '' }}" aria-hidden="true">
          <svg class="icon">
            <use href="{{ $errorCode === 'session_expired' ? '#i-clock' : ($errorCode === 'wrong_phase' ? '#i-calendar' : '#i-alert') }}"></use>
          </svg>
        </div>

        <div class="eyebrow">
          <svg class="icon"><use href="#i-info"></use></svg>
          <span>INFORMASI KENDALA AKSES VOTING</span>
        </div>

        <h1 id="trouble-heading">{{ $errorTitle }}</h1>
        <p>{{ $errorDescription }}</p>

        <dl class="state-details">
          <div>
            <dt>Pemilihan</dt>
            <dd>{{ $electionName }}</dd>
          </div>
          <div>
            <dt>Langkah Berikutnya</dt>
            <dd>{{ $errorNextStep }}</dd>
          </div>
        </dl>

        <div class="privacy-callout" style="text-align: left;">
          <svg class="icon"><use href="#i-shield"></use></svg>
          <div>
            Tunjukkan layar ini kepada <strong>Operator</strong> di meja verifikasi. Operator dapat memeriksa status hak pilih Anda dan melakukan <strong>Reset Sesi</strong> atau <strong>Terbitkan Ulang QR</strong> sesuai prosedur.
          </div>
        </div>
      </section>
    </div>
  @endif
</div>
