<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Actions\DecideSiuCaseAction;
use Modules\Insurance\Domain\Models\SiuInvestigation;

final class SiuInvestigationController extends Controller
{
    public function index()
    {
        $cases = SiuInvestigation::with(['assessment', 'subject', 'investigator', 'decider'])
            ->orderByDesc('id')
            ->paginate(15);

        return view('insurance::siu.index', compact('cases'));
    }

    public function show(int $id)
    {
        $case = SiuInvestigation::with(['assessment', 'subject', 'investigator', 'decider'])->findOrFail($id);

        return view('insurance::siu.show', compact('case'));
    }

    public function decide(Request $request, int $id, DecideSiuCaseAction $action)
    {
        $validated = $request->validate([
            'decision' => 'required|in:confirmed_fraud,cleared',
            'decision_notes' => 'required|string|min:5|max:1000',
            'identity_to_blacklist' => 'nullable|string|max:64',
        ]);

        $action->execute(
            investigationId: $id,
            decision: $validated['decision'],
            decisionNotes: $validated['decision_notes'],
            decidedByUserId: (int) $request->user()->id,
            identityNumberToBlacklist: $validated['identity_to_blacklist'] ?? null,
        );

        return redirect()->route('insurance.siu.show', $id)
            ->with('success', "Putusan kasus SIU #{$id} berhasil dicatat.");
    }
}
