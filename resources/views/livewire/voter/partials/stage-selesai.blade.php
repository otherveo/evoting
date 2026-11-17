{{-- UI-016, FR-015, FR-018, BR-028, AC-020: Tahap 4 Selesai (Tanpa Nama Pemilih & Tanpa Menampilkan Kembali Pilihan) --}}
<div class="view-enter" wire:key="stage-selesai">
  <section class="vote-card success-card" aria-labelledby="success-heading">
    <div class="success-badge" aria-hidden="true">
      <svg class="icon"><use href="#i-check"></use></svg>
    </div>

    <div class="eyebrow" style="justify-content: center;">
      <svg class="icon"><use href="#i-check-circle"></use></svg>
      <span>TAHAP 4 DARI 4 · SUARA TERSIMPAN SAH</span>
    </div>

    {{-- BR-028: Tidak menampilkan nama pemilih dan tidak menampilkan kembali pilihan setelah penyimpanan --}}
    <h1 id="success-heading">Terima Kasih, Suara Anda Telah Tersimpan</h1>
    <p>
      Pilihan Anda pada <strong>{{ $electionName }}</strong> telah tercatat secara anonim di dalam basis data. Demi menjaga kerahasiaan pemungutan suara, halaman ini tidak menampilkan identitas maupun pilihan Anda.
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
