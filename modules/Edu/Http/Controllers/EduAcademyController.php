<?php

declare(strict_types=1);

namespace Modules\Edu\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Edu\Application\Actions\EnrollStudentInCohortAction;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduProgram;

class EduAcademyController extends Controller
{
    public function index(): View
    {
        $programs = EduProgram::query()->latest()->take(15)->get();
        $cohorts = EduCohort::query()->latest()->take(15)->get();
        $enrollments = EduEnrollment::query()->latest()->take(15)->get();

        return view('edu::academy.index', compact('programs', 'cohorts', 'enrollments'));
    }

    public function show(string $id): View
    {
        $cohort = EduCohort::findOrFail($id);
        $program = EduProgram::find($cohort->program_id);
        $enrollments = EduEnrollment::where('cohort_id', $cohort->id)->get();

        return view('edu::academy.show', compact('cohort', 'program', 'enrollments'));
    }

    public function enroll(Request $request, EnrollStudentInCohortAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'student_party_id' => ['required', 'string'],
            'cohort_id' => ['required', 'string'],
            'amount_paid_minor' => ['nullable', 'integer'],
            'corporate_client_id' => ['nullable', 'string'],
        ]);

        $enrollment = $action->execute($validated);

        return redirect()->route('edu.academy.index')->with('success', "Siswa berhasil didaftarkan [{$enrollment->enrollment_code}].");
    }
}
