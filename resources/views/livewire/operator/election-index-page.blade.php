{{-- UI-007, FR-008: Halaman Daftar Pemilihan Operator + Wizard Sekuensial 4 Langkah + Hapus Pemilihan --}}
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
      <x-action-button
        variant="primary"
        icon="#i-plus"
        wire:click="openCreateModal"
        loadingText="Membuka..."
      >
        Buat Pemilihan Baru
      </x-action-button>
    </div>
  </div>

  @if($elections->isEmpty())
    <section class="panel">
      <div class="empty-state">
        <div class="empty-icon"><svg class="icon"><use href="#i-ballot"></use></svg></div>
        <h3>Belum ada pemilihan</h3>
        <p>Buat pemilihan pertama Anda melalui panduan langkah demi langkah (Identitas, Paslon, Jadwal, dan Tautan).</p>
        <div style="margin-top: 16px;">
          <x-action-button
            variant="primary"
            icon="#i-plus"
            wire:click="openCreateModal"
            loadingText="Membuka..."
          >
            Buat Pemilihan Pertama
          </x-action-button>
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
            <a href="{{ route('operator.elections.monitor', $el) }}" wire:navigate class="btn btn-sm btn-primary">
              <svg class="icon"><use href="#i-users"></use></svg>
              <span>Pantau &amp; Registrasi QR</span>
            </a>
            <a href="{{ route('operator.elections.detail', $el) }}" wire:navigate class="btn btn-sm btn-secondary">
              <svg class="icon"><use href="#i-calendar"></use></svg>
              <span>Konfigurasi &amp; Paslon</span>
            </a>
            <a href="{{ route('operator.elections.recap', $el) }}" wire:navigate class="btn btn-sm btn-soft">
              <svg class="icon"><use href="#i-chart"></use></svg>
              <span>Rekapitulasi</span>
            </a>
            @if($fase !== 'berlangsung')
              <x-action-button
                variant="danger-ghost"
                size="sm"
                icon="#i-trash"
                wire:click="promptDeleteElection('{{ $el->id }}')"
                loadingText="Membuka..."
              >
                Hapus
              </x-action-button>
            @endif
          </div>
        </article>
      @endforeach
    </div>
  @endif

  {{-- Perubahan 2.2 & 2.3: Modal Wizard Sekuensial 4 Langkah Pembuatan Pemilihan (Client-Side Dulu) --}}
  @if($showCreateModal)
    <x-modal
      title="Panduan Pembuatan Pemilihan Baru"
      subtitle="Data tidak disimpan ke server sampai Anda menyelesaikan Langkah 3. Keluar di tengah jalan akan membatalkan seluruh isian."
      icon="#i-ballot"
      maxWidth="720px"
      closeAction="closeCreateModal"
    >
      <div
        x-data="{
          step: 1,
          elName: @js($name),
          elMaxRights: @js($maxVoterRights),
          candidates: [
            { number: 1, name: '', vision: '', missions: '' }
          ],
          startsAt: @js($wizardStartsAt),
          endsAt: @js($wizardEndsAt),
          confirmStartNow: false,
          savingWizard: false,
          step1Error: '',
          step2Error: '',
          step3Error: '',
          get canProceedStep1() {
            const n = (this.elName || '').trim();
            const m = Number(this.elMaxRights);
            return n.length >= 3 && Number.isInteger(m) && m >= 3 && m <= 10000;
          },
          get canProceedStep2() {
            if (!Array.isArray(this.candidates) || this.candidates.length < 2) return false;
            const nums = new Set();
            for (const c of this.candidates) {
              const nameOk = String(c.name || '').trim().length >= 2;
              const num = Number(c.number);
              if (!nameOk || !Number.isInteger(num) || num < 1 || nums.has(num)) return false;
              nums.add(num);
            }
            return true;
          },
          goStep2() {
            this.step1Error = '';
            const n = (this.elName || '').trim();
            const m = Number(this.elMaxRights);
            if (n.length < 3) {
              this.step1Error = 'Nama pemilihan wajib diisi minimal 3 karakter.';
              window.showToast('Gagal', 'error');
              return;
            }
            if (!Number.isInteger(m) || m < 3 || m > 10000) {
              this.step1Error = 'Batas maksimum hak pilih minimal 3 dan maksimal 10000.';
              window.showToast('Gagal', 'error');
              return;
            }
            this.step = 2;
          },
          addCandidateCard() {
            const maxNum = this.candidates.reduce((acc, c) => Math.max(acc, Number(c.number) || 0), 0);
            this.candidates.push({
              number: maxNum + 1,
              name: '',
              vision: '',
              missions: ''
            });
          },
          removeCandidateCard(idx) {
            if (this.candidates.length <= 1) return;
            this.candidates.splice(idx, 1);
          },
          goStep3() {
            this.step2Error = '';
            if (!this.canProceedStep2) {
              this.step2Error = 'Tambahkan minimal 2 pasangan calon dengan nomor urut unik dan nama lengkap.';
              window.showToast('Gagal', 'error');
              return;
            }
            this.step = 3;
          },
          submitWizard(startNow = false) {
            if (this.savingWizard) return;
            this.step3Error = '';
            if (!this.startsAt || !this.endsAt) {
              this.step3Error = 'Waktu Mulai dan Waktu Selesai wajib diisi.';
              window.showToast('Gagal', 'error');
              return;
            }
            if (!startNow && this.endsAt <= this.startsAt) {
              this.step3Error = 'Waktu Selesai harus berada setelah Waktu Mulai.';
              window.showToast('Gagal', 'error');
              return;
            }
            this.savingWizard = true;
            $wire.completeWizard(
              this.elName,
              Number(this.elMaxRights),
              this.candidates,
              this.startsAt,
              this.endsAt,
              startNow
            ).then((ok) => {
              this.savingWizard = false;
              this.confirmStartNow = false;
              if (ok) {
                this.step = 4;
              }
            }).catch(() => {
              this.savingWizard = false;
              this.confirmStartNow = false;
            });
          }
        }"
      >
        <div class="modal-body">
          {{-- Tombol keluar sekuensial di bagian atas popup --}}
          <div class="wizard-top-bar" x-show="step < 4">
            <ol class="wizard-steps" aria-label="Tahapan Pembuatan Pemilihan">
              <li :class="{ 'is-active': step === 1, 'is-done': step > 1 }">
                <span class="section-num-badge">1</span>
                <span>Identitas</span>
              </li>
              <li :class="{ 'is-active': step === 2, 'is-done': step > 2 }">
                <span class="section-num-badge">2</span>
                <span>Paslon</span>
              </li>
              <li :class="{ 'is-active': step === 3, 'is-done': step > 3 }">
                <span class="section-num-badge">3</span>
                <span>Jadwal</span>
              </li>
              <li :class="{ 'is-active': step === 4 }">
                <span class="section-num-badge">4</span>
                <span>Tautan</span>
              </li>
            </ol>

            <button
              type="button"
              class="btn btn-xs btn-secondary"
              wire:click="closeCreateModal"
              x-bind:disabled="savingWizard"
            >
              <span>Saya ingin melengkapinya nanti</span>
            </button>
          </div>

          @if($errors->any())
            <div class="alert alert-danger" role="alert" style="margin-bottom: 14px;">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <div>
                <strong>Periksa kembali isian Anda</strong>
                <p>{{ $errors->first() }}</p>
              </div>
            </div>
          @endif

          {{-- LANGKAH 1: Identitas & Hak Pilih --}}
          <div x-show="step === 1" class="wizard-step-card">
            <div class="wizard-card-head">
              <span class="section-num-badge is-large">1</span>
              <div>
                <h3>Identitas Pemilihan &amp; Kuota Hak Pilih</h3>
                <p>Tentukan nama penyelenggaraan pemilihan dan batas maksimum pemilih yang dapat didaftarkan.</p>
              </div>
            </div>

            <div class="form-field">
              <label for="wiz-name" class="field-label">Nama Pemilihan <span class="req">*</span></label>
              <input
                id="wiz-name"
                type="text"
                x-model="elName"
                class="field-input"
                placeholder="Contoh: Pemilihan Ketua & Wakil Ketua OSIS 2026/2027"
                autofocus
              />
            </div>

            <div class="form-field">
              <label for="wiz-max" class="field-label">Batas Maksimum Hak Pilih (3 – 10.000) <span class="req">*</span></label>
              <input
                id="wiz-max"
                type="number"
                min="3"
                max="10000"
                x-model.number="elMaxRights"
                class="field-input"
              />
              <p class="field-hint">Batas maksimum hak pilih minimal 3 dan maksimal 10000.</p>
            </div>

            <div class="field-error" role="alert" x-show="step1Error !== ''" x-cloak>
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span x-text="step1Error"></span>
            </div>
          </div>

          {{-- LANGKAH 2: Data Paslon (Minimal 2 Paslon) --}}
          <div x-show="step === 2" x-cloak class="wizard-step-card">
            <div class="wizard-card-head">
              <span class="section-num-badge is-large">2</span>
              <div>
                <h3>Data Pasangan Calon (Minimal 2 Paslon)</h3>
                <p>Tambahkan minimal 2 pasangan calon agar tombol <strong>Lanjut ke Jadwal</strong> dapat ditekan.</p>
              </div>
            </div>

            <div class="wizard-candidates-stack">
              <template x-for="(cand, idx) in candidates" :key="idx">
                <div class="wizard-candidate-box">
                  <div class="wizard-candidate-box-head">
                    <strong>Pasangan Calon #<span x-text="idx + 1"></span></strong>
                    <button
                      type="button"
                      class="btn btn-xs btn-danger-ghost"
                      x-show="candidates.length > 1"
                      x-on:click="removeCandidateCard(idx)"
                    >
                      <svg class="icon"><use href="#i-trash"></use></svg>
                      <span>Hapus Card</span>
                    </button>
                  </div>

                  <div class="grid-2col" style="gap: 12px;">
                    <div class="form-field" style="margin-top: 0;">
                      <label class="field-label">Nomor Urut <span class="req">*</span></label>
                      <input
                        type="number"
                        min="1"
                        max="99"
                        x-model.number="cand.number"
                        class="field-input"
                      />
                    </div>

                    <div class="form-field" style="margin-top: 0;">
                      <label class="field-label">Foto Paslon (Opsional)</label>
                      <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        x-on:change="$wire.upload('wizardPhotos.' + idx, $event.target.files[0])"
                        class="field-input"
                        style="padding: 7px 10px;"
                      />
                    </div>
                  </div>

                  <div class="form-field">
                    <label class="field-label">Nama Pasangan Calon <span class="req">*</span></label>
                    <input
                      type="text"
                      x-model="cand.name"
                      class="field-input"
                      placeholder="Contoh: Raka Pratama & Nadira Putri"
                    />
                  </div>

                  <div class="form-field">
                    <label class="field-label">Visi (Opsional)</label>
                    <textarea
                      x-model="cand.vision"
                      class="field-textarea"
                      style="min-height: 58px;"
                      placeholder="Ringkasan visi pasangan calon..."
                    ></textarea>
                  </div>

                  <div class="form-field">
                    <label class="field-label">Daftar Misi (Opsional — satu poin per baris)</label>
                    <textarea
                      x-model="cand.missions"
                      class="field-textarea"
                      style="min-height: 68px;"
                      placeholder="Meningkatkan literasi digital sekolah&#10;Mengadakan forum aspirasi bulanan"
                    ></textarea>
                  </div>
                </div>
              </template>
            </div>

            <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
              <button type="button" class="btn btn-sm btn-soft" x-on:click="addCandidateCard()">
                <svg class="icon"><use href="#i-plus"></use></svg>
                <span>+ Tambah Paslon</span>
              </button>
              <span class="field-hint" x-text="'Terisi: ' + candidates.length + ' paslon (wajib minimal 2 paslon)'"></span>
            </div>

            <div class="field-error" role="alert" x-show="step2Error !== ''" x-cloak style="margin-top: 8px;">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span x-text="step2Error"></span>
            </div>
          </div>

          {{-- LANGKAH 3: Jadwal Pemilihan --}}
          <div x-show="step === 3" x-cloak class="wizard-step-card">
            <div class="wizard-card-head">
              <span class="section-num-badge is-large">3</span>
              <div>
                <h3>Jadwal Pelaksanaan Pemilihan</h3>
                <p>Simpan jadwal untuk dimulai otomatis nanti, atau tekan <strong>Mulai Sekarang</strong> bila pemilihan langsung dibuka saat ini.</p>
              </div>
            </div>

            <div class="grid-2col" style="gap: 14px;">
              <div class="form-field" style="margin-top: 0;">
                <label for="wiz-start" class="field-label">Waktu Mulai <span class="req">*</span></label>
                <input
                  id="wiz-start"
                  type="datetime-local"
                  x-model="startsAt"
                  class="field-input"
                />
              </div>

              <div class="form-field" style="margin-top: 0;">
                <label for="wiz-end" class="field-label">Waktu Selesai <span class="req">*</span></label>
                <input
                  id="wiz-end"
                  type="datetime-local"
                  x-model="endsAt"
                  class="field-input"
                />
              </div>
            </div>

            <div class="field-error" role="alert" x-show="step3Error !== ''" x-cloak style="margin-top: 10px;">
              <svg class="icon"><use href="#i-alert"></use></svg>
              <span x-text="step3Error"></span>
            </div>

            {{-- Popup konfirmasi tambahan bila tombol "Mulai Sekarang" ditekan --}}
            <div class="inline-alert inline-alert-warning" x-show="confirmStartNow" x-cloak style="margin-top: 14px;">
              <strong>
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>Konfirmasi Mulai Pemilihan Sekarang?</span>
              </strong>
              <p>
                Pemilihan akan langsung masuk ke <strong>Fase Berlangsung</strong> begitu disimpan, sehingga daftar pasangan calon langsung dikunci dan pendaftaran QR pemilih dibuka.
              </p>
              <div style="margin-top: 10px; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn btn-xs btn-secondary" x-on:click="confirmStartNow = false" x-bind:disabled="savingWizard">
                  Batal
                </button>
                <button
                  type="button"
                  class="btn btn-xs btn-primary"
                  x-on:click="submitWizard(true)"
                  x-bind:disabled="savingWizard"
                  x-bind:data-loading="savingWizard ? 'true' : null"
                >
                  <span class="btn-label-idle" x-show="!savingWizard">
                    <svg class="icon"><use href="#i-check"></use></svg>
                    <span>Ya, Buat &amp; Mulai Sekarang</span>
                  </span>
                  <span class="btn-label-loading" x-show="savingWizard" style="display: inline-flex;">
                    <span class="spinner"></span>
                    <span>Menyimpan...</span>
                  </span>
                </button>
              </div>
            </div>
          </div>

          {{-- LANGKAH 4: Tautan Halaman Publik --}}
          <div x-show="step === 4" x-cloak class="wizard-step-card">
            <div class="wizard-card-head">
              <span class="section-num-badge is-large">4</span>
              <div>
                <h3>Pemilihan Berhasil Dibuat — Tautan Halaman Publik</h3>
                <p>Salin tautan halaman publik di bawah ini untuk dibagikan kepada warga sekolah.</p>
              </div>
            </div>

            @if($createdElectionId)
              <div style="display: grid; gap: 12px;">
                <x-copy-link-box
                  label="1. TAUTAN PUBLIK JUMLAH PEMILIH (TERBUKA KAPAN SAJA)"
                  :url="$createdTurnoutUrl"
                  copyToast="Disalin"
                  style="margin-top: 0;"
                />
                <x-copy-link-box
                  label="2. TAUTAN PUBLIK HASIL PEMILIHAN (TERBUKA SETELAH WAKTU SELESAI)"
                  :url="$createdResultsUrl"
                  copyToast="Disalin"
                  style="margin-top: 0;"
                />
              </div>
            @endif
          </div>
        </div>

        <div class="modal-footer">
          <template x-if="step === 1">
            <div style="display: flex; width: 100%; justify-content: space-between; gap: 10px;">
              <button type="button" class="btn btn-secondary" wire:click="closeCreateModal">Batal</button>
              <button
                type="button"
                class="btn btn-primary"
                x-on:click="goStep2()"
                x-bind:disabled="!canProceedStep1"
              >
                <span>Lanjut ke Data Paslon</span>
                <svg class="icon"><use href="#i-arrow-right"></use></svg>
              </button>
            </div>
          </template>

          <template x-if="step === 2">
            <div style="display: flex; width: 100%; justify-content: space-between; gap: 10px;">
              <button type="button" class="btn btn-secondary" x-on:click="step = 1">
                <svg class="icon"><use href="#i-arrow-left"></use></svg>
                <span>Kembali</span>
              </button>
              <button
                type="button"
                class="btn btn-primary"
                x-on:click="goStep3()"
                x-bind:disabled="!canProceedStep2"
                title="Wajib mengisi minimal 2 pasangan calon"
              >
                <span>Lanjut ke Jadwal</span>
                <svg class="icon"><use href="#i-arrow-right"></use></svg>
              </button>
            </div>
          </template>

          <template x-if="step === 3">
            <div style="display: flex; width: 100%; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
              <button type="button" class="btn btn-secondary" x-on:click="step = 2" x-bind:disabled="savingWizard">
                <svg class="icon"><use href="#i-arrow-left"></use></svg>
                <span>Kembali</span>
              </button>
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button
                  type="button"
                  class="btn btn-soft"
                  x-on:click="confirmStartNow = true"
                  x-bind:disabled="savingWizard"
                >
                  <svg class="icon"><use href="#i-sparkles"></use></svg>
                  <span>Mulai Sekarang (Opsional)</span>
                </button>
                <button
                  type="button"
                  class="btn btn-primary"
                  x-on:click="submitWizard(false)"
                  x-bind:disabled="savingWizard"
                  x-bind:data-loading="savingWizard ? 'true' : null"
                >
                  <span class="btn-label-idle" x-show="!savingWizard">
                    <svg class="icon"><use href="#i-check"></use></svg>
                    <span>Simpan Konfigurasi</span>
                  </span>
                  <span class="btn-label-loading" x-show="savingWizard" style="display: inline-flex;">
                    <span class="spinner"></span>
                    <span>Menyimpan...</span>
                  </span>
                </button>
              </div>
            </div>
          </template>

          <template x-if="step === 4">
            <div style="display: flex; width: 100%; justify-content: flex-end; gap: 10px;">
              @if($createdElectionId)
                <a href="{{ route('operator.elections.detail', $createdElectionId) }}" wire:navigate class="btn btn-primary">
                  <svg class="icon"><use href="#i-check"></use></svg>
                  <span>Selesai &amp; Buka Halaman Pemilihan</span>
                </a>
              @else
                <button type="button" class="btn btn-primary" wire:click="closeCreateModal">Selesai</button>
              @endif
            </div>
          </template>
        </div>
      </div>
    </x-modal>
  @endif

  {{-- Perubahan 6: Modal Konfirmasi Hapus Pemilihan Permanen (Wajib Ketik Nama Pemilihan) --}}
  @if($showDeleteModal)
    <x-modal
      title="Hapus Pemilihan Secara Permanen?"
      :subtitle="'Target: <strong>' . e($deletingElectionName) . '</strong>'"
      icon="#i-trash"
      iconVariant="danger"
      maxWidth="540px"
      closeAction="closeDeleteModal"
    >
      <form wire:submit="confirmDeleteElection" novalidate>
        <div class="modal-body">
          <div class="alert alert-danger" role="alert">
            <svg class="icon"><use href="#i-alert"></use></svg>
            <div>
              <strong>Penghapusan bersifat permanen dan tidak dapat dibatalkan</strong>
              <p>
                Seluruh data terkait pemilihan ini — termasuk pasangan calon beserta berkas fotonya, daftar hak pilih, token QR, suara tersimpan, riwayat perangkat, dan log audit pemilihan ini — akan dihapus secara total.
              </p>
            </div>
          </div>

          <div class="form-field" style="margin-top: 14px;">
            <label for="del-confirm-name" class="field-label">
              Ketik nama pemilihan <code>{{ $deletingElectionName }}</code> untuk mengonfirmasi <span class="req">*</span>
            </label>
            <input
              id="del-confirm-name"
              type="text"
              wire:model.live="deleteConfirmName"
              class="field-input @error('deleteConfirmName') is-invalid @enderror"
              placeholder="{{ $deletingElectionName }}"
              autocomplete="off"
            />
            @error('deleteConfirmName')
              <div class="field-error" role="alert">
                <svg class="icon"><use href="#i-alert"></use></svg>
                <span>{{ $message }}</span>
              </div>
            @enderror
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" wire:click="closeDeleteModal">Batal</button>
          <x-action-button
            type="submit"
            variant="danger"
            target="confirmDeleteElection"
            icon="#i-trash"
            loadingText="Menghapus..."
            :disabled="trim($deleteConfirmName) !== trim($deletingElectionName)"
          >
            Hapus Permanen
          </x-action-button>
        </div>
      </form>
    </x-modal>
  @endif
</div>
