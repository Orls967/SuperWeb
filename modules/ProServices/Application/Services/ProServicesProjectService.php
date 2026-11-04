<?php

declare(strict_types=1);

namespace Modules\ProServices\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Hcm\Contracts\EmployeeDirectory;
use Modules\ProServices\Domain\Models\ProServicesInvoice;
use Modules\ProServices\Domain\Models\ProServicesProject;
use Modules\ProServices\Domain\Models\ProServicesProjectMember;
use Modules\ProServices\Domain\Models\ProServicesRevenueRecognition;
use Modules\ProServices\Domain\Models\ProServicesTimesheet;
use Modules\ProServices\Ledger\ProServicesAccounts;
use Modules\ProServices\Ledger\ProServicesTransactionTypes;
use Modules\Shared\Domain\Algorithms\Revenue\CostToCostRecognition;

final class ProServicesProjectService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly ClockInterface $clock,
        private readonly EmployeeDirectory $employeeDirectory,
    ) {}

    public function createProject(array $payload): ProServicesProject
    {
        return ProServicesProject::create([
            'project_code' => $payload['project_code'],
            'client_party_id' => (int) $payload['client_party_id'],
            'contract_reference' => $payload['contract_reference'],
            'billing_type' => $payload['billing_type'],
            'total_contract_idr' => (int) ($payload['total_contract_idr'] ?? 0),
            'estimated_cost_idr' => (int) ($payload['estimated_cost_idr'] ?? 0),
            'status' => $payload['status'] ?? 'active',
        ]);
    }

    public function addProjectMember(int $projectId, array $payload): ProServicesProjectMember
    {
        $employeeId = (int) $payload['employee_id'];
        $emp = $this->employeeDirectory->find($employeeId);
        if ($emp === null) {
            // fallback
        }

        return ProServicesProjectMember::create([
            'project_id' => $projectId,
            'employee_id' => $employeeId,
            'role_name' => $payload['role_name'],
            'hourly_rate_idr' => (int) $payload['hourly_rate_idr'],
            'active' => $payload['active'] ?? true,
        ]);
    }

    public function submitTimesheet(int $projectId, int $projectMemberId, array $payload): ProServicesTimesheet
    {
        $member = ProServicesProjectMember::findOrFail($projectMemberId);
        $code = 'TS-'.$projectId.'-'.$projectMemberId.'-'.time().'-'.random_int(100, 999);

        return ProServicesTimesheet::create([
            'timesheet_code' => $code,
            'project_id' => $projectId,
            'project_member_id' => $projectMemberId,
            'work_date' => $payload['work_date'],
            'hours_worked' => (int) $payload['hours_worked'],
            'description' => $payload['description'] ?? null,
            'hourly_rate_idr' => $member->hourly_rate_idr,
            'status' => 'submitted',
            'is_billed' => false,
            'is_reversal' => false,
        ]);
    }

    public function approveTimesheet(int $timesheetId, int $approverUserId): ProServicesTimesheet
    {
        $ts = ProServicesTimesheet::findOrFail($timesheetId);
        $ts->update([
            'status' => 'approved',
            'approved_by_user_id' => $approverUserId,
            'approved_at' => $this->clock->now(),
        ]);

        return $ts;
    }

    public function reverseTimesheet(int $timesheetId, string $reason): ProServicesTimesheet
    {
        $original = ProServicesTimesheet::findOrFail($timesheetId);
        $code = 'REV-'.$original->timesheet_code;

        return ProServicesTimesheet::create([
            'timesheet_code' => $code,
            'project_id' => $original->project_id,
            'project_member_id' => $original->project_member_id,
            'work_date' => $original->work_date,
            'hours_worked' => -$original->hours_worked,
            'description' => "Reversal: {$reason}",
            'hourly_rate_idr' => $original->hourly_rate_idr,
            'status' => 'approved',
            'is_billed' => false,
            'is_reversal' => true,
            'reversed_timesheet_id' => $original->id,
        ]);
    }

    public function getBillableHours(int $projectId): int
    {
        return (int) ProServicesTimesheet::where('project_id', $projectId)
            ->where('status', 'approved')
            ->where('is_billed', false)
            ->sum('hours_worked');
    }

    public function recognizeRevenueFixedFee(
        int $projectId,
        int $cumulativeCostIdr,
        int $totalEstimatedCostIdr,
        string $idempotencyKey,
    ): ProServicesRevenueRecognition {
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $existing = ProServicesRevenueRecognition::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $project = ProServicesProject::findOrFail($projectId);

        // Previous recognition
        $previousRecognized = (int) ProServicesRevenueRecognition::where('project_id', $projectId)
            ->max('cumulative_recognized_idr');

        $result = CostToCostRecognition::calculate(
            contractAmountIdr: $project->total_contract_idr,
            cumulativeCostIdr: $cumulativeCostIdr,
            totalEstimatedCostIdr: $totalEstimatedCostIdr,
            previouslyRecognizedIdr: $previousRecognized,
        );

        return DB::transaction(function () use ($project, $cumulativeCostIdr, $totalEstimatedCostIdr, $result, $idempotencyKey) {
            $recognition = ProServicesRevenueRecognition::create([
                'recognition_code' => 'REC-'.$project->id.'-'.time().'-'.random_int(100, 999),
                'project_id' => $project->id,
                'cumulative_cost_idr' => $cumulativeCostIdr,
                'total_estimated_cost_idr' => $totalEstimatedCostIdr,
                'cumulative_recognized_idr' => $result->cumulativeRecognizedIdr,
                'period_recognized_idr' => $result->periodRevenueIdr,
                'certificate_sha256' => $result->certificateSha256,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Ledger posting D25-4:
            // Debit: psv:contract_asset:{project}:IDR
            // Kredit: psv:revenue:IDR
            if ($result->periodRevenueIdr > 0) {
                $this->ledgerService->post(new PostingDTO(
                    type: ProServicesTransactionTypes::REVENUE_RECOGNITION->value,
                    description: "Pengakuan pendapatan proyek {$project->project_code}",
                    idempotencyKey: "psv:rec:{$recognition->id}",
                    entries: Posting::lines()
                        ->debit(ProServicesAccounts::contractAsset($project->id), $result->periodRevenueIdr)
                        ->credit(ProServicesAccounts::REVENUE, $result->periodRevenueIdr)
                        ->toEntries('IDR'),
                    referenceType: 'PSV_PROJECT',
                    referenceId: (string) $project->id,
                ));
            }

            return $recognition;
        });
    }

    public function createInvoice(int $projectId, int $amountIdr, string $idempotencyKey): ProServicesInvoice
    {
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Idempotency key wajib diisi.');
        }

        $existing = ProServicesInvoice::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $project = ProServicesProject::findOrFail($projectId);

        return DB::transaction(function () use ($project, $amountIdr, $idempotencyKey) {
            $invoice = ProServicesInvoice::create([
                'invoice_code' => 'INV-'.$project->id.'-'.time().'-'.random_int(100, 999),
                'project_id' => $project->id,
                'client_party_id' => $project->client_party_id,
                'subtotal_idr' => $amountIdr,
                'idempotency_key' => $idempotencyKey,
                'status' => 'issued',
            ]);

            // Check current contract asset balance
            $currentAsset = (int) ProServicesRevenueRecognition::where('project_id', $project->id)
                ->max('cumulative_recognized_idr');
            $billedSoFar = (int) ProServicesInvoice::where('project_id', $project->id)
                ->where('id', '!=', $invoice->id)
                ->sum('subtotal_idr');

            $availableAsset = max(0, $currentAsset - $billedSoFar);
            $assetPortion = min($amountIdr, $availableAsset);
            $liabilityPortion = $amountIdr - $assetPortion;

            $posting = Posting::lines()->debit(ProServicesAccounts::clientReceivable($project->client_party_id), $amountIdr);

            if ($assetPortion > 0) {
                $posting->credit(ProServicesAccounts::contractAsset($project->id), $assetPortion);
            }

            if ($liabilityPortion > 0) {
                $posting->credit(ProServicesAccounts::contractLiability($project->id), $liabilityPortion);
            }

            $this->ledgerService->post(new PostingDTO(
                type: ProServicesTransactionTypes::INVOICE_ISSUANCE->value,
                description: "Tagihan invoice {$invoice->invoice_code} untuk proyek {$project->project_code}",
                idempotencyKey: "psv:inv:{$invoice->id}",
                entries: $posting->toEntries('IDR'),
                referenceType: 'PSV_INVOICE',
                referenceId: (string) $invoice->id,
            ));

            return $invoice;
        });
    }
}
