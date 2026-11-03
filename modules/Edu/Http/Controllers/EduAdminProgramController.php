<?php

declare(strict_types=1);

namespace Modules\Edu\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduEnrollment;
use Modules\Edu\Domain\Models\EduProgram;

final class EduAdminProgramController extends Controller
{
    public function index(): Response
    {
        $programs = EduProgram::all();
        $cohorts = EduCohort::all();
        $enrollments = EduEnrollment::all();

        return response(view('edu::academy.index', [
            'programs' => $programs,
            'cohorts' => $cohorts,
            'enrollments' => $enrollments,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'program_code' => ['required', 'string', 'max:64', 'unique:edu_programs,program_code'],
            'title' => ['required', 'string', 'max:255'],
            'industry_sector' => ['required', 'string', 'max:64'],
            'tuition_fee_minor' => ['required', 'integer', 'min:0'],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'validity_months' => ['nullable', 'integer', 'min:1'],
        ]);

        EduProgram::create([
            'id' => (string) Str::uuid(),
            'program_code' => (string) $request->input('program_code'),
            'title' => (string) $request->input('title'),
            'industry_sector' => (string) $request->input('industry_sector'),
            'tuition_fee_minor' => (int) $request->input('tuition_fee_minor'),
            'passing_score' => (int) ($request->input('passing_score') ?? 75),
            'validity_months' => (int) ($request->input('validity_months') ?? 24),
            'status' => 'ACTIVE',
        ]);

        return redirect('/academy/admin/programs');
    }
}
