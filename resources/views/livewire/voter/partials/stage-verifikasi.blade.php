{{-- UI-014, FR-015, FR-016, BR-016, BR-028, KP-004: Tahap 1 Verifikasi / Mulai Memilih --}}
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
        {{-- BR-016, BR-028: Halaman tidak menampilkan nama pemilih dan suara dipisahkan dari identitas --}}
        <div class="rule-item">
          <span class="rule-icon"><svg class="icon"><use href="#i-lock"></use></svg></span>
          <div>
            <strong>Sepenuhnya Rahasia &amp; Tanpa Identitas</strong>
            <span>Halaman ini tidak menampilkan nama Anda, dan suara yang disimpan tidak terhubung ke identitas pemilih.</span>
          </div>
        </div>
        {{-- KP-004, BR-013: Sesi memilih berbatas 10 menit pada browser yang sama --}}
        <div class="rule-item">
          <span class="rule-icon"><svg class="icon"><use href="#i-clock"></use></svg></span>
          <div>
            <strong>Sesi Memilih Berbatas 10 Menit</strong>
            <span>Sejak menekan <code>Mulai memilih</code>, Anda memiliki waktu 10 menit pada browser ini untuk mengonfirmasi pilihan.</span>
          </div>
        </div>
        {{-- BR-010: Satu token QR menghasilkan satu suara --}}
        <div class="rule-item">
          <span class="rule-icon"><svg class="icon"><use href="#i-check-circle"></use></svg></span>
          <div>
            <strong>Satu Token QR untuk Satu Suara Sah</strong>
            <span>Setelah dikonfirmasi pada tahap ke-3, pilihan bersifat final dan QR tidak dapat digunakan kembali.</span>
          </div>
        </div>
      </div>

      <x-action-button
        variant="primary"
        size="lg"
        :block="true"
        wire:click="startVoting"
        iconRight="#i-arrow-right"
        loadingText="Memulai sesi..."
      >
        Mulai memilih
      </x-action-button>

      <p class="micro">
        Jika koneksi terputus sebelum konfirmasi, buka kembali QR di browser yang sama dalam waktu 10 menit atau hubungi Operator.
      </p>
    </div>

    {{-- BR-028: Kartu Tiket Anonim (.voter-pass) tanpa menampilkan nama pemilih --}}
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
    <span>{{ config('app.name') ?: 'DigiVoting' }} — Sistem Pemungutan Suara Berbasis QR Token Sekali Pakai</span>
  </p>
</div>
