<?php

namespace App\Livewire\Public;

use App\Livewire\Concerns\HandlesSafeErrors;
use App\Models\PublicElection;
use App\Services\TokenService;
use App\Services\VotingService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Rekapitulasi Real-Time Terbatas')]
class LiveRecapPage extends Component
{
    use HandlesSafeErrors;

    public PublicElection $election;

    public string $accessKeyInput = '';

    public bool $isAuthorized = false;

    public string $loadedAtLabel = '';

    public function mount(PublicElection $election, TokenService $tokens, VotingService $voting): void
    {
        $this->election = $election;
        $this->updateTimestamp();

        $savedHash = session($this->sessionKey());
        if (is_string($savedHash) && strlen($savedHash) === 64) {
            $check = $voting->liveElectionResults($this->election->id, $savedHash);
            if ($check['ok']) {
                $this->isAuthorized = true;
            } else {
                session()->forget($this->sessionKey());
            }
        }
    }

    private function sessionKey(): string
    {
        return 'live_recap_key_hash:'.$this->election->id;
    }

    public function verifyAccessKey(TokenService $tokens, VotingService $voting): void
    {
        $this->resetValidation();

        $rateKey = 'live-recap-verify:'.request()->ip().':'.$this->election->id;
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->addError('accessKeyInput', "Terlalu banyak percobaan. Silakan tunggu {$seconds} detik.");
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return;
        }

        $trimmed = trim($this->accessKeyInput);
        if (strlen($trimmed) !== 64 || preg_match('/\s/', $trimmed)) {
            RateLimiter::hit($rateKey, 60);
            $this->addError('accessKeyInput', 'Access key harus terdiri dari tepat 64 karakter tanpa spasi.');
            $this->dispatch('toast', message: 'Gagal', type: 'error');

            return;
        }

        try {
            $hash = $tokens->hashToken($trimmed);
            $res = $voting->liveElectionResults($this->election->id, $hash);

            if (! $res['ok']) {
                RateLimiter::hit($rateKey, 60);
                $this->addError('accessKeyInput', 'Access key tidak valid atau sudah dicabut oleh Operator.');
                $this->dispatch('toast', message: 'Ditolak', type: 'error');

                return;
            }

            RateLimiter::clear($rateKey);
            session([$this->sessionKey() => $hash]);
            $this->accessKeyInput = '';
            $this->isAuthorized = true;
            $this->updateTimestamp();
            $this->dispatch('toast', message: 'Berhasil', type: 'success');
        } catch (Throwable $e) {
            $this->reportSafeError($e, 'Gagal memverifikasi access key rekap real-time.', 'accessKeyInput');
        }
    }

    public function lockRecapSession(): void
    {
        session()->forget($this->sessionKey());
        $this->isAuthorized = false;
        $this->accessKeyInput = '';
        $this->dispatch('toast', message: 'Terkunci', type: 'info');
    }

    private function updateTimestamp(): void
    {
        $tz = (string) config('evoting.display_timezone', 'Asia/Makassar');
        $now = now()->setTimezone($tz);
        $tzShort = match ($tz) {
            'Asia/Jakarta' => 'WIB',
            'Asia/Makassar', 'Asia/Pontianak' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => $now->format('T'),
        };
        $this->loadedAtLabel = $now->format('d M Y, H:i:s').' '.$tzShort;
    }

    public function render(VotingService $voting)
    {
        $this->updateTimestamp();
        $phase = $this->election->currentPhase();

        $resultsData = [];
        $leadingCandidates = [];
        $totalVotesCast = 0;
        $integrity = $voting->integrityReport($this->election->id);

        if ($this->isAuthorized) {
            $savedHash = (string) session($this->sessionKey(), '');
            $res = $voting->liveElectionResults($this->election->id, $savedHash);

            if (! $res['ok']) {
                session()->forget($this->sessionKey());
                $this->isAuthorized = false;
                $this->dispatch('toast', message: 'Dicabut', type: 'error');
            } else {
                $candidatesById = $this->election->candidates()->get()->keyBy('id');
                $totalVotesCast = (int) collect($res['items'])->sum('vote_count');

                $items = collect($res['items'])->map(function (array $item) use ($candidatesById, $totalVotesCast) {
                    $cand = $candidatesById->get($item['candidate_id']);
                    $share = $totalVotesCast > 0 ? round(($item['vote_count'] / $totalVotesCast) * 100, 1) : 0.0;

                    return [
                        'candidate' => $cand,
                        'number' => $item['number'],
                        'formatted_number' => str_pad((string) $item['number'], 2, '0', STR_PAD_LEFT),
                        'name' => $item['name'],
                        'photo_url' => $cand ? $cand->photoUrl() : '',
                        'vote_count' => $item['vote_count'],
                        'share' => $share,
                    ];
                });

                $maxVote = (int) $items->max('vote_count');
                if ($maxVote > 0) {
                    $leadingCandidates = $items->where('vote_count', $maxVote)->values()->all();
                }

                $resultsData = $items->all();
            }
        }

        return view('livewire.public.live-recap-page', [
            'phase' => $phase,
            'integrity' => $integrity,
            'resultsData' => $resultsData,
            'leadingCandidates' => $leadingCandidates,
            'totalVotesCast' => $totalVotesCast,
        ]);
    }
}
