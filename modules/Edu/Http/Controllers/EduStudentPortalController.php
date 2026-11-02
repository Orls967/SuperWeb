<?php

declare(strict_types=1);

namespace Modules\Edu\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Edu\Domain\Models\EduCertificate;
use Modules\Edu\Domain\Models\EduExamAttempt;

final class EduStudentPortalController extends Controller
{
    public function dashboard(): Response
    {
        return response('<h1>Student Dashboard</h1>', 200);
    }

    public function showCertificate(string $id): JsonResponse
    {
        $cert = EduCertificate::findOrFail($id);
        $currentUserId = auth()->id();

        $isOwner = auth()->user()?->hasRole('admin')
            || (int) $cert->student_user_id === (int) $currentUserId
            || (string) $cert->student_party_id === "student-{$currentUserId}";

        if (! $isOwner) {
            abort(403, 'Unauthorized access to another student certificate (Anti-IDOR).');
        }

        return response()->json([
            'id' => $cert->id,
            'certificate_number' => $cert->certificate_number,
            'status' => $cert->status,
        ]);
    }

    public function showAttempt(string $id): JsonResponse
    {
        $attempt = EduExamAttempt::findOrFail($id);
        $currentUserId = auth()->id();

        $isOwner = auth()->user()?->hasRole('admin')
            || (int) $attempt->student_user_id === (int) $currentUserId
            || (string) $attempt->student_party_uuid === "student-{$currentUserId}";

        if (! $isOwner) {
            abort(403, 'Unauthorized access to another student exam attempt (Anti-IDOR).');
        }

        return response()->json([
            'id' => $attempt->id,
            'attempt_code' => $attempt->attempt_code,
            'score' => $attempt->score,
            'is_passed' => $attempt->is_passed,
        ]);
    }
}
