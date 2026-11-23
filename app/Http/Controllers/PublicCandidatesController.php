<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\PublicElection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicCandidatesController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $elections = $this->listedElections()->orderByDesc('starts_at')->orderBy('id')->get();

        if ($elections->count() === 1 && ! $request->boolean('pilih')) {
            return redirect()->route('public.candidates', $elections->first());
        }

        return view('public.candidates', ['elections' => $elections]);
    }

    public function show(string $election): View
    {
        $election = $this->listedElections()->findOrFail($election);
        $candidates = $election->candidates()->get()->map(fn (Candidate $candidate) => [
            'number' => $candidate->formattedNumber(),
            'name' => $candidate->name,
            'photo' => $candidate->photo_path && ($candidate->public_photo_visible ?? true) ? $candidate->photoUrl() : null,
            'vision' => $candidate->vision,
            'missions' => $candidate->missionItems(),
        ]);

        return view('public.candidates', compact('election', 'candidates'));
    }

    private function listedElections(): Builder
    {
        return PublicElection::query()
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereRaw('name !~* ?', ['^[[:space:]]*(Train(?:ing)?|Simula(?:si|tion)s?|Tes(?:t(?:ing)?)?|Latih(?:an)?)'])
            ->whereHas('candidates');
    }
}
