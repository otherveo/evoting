{{-- UI-019, FR-022, BR-026, KP-008, AC-025: Halaman Publik Hasil Pemilihan --}}
<div class="container page public-page">
  @if($phase !== 'ditutup')
    <x-locked-results-card
      eyebrow="HALAMAN PUBLIK HASIL PEMILIHAN"
      heading="Hasil Pemilihan Belum Tersedia"
      :description="'Hasil perolehan suara untuk <strong>' . e($election->name) . '</strong> hanya dibuka setelah masa pemilihan berakhir pada Waktu Selesai di bawah ini.'"
      :scheduleLabel="$election->formattedSchedule($election->ends_at)"
      :footnote="'Waktu pengecekan: ' . $loadedAtLabel . ' · Tidak ada angka suara yang ditampilkan sebelum Waktu Selesai.'"
      refreshAction="refreshSnapshot"
      :secondaryUrl="route('public.turnout', $election)"
      secondaryLabel="Lihat Jumlah Pemilih"
    />
  @else
    <div class="result-summary-grid" style="margin-top: 0; margin-bottom: 16px;">
      <div class="result-summary-card">
        <span class="result-summary-icon is-teal"><svg class="icon"><use href="#i-check-circle"></use></svg></span>
        <div>
          <strong>{{ $totalVotesCast }} Suara</strong>
          <span>Total Suara Sah Masuk</span>
        </div>
      </div>
      <div class="result-summary-card">
        <span class="result-summary-icon"><svg class="icon"><use href="#i-users"></use></svg></span>
        <div>
          <strong>{{ count($resultsData ?? []) }} Paslon</strong>
          <span>Peserta Pemilihan</span>
        </div>
      </div>
      <div class="result-summary-card">
        <span class="result-summary-icon is-amber"><svg class="icon"><use href="#i-calendar"></use></svg></span>
        <div>
          <strong>Ditutup</strong>
          <span>{{ $election->formattedSchedule($election->ends_at) }}</span>
        </div>
      </div>
    </div>

    <x-results-breakdown
      :election="$election"
      :resultsData="$resultsData ?? []"
      :leadingCandidates="$leadingCandidates"
      :totalVotesCast="$totalVotesCast"
      eyebrow="HASIL PEMILIHAN PUBLIK"
      subtitle="Masa pemungutan suara telah berakhir. Berikut adalah rekapitulasi perolehan suara sah untuk setiap pasangan calon yang dihitung dari data suara anonim."
      metaRightLabel="Dimuat pada"
      :metaRightValue="$loadedAtLabel"
      totalSubtext="Akumulasi seluruh suara anonim yang masuk"
      winnerNote="Rekapitulasi sistem dari data suara anonim; penetapan resmi mengikuti berita acara panitia."
      refreshAction="refreshSnapshot"
    />

    {{-- NFR-005, BR-016: Suara disimpan anonim terpisah dari identitas --}}
    <div class="public-note">
      <svg class="icon"><use href="#i-lock"></use></svg>
      <span>
        Seluruh suara disimpan secara anonim pada tabel terpisah tanpa waktu pemungutan dan tanpa relasi ke daftar hak pilih.
      </span>
    </div>
  @endif
</div>
