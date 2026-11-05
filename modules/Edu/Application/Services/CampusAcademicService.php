<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Edu\Domain\Models\EduCampusClass;
use Modules\Edu\Domain\Models\EduCampusKrsEnrollment;
use Modules\Edu\Domain\Models\EduCampusTranscript;
use Modules\Edu\Domain\Models\EduCampusTuitionInvoice;
use Modules\Edu\Ledger\EduAccounts;
use Modules\Edu\Ledger\EduTransactionTypes;
use Modules\Shared\Domain\Math\HamiltonApportionment;
use Modules\Shared\Domain\Numerics\CanonicalHashChain;
use Modules\Shared\Domain\Numerics\IntervalIndex;

final class CampusAcademicService
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * Menghitung batas maksimum SKS mahasiswa berdasarkan IPK semester lalu.
     * Standar Kemendikbud:
     * - IPK >= 3.00: 24 SKS
     * - IPK >= 2.50: 21 SKS
     * - IPK >= 2.00: 18 SKS
     * - IPK < 2.00: 15 SKS
     */
    public function calculateMaxCreditsByGpa(float $gpa): int
    {
        if ($gpa >= 3.00) {
            return 24;
        }
        if ($gpa >= 2.50) {
            return 21;
        }
        if ($gpa >= 2.00) {
            return 18;
        }

        return 15;
    }

    /**
     * Validasi bentrok jadwal menggunakan algoritma IntervalIndex kanonikal.
     *
     * @param  list<array{id: int|string, day_of_week: int, start_minute: int, end_minute: int, label: string}>  $classes
     */
    public function detectScheduleConflicts(array $classes): array
    {
        $intervals = [];
        foreach ($classes as $c) {
            $intervals[] = [
                'id' => $c['id'],
                'territory' => 'DAY_'.$c['day_of_week'],
                'right_type' => 'CLASS_SLOT',
                'start' => (int) $c['start_minute'],
                'end' => (int) $c['end_minute'],
                'exclusive' => true,
            ];
        }

        return IntervalIndex::detectConflicts($intervals);
    }

    /**
     * Submit Rencana Studi (KRS) dengan validasi prasyarat, batas SKS, dan deteksi bentrok jadwal.
     *
     * @param  list<int>  $classIds
     */
    public function submitKrs(int $studentUserId, int $termId, array $classIds, float $previousGpa = 3.00): array
    {
        if (empty($classIds)) {
            throw new InvalidArgumentException('Daftar kelas KRS tidak boleh kosong.');
        }

        $classes = EduCampusClass::with('course')->whereIn('id', $classIds)->get();
        if ($classes->count() !== count(array_unique($classIds))) {
            throw new InvalidArgumentException('Satu atau lebih kelas tidak ditemukan.');
        }

        // 1. Validasi Batas Total SKS vs IPK
        $totalCredits = $classes->sum(fn ($c) => (int) $c->course->credits);
        $maxCredits = $this->calculateMaxCreditsByGpa($previousGpa);
        if ($totalCredits > $maxCredits) {
            throw new DomainException("Total SKS ({$totalCredits}) melebihi batas hak SKS Anda ({$maxCredits}) berdasarkan IPK {$previousGpa}.");
        }

        // 2. Validasi Prasyarat Mata Kuliah (Prerequisite check)
        $studentPassedCourses = EduCampusTranscript::where('student_user_id', $studentUserId)
            ->where('grade_point', '>=', 2.00) // Lulus min C (2.00)
            ->with('course')
            ->get()
            ->pluck('course.course_code')
            ->toArray();

        foreach ($classes as $c) {
            $prereq = $c->course->prerequisite_course_code;
            if ($prereq && ! in_array($prereq, $studentPassedCourses, true)) {
                throw new DomainException("Mata kuliah {$c->course->name} ({$c->course->course_code}) membutuhkan prasyarat {$prereq} yang belum lulus.");
            }
        }

        // 3. Validasi Bentrok Jadwal menggunakan IntervalIndex
        $classList = $classes->map(fn ($c) => [
            'id' => $c->id,
            'day_of_week' => (int) $c->day_of_week,
            'start_minute' => (int) $c->start_minute,
            'end_minute' => (int) $c->end_minute,
            'label' => $c->class_code,
        ])->values()->all();

        $conflictResult = $this->detectScheduleConflicts($classList);
        if ($conflictResult['has_conflict']) {
            throw new DomainException('Terdapat bentrok jadwal kuliah pada pilihan kelas KRS Anda.');
        }

        // 4. Enroll Mahasiswa & Update Kapasitas
        return DB::transaction(function () use ($classes, $studentUserId) {
            $enrollments = [];
            foreach ($classes as $c) {
                if ($c->enrolled_count >= $c->max_capacity) {
                    throw new DomainException("Kelas {$c->class_code} telah penuh (kapasitas: {$c->max_capacity}).");
                }

                $enrollment = EduCampusKrsEnrollment::firstOrCreate(
                    [
                        'class_id' => $c->id,
                        'student_user_id' => $studentUserId,
                    ],
                    [
                        'status' => 'approved',
                    ]
                );

                $c->increment('enrolled_count');
                $enrollments[] = $enrollment;
            }

            return $enrollments;
        });
    }

    /**
     * Mengunci nilai transkrip mahasiswa secara append-only menggunakan CanonicalHashChain.
     */
    public function lockGradeToTranscript(int $studentUserId, int $courseId, int $termId, float $gradePoint, string $letterGrade): EduCampusTranscript
    {
        $existing = EduCampusTranscript::where('student_user_id', $studentUserId)
            ->where('course_id', $courseId)
            ->where('term_id', $termId)
            ->first();

        if ($existing) {
            throw new DomainException('Nilai untuk mata kuliah dan semester ini sudah terkunci.');
        }

        // Ambil hash terakhir dari mahasiswa ini untuk membentuk rantai hash kanonikal
        $lastTranscript = EduCampusTranscript::where('student_user_id', $studentUserId)
            ->orderByDesc('id')
            ->first();

        $prevHash = $lastTranscript ? $lastTranscript->certificate_hash : CanonicalHashChain::GENESIS_PREV_HASH;

        $payload = [
            'student_user_id' => $studentUserId,
            'course_id' => $courseId,
            'term_id' => $termId,
            'grade_point' => number_format($gradePoint, 2, '.', ''),
            'letter_grade' => strtoupper(trim($letterGrade)),
        ];

        $certHash = CanonicalHashChain::computeHash($prevHash, $payload);

        return EduCampusTranscript::create([
            'student_user_id' => $studentUserId,
            'course_id' => $courseId,
            'term_id' => $termId,
            'grade_point' => $gradePoint,
            'letter_grade' => strtoupper(trim($letterGrade)),
            'prev_hash' => $prevHash,
            'certificate_hash' => $certHash,
            'is_locked' => true,
        ]);
    }

    /**
     * Terbitkan tagihan UKT mahasiswa dengan beasiswa terbatas (scholarship <= gross)
     * dan pembukuan double-entry ledger.
     */
    public function issueTuitionInvoice(
        int $studentUserId,
        int $termId,
        int $grossTuitionIdr,
        int $scholarshipDeductionIdr,
        string $idempotencyKey
    ): EduCampusTuitionInvoice {
        if ($scholarshipDeductionIdr > $grossTuitionIdr) {
            throw new InvalidArgumentException("Potongan beasiswa (Rp{$scholarshipDeductionIdr}) tidak boleh melebihi UKT kotor (Rp{$grossTuitionIdr}).");
        }

        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $existing = EduCampusTuitionInvoice::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $netPayable = $grossTuitionIdr - $scholarshipDeductionIdr;
        $count = EduCampusTuitionInvoice::count() + 1;
        $invoiceCode = sprintf('INV-CAMPUS-%06d', $count);

        return DB::transaction(function () use (
            $invoiceCode,
            $studentUserId,
            $termId,
            $grossTuitionIdr,
            $scholarshipDeductionIdr,
            $netPayable,
            $idempotencyKey
        ) {
            $invoice = EduCampusTuitionInvoice::create([
                'invoice_code' => $invoiceCode,
                'student_user_id' => $studentUserId,
                'term_id' => $termId,
                'gross_amount_idr' => $grossTuitionIdr,
                'scholarship_deduction_idr' => $scholarshipDeductionIdr,
                'net_payable_idr' => $netPayable,
                'status' => 'issued',
                'idempotency_key' => $idempotencyKey,
            ]);

            // Double-entry posting:
            // Debit Piutang UKT (edu:tuition_receivable:IDR) +$netPayable
            // Kredit Pendapatan UKT (edu:tuition_revenue:IDR) -$netPayable
            $this->ledgerService->post(new PostingDTO(
                type: EduTransactionTypes::EDU_TUITION_PAYMENT->value,
                description: "Penagihan UKT Semester {$invoiceCode}",
                idempotencyKey: "edu:tuition:invoice:{$invoiceCode}:{$idempotencyKey}",
                entries: [
                    PostingEntryDTO::forCode(EduAccounts::TUITION_RECEIVABLE, 'IDR', $netPayable),
                    PostingEntryDTO::forCode(EduAccounts::TUITION_REVENUE, 'IDR', -$netPayable),
                ],
                referenceType: 'edu_campus_tuition_invoice',
                referenceId: (string) $invoice->id,
            ));

            return $invoice;
        });
    }

    /**
     * Hitung jadwal cicilan UKT bebas pembulatan pincang menggunakan HamiltonApportionment.
     *
     * @return array<int, int> [installment_index => amount_idr]
     */
    public function calculateInstallmentsHamilton(int $totalNetPayable, int $numberOfInstallments): array
    {
        if ($numberOfInstallments <= 0) {
            throw new InvalidArgumentException('Jumlah cicilan harus minimal 1.');
        }

        $weights = array_fill(0, $numberOfInstallments, 1.0);
        $result = HamiltonApportionment::apportion($totalNetPayable, $weights);

        return $result->allocated;
    }
}
