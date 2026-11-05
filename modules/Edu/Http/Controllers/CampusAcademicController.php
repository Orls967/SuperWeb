<?php

declare(strict_types=1);

namespace Modules\Edu\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Edu\Application\Services\CampusAcademicService;
use Modules\Edu\Domain\Models\EduCampusClass;
use Modules\Edu\Domain\Models\EduCampusCourse;
use Modules\Edu\Domain\Models\EduCampusInstitution;
use Modules\Edu\Domain\Models\EduCampusKrsEnrollment;
use Modules\Edu\Domain\Models\EduCampusTerm;
use Modules\Edu\Domain\Models\EduCampusTranscript;
use Modules\Edu\Domain\Models\EduCampusTuitionInvoice;

final class CampusAcademicController extends Controller
{
    public function __construct(
        private readonly CampusAcademicService $academicService
    ) {}

    public function institutions()
    {
        $institutions = EduCampusInstitution::withCount(['terms', 'courses'])->get();

        return view('edu::campus.institutions', compact('institutions'));
    }

    public function courses()
    {
        $courses = EduCampusCourse::with('institution')->get();

        return view('edu::campus.courses', compact('courses'));
    }

    public function classes()
    {
        $classes = EduCampusClass::with(['course', 'term'])->get();

        return view('edu::campus.classes', compact('classes'));
    }

    public function krs(Request $request)
    {
        $user = $request->user();
        $term = EduCampusTerm::where('is_active', true)->first();

        $enrollments = EduCampusKrsEnrollment::where('student_user_id', $user->id)
            ->with(['class.course', 'class.term'])
            ->get();

        return view('edu::campus.krs', compact('enrollments', 'term'));
    }

    public function transcripts(Request $request)
    {
        $user = $request->user();

        // IDOR protection: Student hanya dapat melihat transkripnya sendiri
        $studentId = $user->role === 'student' ? $user->id : (int) $request->query('student_id', $user->id);

        $transcripts = EduCampusTranscript::where('student_user_id', $studentId)
            ->with(['course', 'term'])
            ->orderBy('id')
            ->get();

        return view('edu::campus.transcripts', compact('transcripts', 'studentId'));
    }

    public function tuitionInvoices(Request $request)
    {
        $user = $request->user();

        // IDOR protection: Student hanya melihat tagihan UKT miliknya
        $invoices = EduCampusTuitionInvoice::query()
            ->when($user->role === 'student', fn ($q) => $q->where('student_user_id', $user->id))
            ->with('term')
            ->orderByDesc('id')
            ->get();

        return view('edu::campus.tuition', compact('invoices'));
    }
}
