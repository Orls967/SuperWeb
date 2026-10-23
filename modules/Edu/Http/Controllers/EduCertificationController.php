<?php

declare(strict_types=1);

namespace Modules\Edu\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Edu\Application\Actions\IssueVerifiedCompetencyCertificateAction;
use Modules\Edu\Domain\Models\EduCertificate;

class EduCertificationController extends Controller
{
    public function index(): View
    {
        $certificates = EduCertificate::query()->latest()->take(20)->get();

        return view('edu::certification.index', compact('certificates'));
    }

    public function verify(string $hash): View
    {
        $certificate = EduCertificate::where('certificate_hash', $hash)->first();

        return view('edu::certification.verify', compact('certificate', 'hash'));
    }

    public function issue(Request $request, IssueVerifiedCompetencyCertificateAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'enrollment_id' => ['required', 'string'],
            'skill_competency_code' => ['required', 'string'],
            'expires_at' => ['nullable', 'date'],
            'cpd_points_earned' => ['nullable', 'integer'],
        ]);

        $cert = $action->execute($validated);

        return redirect()->route('edu.certification.index')->with('success', "Sertifikat diterbitkan [{$cert->certificate_number}].");
    }
}
