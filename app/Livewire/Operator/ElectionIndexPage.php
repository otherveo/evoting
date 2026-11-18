<?php

namespace App\Livewire\Operator;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Services\AuditService;
use App\Services\SupabaseStorageService;
use App\Services\VotingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
#[Title('Daftar Pemilihan')]
class ElectionIndexPage extends Component
{
    use HandlesSafeErrors;
    use WithFileUploads;

    public bool $showCreateModal = false;

    public string $name = '';

    public int $maxVoterRights = 300;

    // Pengaman Idempotency (Bug 1)
    public string $submitToken = '';

    public bool $isCreated = false;

    // Wizard Sekuensial 4 Langkah (Perubahan 2.2)
    /** @var list<array{number: int, name: string, vision: string, missions: string}> */
    public array $wizardCandidates = [];

    /** @var array<int, mixed> */
    public array $wizardPhotos = [];

    public string $wizardStartsAt = '';

    public string $wizardEndsAt = '';

    public ?string $createdElectionId = null;

    public string $createdTurnoutUrl = '';

    public string $createdResultsUrl = '';

    // Modal Hapus Pemilihan Permanen (Perubahan 6)
    public bool $showDeleteModal = false;

    public ?string $deletingElectionId = null;

    public string $deletingElectionName = '';

    public string $deleteConfirmName = '';

    public function mount(): void
    {
        $this->resetWizardDefaults();
    }

    private function resetWizardDefaults(): void
    {
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
        $this->name = '';
        $this->maxVoterRights = 300;
        $this->submitToken = (string) Str::uuid();
        $this->isCreated = false;
        $this->wizardCandidates = [
            [
                'number' => 1,
                'name' => '',
                'vision' => '',
                'missions' => '',
            ],
        ];
        $this->wizardPhotos = [];
        $this->wizardStartsAt = now()->setTimezone($tz)->addMinutes(15)->format('Y-m-d\TH:i');
        $this->wizardEndsAt = now()->setTimezone($tz)->addHours(4)->format('Y-m-d\TH:i');
        $this->createdElectionId = null;
        $this->createdTurnoutUrl = '';
        $this->createdResultsUrl = '';
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->resetWizardDefaults();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        // Perubahan 2.2: Keluar di tengah wizard membatalkan semuanya (tidak ada yang tersimpan ke database)
        $this->showCreateModal = false;
        $this->resetWizardDefaults();
    }

    /**
     * Pembuatan pemilihan langsung / kompatibilitas dengan pengaman anti-duplikasi (Bug 1 & Perubahan 1).
     */
    public function createElection(AuditService $audit): void
    {
        if ($this->isCreated) {
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'maxVoterRights' => ['required', 'integer', 'min:3', 'max:10000'],
        ], [
            'name.required' => 'Nama pemilihan wajib diisi.',
            'name.min' => 'Nama pemilihan minimal 3 karakter.',
            'maxVoterRights.required' => 'Batas maksimum hak pilih wajib diisi.',
            'maxVoterRights.integer' => 'Batas maksimum hak pilih harus berupa angka bulat.',
            'maxVoterRights.min' => 'Batas maksimum hak pilih minimal 3 dan maksimal 10000.',
            'maxVoterRights.max' => 'Batas maksimum hak pilih minimal 3 dan maksimal 10000.',
        ]);

        /** @var StaffAccount $operator */
        $operator = Auth::guard('staff')->user();
        $cleanName = preg_replace('/\s+/u', ' ', trim($this->name)) ?? trim($this->name);
        $lockKey = 'create_election:'.$operator->id.':'.($this->submitToken !== '' ? $this->submitToken : mb_strtolower($cleanName));

        if (! $this->acquireIdempotencyLock($lockKey, 15)) {
            return;
        }

