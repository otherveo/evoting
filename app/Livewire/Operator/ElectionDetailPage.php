<?php

namespace App\Livewire\Operator;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Services\AuditService;
use App\Services\SupabaseStorageService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Detail & Konfigurasi Pemilihan')]
class ElectionDetailPage extends Component
{
    use WithFileUploads;

    public Election $election;

    // Form Identitas & Batas Hak Pilih (FR-008, FR-028)
    public string $name = '';

    public int $maxVoterRights = 100;

    // Form Jadwal (FR-009)
    public string $startsAtInput = '';

    public string $endsAtInput = '';

    public bool $showEndEarlyModal = false;

    // Form Paslon (FR-011)
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
        $minAllowed = max(1, $existingRightsCount);

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'maxVoterRights' => ['required', 'integer', "min:{$minAllowed}", 'max:100000'],
        ], [
            'name.required' => 'Nama pemilihan wajib diisi.',
            'maxVoterRights.required' => 'Batas maksimum hak pilih wajib diisi.',
            'maxVoterRights.min' => $existingRightsCount > 0
                ? "Batas maksimum tidak boleh lebih kecil dari jumlah hak pilih yang sudah dibuat ({$existingRightsCount})."
                : 'Batas maksimum hak pilih minimal 1.',
        ]);

        /** @var StaffAccount $operator */
        $operator = Auth::guard('staff')->user();

        $this->election->update([
            'name' => preg_replace('/\s+/u', ' ', trim($this->name)) ?? trim($this->name),
            'max_voter_rights' => $this->maxVoterRights,
        ]);

        $audit->log(
            actorType: 'operator',
            actorId: $operator->id,
            action: 'ubah_pemilihan',
            objectType: 'election',
            objectId: $this->election->id,
            detail: [
                'name' => $this->election->name,
                'max_voter_rights' => $this->election->max_voter_rights,
            ]
        );

        $this->syncElectionFields();
        $this->dispatch('toast', message: 'Pengaturan pemilihan berhasil disimpan.', type: 'success');
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

        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');

        // Saat Berlangsung, Waktu Mulai terkunci pada nilai di DB (BR-025, AC-011)
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
    }

    public function startElectionNow(VotingService $voting): void
    {
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
    }

    public function confirmEndEarly(VotingService $voting): void
    {
        $this->showEndEarlyModal = false;
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
            $this->addError('candidateNumber', "Nomor urut {$this->candidateNumber} sudah digunakan pada pemilihan ini.");

            return;
        }

        $missionsArray = array_values(array_filter(array_map(
            static fn (string $line) => trim(preg_replace('/^[-•*\d.)\s]+/u', '', trim($line)) ?? ''),
            preg_split('/\r\n|\r|\n/', $this->candidateMissionsText) ?: []
        ), static fn (string $v) => $v !== ''));

        /** @var StaffAccount $operator */
        $operator = Auth::guard('staff')->user();

        if ($this->editingCandidateId) {
            /** @var Candidate $cand */
            $cand = $this->election->candidates()->findOrFail($this->editingCandidateId);
            $photoPath = $cand->photo_path;

            if ($this->candidatePhoto) {
                try {
                    $newPath = $storage->uploadCandidatePhoto($this->election->id, $cand->id, $this->candidatePhoto);
                    if ($photoPath && $photoPath !== $newPath) {
                        $storage->deleteCandidatePhoto($photoPath);
                    }
                    $photoPath = $newPath;
                } catch (\Throwable $e) {
                    $this->addError('candidatePhoto', $e->getMessage());

                    return;
                }
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
            try {
                $photoPath = $storage->uploadCandidatePhoto($this->election->id, $candidateId, $this->candidatePhoto);
            } catch (\Throwable $e) {
                $this->addError('candidatePhoto', $e->getMessage());

                return;
            }
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
        $cand->save();

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

        if ($scheduleConfigured && $totalCandidates <= 2) {
            $this->showDeleteCandidateModal = false;
            $this->dispatch('toast', message: 'Tidak dapat menghapus paslon: jadwal sudah diatur sehingga jumlah paslon tidak boleh kurang dari 2 (BR-024).', type: 'error');

            return;
        }

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
        $this->dispatch('toast', message: 'Pasangan calon berhasil dihapus.', type: 'success');
    }

    public function render()
    {
        View::share('activeElection', $this->election);

        $phase = $this->election->currentPhase();
        $candidates = $this->election->candidates()->get();
        $voterRightsCount = $this->election->voterRights()->count();
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');

        return view('livewire.operator.election-detail-page', [
            'phase' => $phase,
            'candidates' => $candidates,
            'voterRightsCount' => $voterRightsCount,
            'tz' => $tz,
        ]);
    }
}
