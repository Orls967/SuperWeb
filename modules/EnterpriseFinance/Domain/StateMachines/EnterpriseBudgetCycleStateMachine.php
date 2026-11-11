<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\StateMachines;

use Modules\EnterpriseFinance\Domain\Exceptions\BudgetGuardrailBreachException;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetInvalidTransitionException;
use Modules\EnterpriseFinance\Domain\Exceptions\BudgetMakerCheckerViolationException;
use Modules\EnterpriseFinance\Domain\Models\BudgetHistory;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;

/**
 * State Machine Siklus Hidup Anggaran Korporat (D54-1: 8 Status).
 *
 * Alur Status:
 * DRAFT -> SUBMITTED -> REVIEWED -> APPROVED -> ACTIVE -> FROZEN -> REVISED -> CLOSED.
 *
 * Dilengkapi pengujian guardrail ketat per transisi, penegakan Four-Eyes Principle,
 * versioning resmi pada status REVISED, dan pencatatan riwayat append-only.
 */
class EnterpriseBudgetCycleStateMachine
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_REVIEWED = 'REVIEWED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_FROZEN = 'FROZEN';

    public const STATUS_REVISED = 'REVISED';

    public const STATUS_CLOSED = 'CLOSED';

    /**
     * Matriks transisi status yang sah.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [
            self::STATUS_SUBMITTED,
            self::STATUS_CLOSED,
        ],
        self::STATUS_SUBMITTED => [
            self::STATUS_REVIEWED,
            self::STATUS_DRAFT,
            self::STATUS_CLOSED,
        ],
        self::STATUS_REVIEWED => [
            self::STATUS_APPROVED,
            self::STATUS_DRAFT,
            self::STATUS_CLOSED,
        ],
        self::STATUS_APPROVED => [
            self::STATUS_ACTIVE,
            self::STATUS_FROZEN,
            self::STATUS_CLOSED,
        ],
        self::STATUS_ACTIVE => [
            self::STATUS_FROZEN,
            self::STATUS_REVISED,
            self::STATUS_CLOSED,
        ],
        self::STATUS_FROZEN => [
            self::STATUS_ACTIVE,
            self::STATUS_REVISED,
            self::STATUS_CLOSED,
        ],
        self::STATUS_REVISED => [
            // Status final untuk versi lama yang telah digantikan versi baru
        ],
        self::STATUS_CLOSED => [
            // Status terminal akhir periode
        ],
    ];

    /**
     * Mengeksekusi transisi status anggaran dengan evaluasi seluruh guardrail bisnis.
     *
     * @throws BudgetInvalidTransitionException
     * @throws BudgetMakerCheckerViolationException
     * @throws BudgetGuardrailBreachException
     */
    public function transition(
        EnterpriseBudget $budget,
        string $toStatus,
        int $operatorId,
        ?string $reason = null
    ): EnterpriseBudget {
        $fromStatus = $budget->status ?? self::STATUS_DRAFT;
        $allowed = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw new BudgetInvalidTransitionException($fromStatus, $toStatus);
        }

        // 1. Guard Transisi DRAFT -> SUBMITTED (Validasi kelengkapan hierarki)
        if ($toStatus === self::STATUS_SUBMITTED) {
            $this->validateSubmissionGuard($budget);
        }

        // 2. Guard Transisi REVIEWED -> APPROVED (Four-Eyes Principle / Maker-Checker)
        if ($toStatus === self::STATUS_APPROVED) {
            $this->validateApprovalGuard($budget, $operatorId);
            $budget->approved_by = $operatorId;
        }

        // 3. Guard Transisi APPROVED -> ACTIVE (Validasi tahun fiskal dan alokasi)
        if ($toStatus === self::STATUS_ACTIVE) {
            $this->validateActivationGuard($budget);
        }

        // 4. Guard Transisi ACTIVE -> FROZEN (Pembekuan operasional)
        if ($toStatus === self::STATUS_FROZEN) {
            $this->validateFreezeGuard($budget, $reason);
        }

        // 5. Guard Transisi ACTIVE/FROZEN -> REVISED (Penerbitan revisi versi resmi)
        if ($toStatus === self::STATUS_REVISED) {
            return $this->executeRevisionWithVersioning($budget, $operatorId, $reason);
        }

        // 6. Guard Transisi -> CLOSED (Penutupan akhir siklus)
        if ($toStatus === self::STATUS_CLOSED) {
            $this->validateCloseGuard($budget);
        }

        // Terapkan perubahan status
        $budget->status = $toStatus;
        $budget->save();

        // Rekam riwayat append-only
        if ($budget->id !== null) {
            BudgetHistory::create([
                'budget_id' => $budget->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'operator_id' => $operatorId,
            ]);
        }

        return $budget;
    }

    /**
     * Memvalidasi prasyarat pengajuan anggaran (DRAFT -> SUBMITTED).
     */
    protected function validateSubmissionGuard(EnterpriseBudget $budget): void
    {
        if (empty($budget->budget_code) || empty($budget->fiscal_year)) {
            throw new BudgetGuardrailBreachException(
                'Pengajuan anggaran ditolak: Kode anggaran dan tahun fiskal wajib terdefinisi lengkap.'
            );
        }

        if ($budget->allocated_amount_idr < 0) {
            throw new BudgetGuardrailBreachException(
                "Pengajuan anggaran ditolak: Plafon alokasi anggaran tidak boleh negatif ({$budget->allocated_amount_idr})."
            );
        }
    }

    /**
     * Memvalidasi aturan Four-Eyes Principle (Maker-Checker Guardrail).
     */
    protected function validateApprovalGuard(EnterpriseBudget $budget, int $operatorId): void
    {
        if ($budget->created_by !== null && (int) $budget->created_by === $operatorId) {
            throw new BudgetMakerCheckerViolationException(
                "Pelanggaran Four-Eyes Principle: Operator pembuat (#{$operatorId}) dilarang menyetujui anggaran miliknya sendiri."
            );
        }
    }

    /**
     * Memvalidasi aktivasi pagu anggaran.
     */
    protected function validateActivationGuard(EnterpriseBudget $budget): void
    {
        if ($budget->approved_by === null && $budget->status !== self::STATUS_FROZEN) {
            throw new BudgetGuardrailBreachException(
                'Aktivasi anggaran ditolak: Anggaran belum memperoleh persetujuan resmi (approved_by kosong).'
            );
        }
    }

    /**
     * Memvalidasi pembekuan pagu anggaran.
     */
    protected function validateFreezeGuard(EnterpriseBudget $budget, ?string $reason): void
    {
        if (empty($reason)) {
            throw new BudgetGuardrailBreachException(
                'Pembekuan anggaran ditolak: Alasan pembekuan anggaran wajib disertakan secara eksplisit.'
            );
        }
    }

    /**
     * Mengeksekusi revisi anggaran dengan versioning (D54-1).
     *
     * Versi lama ditandai final REVISED, versi baru dibuat berstatus APPROVED lalu ACTIVE.
     */
    protected function executeRevisionWithVersioning(
        EnterpriseBudget $oldBudget,
        int $operatorId,
        ?string $reason
    ): EnterpriseBudget {
        $fromStatus = $oldBudget->status;
        $oldBudget->status = self::STATUS_REVISED;
        $oldBudget->save();

        if ($oldBudget->id !== null) {
            BudgetHistory::create([
                'budget_id' => $oldBudget->id,
                'from_status' => $fromStatus,
                'to_status' => self::STATUS_REVISED,
                'reason' => "Revisi versi anggaran disetujui. Digantikan oleh versi penerus. Catatan: {$reason}",
                'operator_id' => $operatorId,
            ]);
        }

        // Buat salinan versi baru dengan nomor revisi inkremental
        $newBudget = $oldBudget->replicate();
        $baseCode = preg_replace('/-REV\d+$/', '', $oldBudget->budget_code);
        $nextRevNumber = 1;
        if (preg_match('/-REV(\d+)$/', $oldBudget->budget_code, $matches)) {
            $nextRevNumber = ((int) $matches[1]) + 1;
        }
        $newBudget->budget_code = "{$baseCode}-REV{$nextRevNumber}";
        $newBudget->status = self::STATUS_ACTIVE;
        $newBudget->created_by = $operatorId;
        $newBudget->approved_by = $operatorId;
        $newBudget->save();

        if ($newBudget->id !== null) {
            BudgetHistory::create([
                'budget_id' => $newBudget->id,
                'from_status' => self::STATUS_DRAFT,
                'to_status' => self::STATUS_ACTIVE,
                'reason' => "Versi revisi aktif baru diterbitkan dari induk #{$oldBudget->id}",
                'operator_id' => $operatorId,
            ]);
        }

        return $newBudget;
    }

    /**
     * Memvalidasi penutupan anggaran (CLOSING).
     */
    protected function validateCloseGuard(EnterpriseBudget $budget): void
    {
        // Memeriksa apakah ada komitmen terbuka yang belum terselesaikan
        $openEncumbrances = $budget->encumbrances()
            ->where('status', 'RESERVED')
            ->count();

        if ($openEncumbrances > 0) {
            throw new BudgetGuardrailBreachException(
                "Penutupan anggaran ditolak: Terdapat {$openEncumbrances} komitmen terbuka (RESERVED) yang belum dilikuidasi atau dilepas."
            );
        }
    }

    /**
     * Mendapatkan daftar status transisi yang diizinkan untuk status saat ini.
     *
     * @return list<string>
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
    }
}