        try {
            $election = DB::transaction(function () use ($cleanName, $operator, $audit) {
                DB::selectOne('select pg_advisory_xact_lock(hashtext(?))', ['create_el:'.mb_strtolower($cleanName)]);

                $existing = Election::query()
                    ->whereRaw('lower(name) = ?', [mb_strtolower($cleanName)])
                    ->where('created_at', '>=', now()->subSeconds(15))
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $el = Election::create([
                    'name' => $cleanName,
                    'max_voter_rights' => $this->maxVoterRights,
                ]);

                $audit->log(
                    actorType: 'operator',
                    actorId: $operator->id,
                    action: 'buat_pemilihan',
                    objectType: 'election',
                    objectId: $el->id,
                    detail: [
                        'name' => $el->name,
                        'max_voter_rights' => $el->max_voter_rights,
                    ]
                );

                return $el;
            });

            $this->isCreated = true;
            $this->submitToken = '';
            $this->createdElectionId = $election->id;
            $this->createdTurnoutUrl = route('public.turnout', $election);
            $this->createdResultsUrl = route('public.results', $election);

            session()->flash('success', 'Berhasil');
            $this->redirectRoute('operator.elections.detail', $election);
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal membuat pemilihan baru.', 'name');
        }
    }

    /**
     * Menyimpan seluruh langkah Wizard Sekuensial (Langkah 1 Identitas + Langkah 2 Paslon + Langkah 3 Jadwal)
     * secara atomik ke database lalu mengembalikan tautan Langkah 4 (Perubahan 2.2).
     *
     * @param  list<array{number: int|string, name: string, vision?: string, missions?: string}>  $candidatesPayload
     */
    public function completeWizard(
        string $electionName,
        int $maxRights,
        array $candidatesPayload,
        string $startsAtLocal,
        string $endsAtLocal,
        bool $startNow,
        AuditService $audit,
        VotingService $voting,
        SupabaseStorageService $storage
    ): bool {
        if ($this->isCreated) {
            return true;
        }

        $this->name = $electionName;
        $this->maxVoterRights = $maxRights;
        $this->wizardStartsAt = $startsAtLocal;
        $this->wizardEndsAt = $endsAtLocal;

        $this->resetValidation();

        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'maxVoterRights' => ['required', 'integer', 'min:3', 'max:10000'],
            'wizardStartsAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'wizardEndsAt' => ['required', 'date_format:Y-m-d\TH:i'],
        ], [
            'name.required' => 'Nama pemilihan wajib diisi.',
            'name.min' => 'Nama pemilihan minimal 3 karakter.',
            'maxVoterRights.min' => 'Batas maksimum hak pilih minimal 3 dan maksimal 10000.',
            'maxVoterRights.max' => 'Batas maksimum hak pilih minimal 3 dan maksimal 10000.',
            'wizardStartsAt.required' => 'Waktu Mulai wajib diisi.',
            'wizardEndsAt.required' => 'Waktu Selesai wajib diisi.',
        ]);

        $normalizedCandidates = [];
        $usedNumbers = [];
        foreach ($candidatesPayload as $idx => $item) {
            $cName = preg_replace('/\s+/u', ' ', trim((string) ($item['name'] ?? ''))) ?? '';
            $cNum = (int) ($item['number'] ?? ($idx + 1));
            if ($cName === '' || mb_strlen($cName) < 2) {
                $this->addError('name', 'Setiap pasangan calon wajib memiliki nama minimal 2 karakter.');
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return false;
            }
            if ($cNum < 1 || in_array($cNum, $usedNumbers, true)) {
                $this->addError('name', "Nomor urut pasangan calon ({$cNum}) tidak valid atau duplikat.");
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return false;
            }
            $usedNumbers[] = $cNum;
            $missionsText = (string) ($item['missions'] ?? '');
            $missionsArray = array_values(array_filter(array_map(
                static fn (string $line) => trim(preg_replace('/^[-•*\d.)\s]+/u', '', trim($line)) ?? ''),
                preg_split('/\r\n|\r|\n/', $missionsText) ?: []
            ), static fn (string $v) => $v !== ''));

            $normalizedCandidates[] = [
                'index' => $idx,
                'number' => $cNum,
                'name' => $cName,
                'vision' => trim((string) ($item['vision'] ?? '')) !== '' ? trim((string) ($item['vision'] ?? '')) : null,
                'missions' => $missionsArray !== [] ? $missionsArray : null,
            ];
        }

        if (count($normalizedCandidates) < 2) {
            $this->addError('name', 'Minimal 2 pasangan calon wajib ditambahkan sebelum menyimpan pemilihan.');
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return false;
        }

        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
        $startsUtc = $startNow
            ? $voting->databaseNow()
            : CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->wizardStartsAt, $tz)->setTimezone('UTC');
        $endsUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->wizardEndsAt, $tz)->setTimezone('UTC');

        if ($startNow && $endsUtc->lte($startsUtc)) {
            $endsUtc = $startsUtc->addHours(4);
        }

        if ($endsUtc->lte($startsUtc)) {
            $this->addError('wizardEndsAt', 'Waktu Selesai harus berada setelah Waktu Mulai.');
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return false;
        }

        /** @var StaffAccount $operator */
        $operator = Auth::guard('staff')->user();
        $cleanName = preg_replace('/\s+/u', ' ', trim($this->name)) ?? trim($this->name);
        $lockKey = 'wizard_election:'.$operator->id.':'.($this->submitToken !== '' ? $this->submitToken : mb_strtolower($cleanName));

        if (! $this->acquireIdempotencyLock($lockKey, 15)) {
            return false;
        }

        try {
            $election = DB::transaction(function () use (
                $cleanName,
                $operator,
                $normalizedCandidates,
                $startsUtc,
                $endsUtc,
                $audit,
                $voting,
                $storage
            ) {
                DB::selectOne('select pg_advisory_xact_lock(hashtext(?))', ['create_el:'.mb_strtolower($cleanName)]);

                $existing = Election::query()
                    ->whereRaw('lower(name) = ?', [mb_strtolower($cleanName)])
                    ->where('created_at', '>=', now()->subSeconds(15))
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $el = Election::create([
                    'name' => $cleanName,
                    'max_voter_rights' => $this->maxVoterRights,
                ]);

                $audit->log(
                    actorType: 'operator',
                    actorId: $operator->id,
                    action: 'buat_pemilihan',
                    objectType: 'election',
                    objectId: $el->id,
                    detail: [
                        'name' => $el->name,
                        'max_voter_rights' => $el->max_voter_rights,
                    ]
                );

                foreach ($normalizedCandidates as $candData) {
                    $candId = (string) Str::uuid();
                    $photoPath = null;
                    $uploadedPhoto = $this->wizardPhotos[$candData['index']] ?? null;
                    if ($uploadedPhoto) {
                        $photoPath = $storage->uploadCandidatePhoto($el->id, $candId, $uploadedPhoto);
                    }

                    $cand = new Candidate([
                        'election_id' => $el->id,
                        'number' => $candData['number'],
                        'name' => $candData['name'],
                        'vision' => $candData['vision'],
                        'missions' => $candData['missions'],
                        'photo_path' => $photoPath,
                    ]);
                    $cand->id = $candId;
                    $cand->save();

                    $audit->log(
                        actorType: 'operator',
                        actorId: $operator->id,
                        action: 'tambah_paslon',
                        objectType: 'candidate',
                        objectId: $cand->id,
                        detail: [
                            'election_id' => $el->id,
                            'number' => $cand->number,
                            'name' => $cand->name,
                        ]
                    );
                }

                $schedResult = $voting->setElectionSchedule(
                    electionId: $el->id,
                    startsAtUtc: $startsUtc,
                    endsAtUtc: $endsUtc,
                    operatorId: $operator->id
                );

                if (! $schedResult['ok']) {
                    throw new \RuntimeException($schedResult['message']);
                }

                return $el;
            });

            $this->isCreated = true;
            $this->submitToken = '';
            $this->createdElectionId = $election->id;
            $this->createdTurnoutUrl = route('public.turnout', $election);
            $this->createdResultsUrl = route('public.results', $election);

            $this->dispatch('toast', message: 'Tersimpan', type: 'success');

            return true;
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menyimpan konfigurasi pemilihan baru.', 'name');

            return false;
        }
    }

    public function promptDeleteElection(string $electionId): void
    {
        $el = Election::findOrFail($electionId);
        if ($el->currentPhase() === 'berlangsung') {
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return;
        }

        $this->resetValidation();
        $this->deletingElectionId = $el->id;
        $this->deletingElectionName = $el->name;
        $this->deleteConfirmName = '';
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingElectionId = null;
        $this->deletingElectionName = '';
        $this->deleteConfirmName = '';
    }

    public function confirmDeleteElection(VotingService $voting, SupabaseStorageService $storage): void
    {
        if (! $this->deletingElectionId) {
            return;
        }

        $this->resetValidation();

        if (trim($this->deleteConfirmName) !== trim($this->deletingElectionName)) {
            $this->addError('deleteConfirmName', 'Ketik nama pemilihan secara persis untuk mengonfirmasi penghapusan permanen.');
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return;
        }

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $result = $voting->deleteElection(
                electionId: $this->deletingElectionId,
                operatorId: $operator->id,
                confirmName: $this->deleteConfirmName,
                storage: $storage
            );

            if (! $result['ok']) {
                $this->addError('deleteConfirmName', $result['message']);
                $this->dispatch('toast', message: 'Gagal', type: 'error');

                return;
            }

            $this->closeDeleteModal();
            $this->dispatch('toast', message: 'Terhapus', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal menghapus pemilihan.', 'deleteConfirmName');
        }
    }

    public function render()
    {
        $rows = DB::select('
            select
                ve.*,
                (select count(*) from evoting.candidates c where c.election_id = ve.id) as candidates_count,
                (select count(*) from evoting.voter_rights vr where vr.election_id = ve.id) as rights_count,
                (select count(*) from evoting.voter_rights vr where vr.election_id = ve.id and vr.status = \'sudah_memilih\') as voted_count
            from evoting.v_elections ve
            order by ve.created_at desc
        ');

        $elections = collect($rows)->map(function ($row) {
            $model = new Election([
                'name' => $row->name,
                'max_voter_rights' => (int) $row->max_voter_rights,
                'starts_at' => $row->starts_at,
                'ends_at' => $row->ends_at,
            ]);
            $model->id = $row->id;
            $model->exists = true;

            return [
                'model' => $model,
                'fase' => (string) $row->fase,
                'candidates_count' => (int) $row->candidates_count,
                'rights_count' => (int) $row->rights_count,
                'voted_count' => (int) $row->voted_count,
            ];
        });

        return view('livewire.operator.election-index-page', [
            'elections' => $elections,
        ]);
    }
}
