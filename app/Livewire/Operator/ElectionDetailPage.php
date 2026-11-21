<?php

namespace App\Livewire\Operator;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Livewire\StaffPage;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Services\AuditService;
use App\Services\SupabaseStorageService;
use App\Services\TokenService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
#[Title('Detail & Konfigurasi Pemilihan')]
class ElectionDetailPage extends StaffPage
{
    protected const ROLE = 'operator';

    use HandlesSafeErrors;
    use WithFileUploads;

    public Election $election;

    // FR-008, FR-028: Identitas Pemilihan & Batas Hak Pilih
    public string $name = '';

    public int $maxVoterRights = 100;

    // FR-009: Jadwal Pemilihan
    public string $startsAtInput = '';

    public string $endsAtInput = '';

    public bool $showEndEarlyModal = false;

    // FR-011: Pengelolaan Paslon
    public bool $showCandidateModal = false;

    public ?string $editingCandidateId = null;

    public int $candidateNumber = 1;

    public string $candidateName = '';

    public string $candidateVision = '';

    public string $candidateMissionsText = '';

    public $candidatePhoto = null;

    // Konfirmasi Hapus Paslon
    public bool $showDeleteCandidateModal = false;

    public ?string $deletingCandidateId = null;

    public string $deletingCandidateName = '';

    // Perubahan 6: Modal Hapus Pemilihan Permanen
    public bool $showDeleteElectionModal = false;

    public string $deleteConfirmName = '';

    // Perubahan 7: Access Key Rekap Real-Time (Sekali Tampil)
    public bool $showAccessKeyModal = false;

    public string $issuedAccessKey = '';

    public string $issuedLiveRecapUrl = '';

    public bool $showRevokeAccessKeyModal = false;

    public function mount(Election $election): void
    {
        $this->election = $election;
        $this->syncElectionFields();
    }

    private function syncElectionFields(): void
    {
        $this->election->refresh();
        $this->name = $this->election->name;
        $this->maxVoterRights = (int) $this->election->max_voter_rights;

        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
        $this->startsAtInput = $this->election->starts_at
            ? $this->election->starts_at->copy()->setTimezone($tz)->format('Y-m-d\TH:i')
            : now()->setTimezone($tz)->addMinutes(5)->format('Y-m-d\TH:i');
        $this->endsAtInput = $this->election->ends_at
            ? $this->election->ends_at->copy()->setTimezone($tz)->format('Y-m-d\TH:i')
            : now()->setTimezone($tz)->addHours(4)->format('Y-m-d\TH:i');
    }

