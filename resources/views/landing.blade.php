@php
  $appName = config('app.name') ?: 'DigiVoting';
  /** @var \App\Models\StaffAccount|null $staffUser */
  $staffUser = auth('staff')->user();
  $ctaUrl = $staffUser
      ? ($staffUser->isAdmin() ? route('admin.operators') : route('operator.elections'))
      : route('login');
  $ctaLabel = $staffUser ? 'Buka Dashboard Staf' : 'Masuk ke Portal Staf';
@endphp

@component('layouts.app', ['title' => 'Beranda'])
  <div class="landing-shell view-enter">
    <section class="landing-card" aria-labelledby="landing-title">
      <div class="landing-main">
        <div class="landing-brand-row">
          <img src="{{ asset('SMPN7-e-voting_logo.png') }}" alt="{{ $appName }}" class="landing-logo" />
          <div class="eyebrow" style="margin: 0;">
            <svg class="icon"><use href="#i-shield"></use></svg>
            <span>SISTEM PEMUNGUTAN SUARA DIGITAL</span>
          </div>
        </div>

        <h1 id="landing-title">{{ $appName }} — Pemilihan Ketua OSIS Berbasis QR Token Sekali Pakai</h1>

        <p class="landing-lede">
          Platform pemungutan suara digital yang memisahkan identitas pemilih dari pilihan suara secara struktural. Cepat di meja verifikasi, rahasia di bilik suara, dan akuntabel hingga rekapitulasi akhir.
        </p>

        <div class="landing-pillars" aria-label="Keunggulan Sistem">
          <div class="landing-pillar">
            <span class="rule-icon"><svg class="icon"><use href="#i-qr"></use></svg></span>
            <div>
              <strong>Token QR Sekali Pakai</strong>
              <span>Diterbitkan langsung oleh Operator setelah verifikasi identitas pemilih.</span>
            </div>
          </div>
          <div class="landing-pillar">
            <span class="rule-icon"><svg class="icon"><use href="#i-lock"></use></svg></span>
            <div>
              <strong>Anonim &amp; Rahasia</strong>
              <span>Pilihan pasangan calon tidak terhubung ke nama maupun identitas pemilih.</span>
            </div>
          </div>
          <div class="landing-pillar">
            <span class="rule-icon"><svg class="icon"><use href="#i-check-circle"></use></svg></span>
            <div>
              <strong>Integritas Terverifikasi</strong>
              <span>Hasil suara terkunci selama pemilihan berlangsung hingga waktu selesai.</span>
            </div>
          </div>
        </div>

        <div class="landing-actions">
          <a href="{{ $ctaUrl }}" wire:navigate class="btn btn-primary btn-lg">
            <svg class="icon"><use href="#i-lock"></use></svg>
            <span>{{ $ctaLabel }}</span>
            <svg class="icon"><use href="#i-arrow-right"></use></svg>
          </a>
          <span class="landing-voter-hint">
            <svg class="icon"><use href="#i-qr"></use></svg>
            <span><strong>Pemilih?</strong> Pindai kode QR dari petugas Operator untuk langsung masuk ke bilik suara.</span>
          </span>
        </div>
      </div>
    </section>

    {{-- Perubahan 5: Section Alur Cara Pakai (Sisi Operator & Sisi Pemilih/Anonim) --}}
    <section class="landing-flow-section" aria-labelledby="landing-flow-title">
      <div class="landing-flow-head">
        <div class="eyebrow" style="justify-content: center;">
          <svg class="icon"><use href="#i-sparkles"></use></svg>
          <span>ALUR PENGGUNAAN PRAKTIS</span>
        </div>
        <h2 id="landing-flow-title">Semudah 5 Langkah — Tanpa Akun Pemilih</h2>
        <p>Dirancang agar panitia dapat menyiapkan pemilihan dalam hitungan menit dan pemilih dapat memberikan suara tanpa hambatan teknis.</p>
      </div>

      <div class="landing-flow-grid">
        <div class="landing-flow-card">
          <div class="landing-flow-card-head">
            <span class="rule-icon"><svg class="icon"><use href="#i-dashboard"></use></svg></span>
            <div>
              <span class="landing-flow-tag">ALUR PETUGAS</span>
              <h3>Sisi Operator Pemilihan</h3>
            </div>
          </div>
          <ol class="landing-step-list">
            <li>
              <span class="landing-step-num">1</span>
              <div>
                <strong>Buat Pemilihan</strong>
                <span>Tentukan nama pemilihan dan kuota maksimum hak pilih (3–10.000).</span>
              </div>
            </li>
            <li>
              <span class="landing-step-num">2</span>
              <div>
                <strong>Tambah Paslon</strong>
                <span>Masukkan minimal 2 pasangan calon beserta foto, nomor urut, visi, dan misi.</span>
              </div>
            </li>
            <li>
              <span class="landing-step-num">3</span>
              <div>
                <strong>Atur Jadwal</strong>
                <span>Tetapkan waktu mulai dan waktu selesai, atau mulai pemilihan secara langsung.</span>
              </div>
            </li>
            <li>
              <span class="landing-step-num">4</span>
              <div>
                <strong>Bagikan Tautan &amp; Terbitkan QR</strong>
                <span>Verifikasi identitas pemilih di meja panitia lalu terbitkan kode QR sekali pakai.</span>
              </div>
            </li>
            <li>
              <span class="landing-step-num">5</span>
              <div>
                <strong>Pantau &amp; Lihat Rekap</strong>
                <span>Pantau status sesi secara langsung dan buka rekapitulasi hasil saat pemilihan berakhir.</span>
              </div>
            </li>
          </ol>
        </div>

        <div class="landing-flow-card is-voter-flow">
          <div class="landing-flow-card-head">
            <span class="rule-icon"><svg class="icon"><use href="#i-qr"></use></svg></span>
            <div>
              <span class="landing-flow-tag">ALUR BILIK SUARA</span>
              <h3>Sisi Anonim / Pemilih</h3>
            </div>
          </div>
          <ol class="landing-step-list">
            <li>
              <span class="landing-step-num">1</span>
              <div>
                <strong>Buka Tautan atau Scan QR</strong>
                <span>Pindai kode QR sekali pakai dari Operator menggunakan kamera HP atau buka tautan akses.</span>
              </div>
            </li>
            <li>
              <span class="landing-step-num">2</span>
              <div>
                <strong>Verifikasi Tiket Anonim</strong>
                <span>Periksa informasi pemilihan pada halaman tiket suara tanpa menampilkan identitas pribadi.</span>
              </div>
            </li>
            <li>
              <span class="landing-step-num">3</span>
              <div>
                <strong>Mulai Memilih</strong>
                <span>Buka surat suara digital (sesi aktif 10 menit) dan pelajari visi-misi tiap pasangan calon.</span>
              </div>
            </li>
            <li>
              <span class="landing-step-num">4</span>
              <div>
                <strong>Konfirmasi Pilihan</strong>
                <span>Tinjau ulang pasangan calon yang dipilih sebelum mengunci suara secara permanen.</span>
              </div>
            </li>
            <li>
              <span class="landing-step-num">5</span>
              <div>
                <strong>Selesai</strong>
                <span>Suara tersimpan secara anonim dan token QR otomatis dikunci agar tidak dapat dipakai ulang.</span>
              </div>
            </li>
          </ol>
        </div>
      </div>
    </section>
  </div>
@endcomponent
