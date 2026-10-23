<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Edu\Domain\Models\EduCohort;
use Modules\Edu\Domain\Models\EduProgram;

final class EduAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'edu:audit';
    }

    public function corrupt(): string
    {
        $program = EduProgram::firstOrCreate(
            ['program_code' => 'EDU-AUDIT-01'],
            [
                'id' => (string) Str::uuid(),
                'title' => 'Audit Test Program',
                'industry_sector' => 'AUTOMOTIVE',
                'total_sessions' => 10,
                'tuition_fee_minor' => 10_000_000,
                'status' => 'ACTIVE',
            ]
        );

        EduCohort::create([
            'id' => (string) Str::uuid(),
            'cohort_code' => 'COH-CORRUPT-99',
            'program_id' => $program->id,
            'instructor_party_id' => (string) Str::uuid(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'max_capacity' => 10,
            'enrolled_count' => 50,
            'status' => 'ACTIVE',
        ]);

        return 'Edu audit FAILED';
    }
}