    public function saveElectionInfo(AuditService $audit): void
    {
        $this->resetValidation();
        $existingRightsCount = $this->election->voterRights()->count();
        $minAllowed = max(3, $existingRightsCount);

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'maxVoterRights' => ['required', 'integer', "min:{$minAllowed}", 'max:10000'],
        ], [
            'name.required' => 'Nama pemilihan wajib diisi.',
            'name.min' => 'Nama pemilihan minimal 3 karakter.',
            'maxVoterRights.required' => 'Batas maksimum hak pilih wajib diisi.',
            'maxVoterRights.integer' => 'Batas maksimum hak pilih harus berupa angka bulat.',
            'maxVoterRights.min' => $existingRightsCount > 3
                ? "Batas maksimum tidak boleh lebih kecil dari jumlah hak pilih yang sudah dibuat ({$existingRightsCount})."
                : 'Batas maksimum hak pilih minimal 3 dan maksimal 10000.',
            'maxVoterRights.max' => 'Batas maksimum hak pilih minimal 3 dan maksimal 10000.',
        ]);

        if (! $this->acquireIdempotencyLock('save_election_info:'.$this->election->id, 2)) {
            return;
        }

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();
            $cleanName = preg_replace('/\s+/u', ' ', trim($this->name)) ?? trim($this->name);

            DB::connection('staff')->transaction(function () use ($cleanName, $operator, $audit) {
                $this->election->update([
                    'name' => $cleanName,
                    'max_voter_rights' => $this->maxVoterRights,
                ]);

                $audit->log(
                    actorType: 'operator',
                    actorId: $operator->id,
                    action: 'ubah_pemilihan',
                    objectType: 'election',
                    objectId: $this->election->id,
                    detail: [
                        'name' => $cleanName,
                        'max_voter_rights' => $this->maxVoterRights,
                    ]
                );
            });

            $this->syncElectionFields();
            $this->dispatch('toast', message: 'Tersimpan', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menyimpan pengaturan pemilihan.', 'name');
        }
    }

    public function saveSchedule(VotingService $voting): void
    {
        $this->resetValidation();

        $phase = $this->election->currentPhase();
        if ($phase === 'ditutup') {
            $this->dispatch('toast', message: 'Jadwal tidak dapat diubah setelah pemilihan Ditutup.', type: 'error');

            return;
        }

        $this->validate([
            'startsAtInput' => ['required', 'date_format:Y-m-d\TH:i'],
            'endsAtInput' => ['required', 'date_format:Y-m-d\TH:i'],
        ], [
            'startsAtInput.required' => 'Waktu Mulai wajib diisi.',
            'startsAtInput.date_format' => 'Format Waktu Mulai harus tahun-bulan-tanggal jam:menit.',
            'endsAtInput.required' => 'Waktu Selesai wajib diisi.',
            'endsAtInput.date_format' => 'Format Waktu Selesai harus tahun-bulan-tanggal jam:menit.',
        ]);

        try {
            $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');

            // BR-025, AC-011: Saat Berlangsung, Waktu Mulai terkunci pada nilai di DB
            $startsUtc = ($phase === 'berlangsung' && $this->election->starts_at)
                ? CarbonImmutable::instance($this->election->starts_at)->setTimezone('UTC')
                : CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->startsAtInput, $tz)->setTimezone('UTC');

            $endsUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->endsAtInput, $tz)->setTimezone('UTC');

            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $result = $voting->setElectionSchedule(
                electionId: $this->election->id,
                startsAtUtc: $startsUtc,
                endsAtUtc: $endsUtc,
                operatorId: $operator->id
            );

            if (! $result['ok']) {
                $this->addError('endsAtInput', $result['message']);
                $this->dispatch('toast', message: $result['message'], type: 'error');

                return;
            }

            $this->syncElectionFields();
            $this->dispatch('toast', message: 'Jadwal pemilihan berhasil disimpan.', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menyimpan jadwal pemilihan. Silakan coba kembali.', 'endsAtInput');
        }
    }

    public function startElectionNow(VotingService $voting): void
    {
        try {
            $phase = $this->election->currentPhase();
            if ($phase !== 'persiapan') {
                $this->dispatch('toast', message: 'Pemilihan sudah dimulai atau telah ditutup.', type: 'warning');

                return;
            }

            $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
            $startsUtc = $voting->databaseNow();
            $endsUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->endsAtInput, $tz)->setTimezone('UTC');
            if ($endsUtc->lte($startsUtc)) {
                $endsUtc = $startsUtc->addHours(4);
            }

            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $result = $voting->setElectionSchedule(
                electionId: $this->election->id,
                startsAtUtc: $startsUtc,
                endsAtUtc: $endsUtc,
                operatorId: $operator->id
            );

            if (! $result['ok']) {
                $this->dispatch('toast', message: $result['message'], type: 'error');

                return;
            }

            $this->syncElectionFields();
            $this->dispatch('toast', message: 'Pemilihan resmi dimulai (Fase Berlangsung).', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memulai pemilihan sekarang. Silakan coba kembali.');
        }
    }

    public function confirmEndEarly(VotingService $voting): void
    {
        $this->showEndEarlyModal = false;

        try {
            $phase = $this->election->currentPhase();
            if ($phase !== 'berlangsung' || ! $this->election->starts_at) {
                $this->dispatch('toast', message: 'Hanya pemilihan yang sedang berlangsung yang dapat diakhiri lebih awal.', type: 'error');

                return;
            }

            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $startsUtc = CarbonImmutable::instance($this->election->starts_at)->setTimezone('UTC');
            $endsUtc = $voting->databaseNow();
            if ($endsUtc->lte($startsUtc)) {
                $endsUtc = $startsUtc->addMicrosecond();
            }

            $result = $voting->setElectionSchedule(
                electionId: $this->election->id,
                startsAtUtc: $startsUtc,
                endsAtUtc: $endsUtc,
                operatorId: $operator->id
            );

            if (! $result['ok']) {
                $this->dispatch('toast', message: $result['message'], type: 'error');

                return;
            }

            $this->syncElectionFields();
            $this->dispatch('toast', message: 'Masa pemilihan telah diakhiri (Fase Ditutup). Hasil kini terbuka.', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mengakhiri pemilihan lebih awal. Silakan coba kembali.');
        }
    }

    public function openAddCandidateModal(): void
    {
        if ($this->election->currentPhase() !== 'persiapan') {
            $this->dispatch('toast', message: 'Pasangan calon hanya dapat diubah pada Fase Persiapan.', type: 'error');

            return;
        }

        $this->resetValidation();
        $maxNum = (int) $this->election->candidates()->max('number');
        $this->editingCandidateId = null;
        $this->candidateNumber = $maxNum + 1;
        $this->candidateName = '';
        $this->candidateVision = '';
        $this->candidateMissionsText = '';
        $this->candidatePhoto = null;
        $this->showCandidateModal = true;
    }

    public function openEditCandidateModal(string $candidateId): void
    {
        if ($this->election->currentPhase() !== 'persiapan') {
            $this->dispatch('toast', message: 'Pasangan calon hanya dapat diubah pada Fase Persiapan.', type: 'error');

            return;
        }

        $this->resetValidation();
        /** @var Candidate $cand */
        $cand = $this->election->candidates()->findOrFail($candidateId);
        $this->editingCandidateId = $cand->id;
        $this->candidateNumber = (int) $cand->number;
        $this->candidateName = $cand->name;
        $this->candidateVision = (string) ($cand->vision ?? '');
        $this->candidateMissionsText = implode("\n", $cand->missionItems());
        $this->candidatePhoto = null;
        $this->showCandidateModal = true;
    }

    public function closeCandidateModal(): void
    {
        $this->showCandidateModal = false;
        $this->candidatePhoto = null;
    }

    public function saveCandidate(SupabaseStorageService $storage, AuditService $audit): void
    {
        if ($this->election->currentPhase() !== 'persiapan') {
            $this->dispatch('toast', message: 'Pasangan calon hanya dapat diubah pada Fase Persiapan.', type: 'error');

            return;
        }

        $this->validate([
            'candidateNumber' => ['required', 'integer', 'min:1', 'max:99'],
            'candidateName' => ['required', 'string', 'min:2', 'max:160'],
            'candidateVision' => ['nullable', 'string', 'max:600'],
            'candidateMissionsText' => ['nullable', 'string', 'max:2000'],
            'candidatePhoto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'candidateNumber.required' => 'Nomor urut paslon wajib diisi.',
            'candidateNumber.min' => 'Nomor urut minimal 1.',
            'candidateName.required' => 'Nama pasangan calon wajib diisi.',
            'candidatePhoto.image' => 'Berkas harus berupa gambar (JPG, PNG, atau WEBP).',
            'candidatePhoto.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $dupQuery = $this->election->candidates()->where('number', $this->candidateNumber);
        if ($this->editingCandidateId) {
            $dupQuery->where('id', '<>', $this->editingCandidateId);
        }
        if ($dupQuery->exists()) {
            $msg = "Nomor urut {$this->candidateNumber} sudah digunakan pada pemilihan ini.";
            $this->addError('candidateNumber', $msg);
            $this->dispatch('toast', message: $msg, type: 'error');

            return;
        }

        $missionsArray = array_values(array_filter(array_map(
            static fn (string $line) => trim(preg_replace('/^[-•*\d.)\s]+/u', '', trim($line)) ?? ''),
            preg_split('/\r\n|\r|\n/', $this->candidateMissionsText) ?: []
        ), static fn (string $v) => $v !== ''));

        /** @var StaffAccount $operator */
        $operator = Auth::guard('staff')->user();

        try {
            if ($this->editingCandidateId) {
                /** @var Candidate $cand */
                $cand = $this->election->candidates()->findOrFail($this->editingCandidateId);
                $photoPath = $cand->photo_path;

                if ($this->candidatePhoto) {
                    $newPath = $storage->uploadCandidatePhoto($this->election->id, $cand->id, $this->candidatePhoto);
                    if ($photoPath && $photoPath !== $newPath) {
                        $storage->deleteCandidatePhoto($photoPath);
                    }
                    $photoPath = $newPath;
                }

                $cand->update([
                    'number' => $this->candidateNumber,
                    'name' => preg_replace('/\s+/u', ' ', trim($this->candidateName)) ?? trim($this->candidateName),
                    'vision' => trim($this->candidateVision) !== '' ? trim($this->candidateVision) : null,
                    'missions' => $missionsArray !== [] ? $missionsArray : null,
                    'photo_path' => $photoPath,
                ]);

                $audit->log(
                    actorType: 'operator',
                    actorId: $operator->id,
                    action: 'ubah_paslon',
                    objectType: 'candidate',
                    objectId: $cand->id,
                    detail: [
                        'election_id' => $this->election->id,
                        'number' => $cand->number,
                        'name' => $cand->name,
                    ]
                );

                $this->closeCandidateModal();
                $this->dispatch('toast', message: 'Data pasangan calon berhasil diperbarui.', type: 'success');

                return;
            }

            $candidateId = (string) Str::uuid();
            $photoPath = null;

            if ($this->candidatePhoto) {
                $photoPath = $storage->uploadCandidatePhoto($this->election->id, $candidateId, $this->candidatePhoto);
            }

            $cand = new Candidate([
                'election_id' => $this->election->id,
                'number' => $this->candidateNumber,
                'name' => preg_replace('/\s+/u', ' ', trim($this->candidateName)) ?? trim($this->candidateName),
                'vision' => trim($this->candidateVision) !== '' ? trim($this->candidateVision) : null,
                'missions' => $missionsArray !== [] ? $missionsArray : null,
                'photo_path' => $photoPath,
            ]);
            $cand->id = $candidateId;
            $cand->setConnection('staff')->save();

            $audit->log(
                actorType: 'operator',
                actorId: $operator->id,
                action: 'tambah_paslon',
                objectType: 'candidate',
                objectId: $cand->id,
                detail: [
                    'election_id' => $this->election->id,
                    'number' => $cand->number,
                    'name' => $cand->name,
                ]
            );

            $this->closeCandidateModal();
            $this->dispatch('toast', message: 'Pasangan calon berhasil ditambahkan.', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError(
                $e,
                'Gagal menyimpan data pasangan calon. Silakan periksa kembali isian atau koneksi Anda.',
                'candidatePhoto'
            );
        }
    }

    public function promptDeleteCandidate(string $candidateId): void
    {
        if ($this->election->currentPhase() !== 'persiapan') {
            $this->dispatch('toast', message: 'Pasangan calon tidak dapat dihapus setelah pemilihan dimulai.', type: 'error');

            return;
        }

        /** @var Candidate $cand */
        $cand = $this->election->candidates()->findOrFail($candidateId);
        $this->deletingCandidateId = $cand->id;
        $this->deletingCandidateName = "Paslon {$cand->formattedNumber()} — {$cand->name}";
        $this->showDeleteCandidateModal = true;
    }

    public function confirmDeleteCandidate(SupabaseStorageService $storage, AuditService $audit): void
    {
        if (! $this->deletingCandidateId) {
            return;
        }

        if ($this->election->currentPhase() !== 'persiapan') {
            $this->showDeleteCandidateModal = false;
            $this->dispatch('toast', message: 'Pasangan calon hanya dapat dihapus pada Fase Persiapan.', type: 'error');

            return;
        }

        $totalCandidates = $this->election->candidates()->count();
        $scheduleConfigured = $this->election->starts_at !== null && $this->election->ends_at !== null;

        // BR-024, AC-013: Setelah jadwal diatur, paslon tidak boleh berkurang di bawah 2
        if ($scheduleConfigured && $totalCandidates <= 2) {
            $this->showDeleteCandidateModal = false;
            $this->dispatch('toast', message: 'Tidak dapat menghapus paslon: jadwal sudah diatur sehingga jumlah paslon tidak boleh kurang dari 2.', type: 'error');

            return;
        }

        try {
            /** @var Candidate $cand */
            $cand = $this->election->candidates()->findOrFail($this->deletingCandidateId);
            $oldPhoto = $cand->photo_path;
            $candNumber = $cand->number;
            $candName = $cand->name;
            $candId = $cand->id;

            $cand->delete();
            $storage->deleteCandidatePhoto($oldPhoto);

            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();
            $audit->log(
                actorType: 'operator',
                actorId: $operator->id,
                action: 'hapus_paslon',
                objectType: 'candidate',
                objectId: $candId,
                detail: [
                    'election_id' => $this->election->id,
                    'number' => $candNumber,
                    'name' => $candName,
                ]
            );

            $this->showDeleteCandidateModal = false;
            $this->deletingCandidateId = null;
            $this->dispatch('toast', message: 'Terhapus', type: 'success');
        } catch (Throwable $e) {
            $this->showDeleteCandidateModal = false;
            $this->reportSafeError($e, 'Gagal menghapus pasangan calon.');
        }
    }

    public function issueAccessKey(VotingService $voting, TokenService $tokens): void
    {
        if (! $this->acquireIdempotencyLock('issue_access_key:'.$this->election->id, 3)) {
            return;
        }

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();
            $result = $voting->issueAccessKey($this->election->id, $operator->id);

            if (! $result['ok'] || ! $result['raw_key']) {
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return;
            }

            $this->election->refresh();
            $this->issuedAccessKey = $result['raw_key'];
            $this->issuedLiveRecapUrl = $tokens->liveRecapUrl($this->election->id);
            $this->showAccessKeyModal = true;
            $this->dispatch('toast', message: 'Berhasil', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menerbitkan access key rekap real-time.');
        }
    }

    public function closeAccessKeyModal(): void
    {
        // Perubahan 7: Kunci asli hanya ditampilkan satu kali saat diterbitkan
        $this->showAccessKeyModal = false;
        $this->issuedAccessKey = '';
        $this->issuedLiveRecapUrl = '';
    }

    public function confirmRevokeAccessKey(VotingService $voting): void
    {
        $this->showRevokeAccessKeyModal = false;

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();
            $result = $voting->revokeAccessKey($this->election->id, $operator->id);

            if (! $result['ok']) {
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return;
            }

            $this->election->refresh();
            $this->dispatch('toast', message: 'Dicabut', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal mencabut access key rekap real-time.');
        }
    }

    public function openDeleteElectionModal(): void
    {
        if ($this->election->currentPhase() === 'berlangsung') {
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return;
        }

        $this->resetValidation();
        $this->deleteConfirmName = '';
        $this->showDeleteElectionModal = true;
    }

    public function closeDeleteElectionModal(): void
    {
        $this->showDeleteElectionModal = false;
        $this->deleteConfirmName = '';
    }

    public function confirmDeleteElection(VotingService $voting, SupabaseStorageService $storage): void
    {
        $this->resetValidation();

        if ($this->election->currentPhase() === 'berlangsung') {
            $this->addError('deleteConfirmName', 'Pemilihan yang sedang berlangsung dilarang dihapus.');
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return;
        }

        if (trim($this->deleteConfirmName) !== trim($this->election->name)) {
            $this->addError('deleteConfirmName', 'Ketik nama pemilihan secara persis untuk mengonfirmasi penghapusan permanen.');
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return;
        }

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $result = $voting->deleteElection(
                electionId: $this->election->id,
                operatorId: $operator->id,
                confirmName: $this->deleteConfirmName,
                storage: $storage
            );

            if (! $result['ok']) {
                $this->addError('deleteConfirmName', $result['message']);
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return;
            }

            $this->showDeleteElectionModal = false;
            session()->flash('success', 'Terhapus');
            $this->redirectRoute('operator.elections');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menghapus pemilihan.', 'deleteConfirmName');
        }
    }

    public function render(TokenService $tokens)
    {
        View::share('activeElection', $this->election);

        $phase = $this->election->currentPhase();
        $candidates = $this->election->candidates()->get();
        $voterRightsCount = $this->election->voterRights()->count();
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
        $veRow = DB::connection('staff')->selectOne('select has_access_key, access_key_created_at from evoting.v_elections where id = ?::uuid', [$this->election->id]);
        $hasAccessKey = (bool) ($veRow->has_access_key ?? false);
        $accessKeyCreatedAt = ! empty($veRow->access_key_created_at)
            ? CarbonImmutable::parse($veRow->access_key_created_at)->setTimezone($tz)
            : null;
        $liveRecapUrl = $tokens->liveRecapUrl($this->election->id);

        return view('livewire.operator.election-detail-page', [
            'phase' => $phase,
            'candidates' => $candidates,
            'voterRightsCount' => $voterRightsCount,
            'tz' => $tz,
            'hasAccessKey' => $hasAccessKey,
            'accessKeyCreatedAt' => $accessKeyCreatedAt,
            'liveRecapUrl' => $liveRecapUrl,
        ]);
    }
}
