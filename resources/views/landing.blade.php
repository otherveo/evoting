@php
  $appName = config('app.name') ?: 'E-Voting';
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
            <span>SISTEM PEMUNGUTAN SUARA ELEKTRONIK</span>
          </div>
        </div>

        <h1 id="landing-title">{{ $appName }} — Pemilihan Sekolah Berbasis QR Token Sekali Pakai</h1>

        <p class="landing-lede">
          Platform pemungutan suara elektronik yang memisahkan identitas pemilih dari pilihan suara secara struktural. Cepat di meja verifikasi, rahasia di bilik suara, dan akuntabel hingga rekapitulasi akhir.
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
  </div>
@endcomponent
