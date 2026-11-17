<?php

namespace App\Livewire\Operator;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\Election;
use App\Models\StaffAccount;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Daftar Pemilihan')]
class ElectionIndexPage extends Component
{
    use HandlesSafeErrors;

    public bool $showCreateModal = false;

    public string $name = '';

    public int $maxVoterRights = 300;

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->name = '';
        $this->maxVoterRights = 300;
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function createElection(AuditService $audit): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'maxVoterRights' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [
            'name.required' => 'Nama pemilihan wajib diisi.',
            'name.min' => 'Nama pemilihan minimal 3 karakter.',
            'maxVoterRights.required' => 'Batas maksimum hak pilih wajib diisi.',
            'maxVoterRights.min' => 'Batas maksimum hak pilih minimal 1.',
        ]);

        try {
            /** @var StaffAccount $operator */
            $operator = Auth::guard('staff')->user();

            $election = Election::create([
                'name' => preg_replace('/\s+/u', ' ', trim($this->name)) ?? trim($this->name),
                'max_voter_rights' => $this->maxVoterRights,
            ]);

            $audit->log(
                actorType: 'operator',
                actorId: $operator->id,
                action: 'buat_pemilihan',
                objectType: 'election',
                objectId: $election->id,
                detail: [
                    'name' => $election->name,
                    'max_voter_rights' => $election->max_voter_rights,
                ]
            );

            session()->flash('success', 'Pemilihan berhasil dibuat pada Fase Persiapan. Silakan tambahkan pasangan calon dan atur jadwal.');
            $this->redirectRoute('operator.elections.detail', $election);
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal membuat pemilihan baru. Silakan coba kembali.', 'name');
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
