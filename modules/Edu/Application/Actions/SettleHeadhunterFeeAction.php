<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Edu\Domain\Exceptions\HeadhunterWarrantyNotExpiredException;
use Modules\Edu\Domain\Models\EduHeadhunterFee;
use Modules\Edu\Domain\Models\EduJobOpening;
use Modules\Edu\Domain\Models\EduPlacement;
use Modules\Edu\Ledger\EduAccounts;
use Modules\Shared\Application\BaseAction;

class SettleHeadhunterFeeAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected ClockInterface $clock
    ) {}

    /**
     * Calculates and holds the headhunter fee in guarantee escrow.
     */
    public function holdFee(EduPlacement $placement): EduHeadhunterFee
    {
        $opening = EduJobOpening::findOrFail($placement->opening_id);
        $annualSalary = (int) $opening->annual_salary_minor;
        $feeBps = (int) $opening->headhunter_fee_bps;

        // Exact minor integer HalfUp calculation (U-1)
        $feeMinor = (int) round(($annualSalary * $feeBps) / 10000);

        return DB::transaction(function () use ($placement, $feeMinor) {
            $idempotencyKey = "edu:hh:hold:{$placement->id}";

            $fee = EduHeadhunterFee::firstOrCreate(
                ['placement_id' => $placement->id],
                [
                    'id' => sprintf('00000000-0009-4000-8000-%012s', substr(hash('sha256', "{$placement->id}"), 0, 12)),
                    'fee_minor' => $feeMinor,
                    'status' => 'HELD',
                    'held_at' => $this->clock->{'now'}(),
                    'idempotency_key' => $idempotencyKey,
                ]
            );

            // Post to ledger: Debit receivable (-), Credit escrow guarantee (+)
            $this->ledgerService->post(new PostingDTO(
                type: 'EDU_HEADHUNTER_HOLD',
                description: "Headhunter guarantee hold for placement {$placement->placement_code}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit(EduAccounts::HEADHUNTER_RECEIVABLE, $feeMinor)
                    ->credit(EduAccounts::ESCROW_HEADHUNTER_GUARANTEE, $feeMinor)
                    ->toEntries('IDR'),
                referenceType: 'EDU_PLACEMENT',
                referenceId: $placement->placement_code,
            ));

            $this->audit(
                action: 'edu_headhunter_fee_held',
                auditable: $fee,
                newValues: ['placement_code' => $placement->placement_code, 'fee_minor' => $feeMinor],
                impactType: 'headhunter',
            );

            return $fee;
        });
    }

    /**
     * Captures escrow to recognized revenue after 90 days guarantee period.
     */
    public function captureFee(EduHeadhunterFee $fee, Carbon $asOfDate): void
    {
        if ($fee->status !== 'HELD') {
            throw new InvalidArgumentException("Fee [{$fee->id}] cannot be captured: status is [{$fee->status}].");
        }

        $placement = EduPlacement::findOrFail($fee->placement_id);
        $placedAt = Carbon::parse($placement->placed_at)->startOfDay();
        $checkDate = $asOfDate->copy()->startOfDay();
        $daysElapsed = $placedAt->diffInDays($checkDate);

        if ($daysElapsed < $placement->guarantee_days) {
            throw new HeadhunterWarrantyNotExpiredException(
                "Headhunter warranty not expired: {$daysElapsed} days elapsed of required {$placement->guarantee_days} days."
            );
        }

        DB::transaction(function () use ($fee, $placement, $asOfDate) {
            $fee->update([
                'status' => 'CAPTURED',
                'captured_at' => $asOfDate,
            ]);

            $placement->update(['status' => 'CONFIRMED']);

            $idempotencyKey = "edu:hh:cap:{$fee->id}";

            // Post to ledger: Debit escrow guarantee (-), Credit revenue (+)
            $this->ledgerService->post(new PostingDTO(
                type: 'EDU_HEADHUNTER_CAPTURE',
                description: "Headhunter revenue recognized for placement {$placement->placement_code}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit(EduAccounts::ESCROW_HEADHUNTER_GUARANTEE, $fee->fee_minor)
                    ->credit(EduAccounts::HEADHUNTER_REVENUE, $fee->fee_minor)
                    ->toEntries('IDR'),
                referenceType: 'EDU_PLACEMENT',
                referenceId: $placement->placement_code,
            ));

            $this->audit(
                action: 'edu_headhunter_fee_captured',
                auditable: $fee,
                newValues: ['status' => 'CAPTURED', 'fee_minor' => $fee->fee_minor],
                impactType: 'headhunter',
            );
        });
    }

    /**
     * Releases escrow back to hiring client when candidate resigns before guarantee expires.
     */
    public function releaseFee(EduHeadhunterFee $fee, string $reason): void
    {
        if ($fee->status !== 'HELD') {
            throw new InvalidArgumentException("Fee [{$fee->id}] cannot be released: status is [{$fee->status}].");
        }

        $placement = EduPlacement::findOrFail($fee->placement_id);

        DB::transaction(function () use ($fee, $placement, $reason) {
            $fee->update([
                'status' => 'RELEASED',
                'released_at' => $this->clock->{'now'}(),
            ]);

            $placement->update(['status' => 'RELEASED']);

            $idempotencyKey = "edu:hh:rel:{$fee->id}";

            // Post to ledger: Reverse escrow guarantee and receivable
            $this->ledgerService->post(new PostingDTO(
                type: 'EDU_HEADHUNTER_RELEASE',
                description: "Headhunter escrow refund for placement {$placement->placement_code}: {$reason}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit(EduAccounts::ESCROW_HEADHUNTER_GUARANTEE, $fee->fee_minor)
                    ->credit(EduAccounts::HEADHUNTER_RECEIVABLE, $fee->fee_minor)
                    ->toEntries('IDR'),
                referenceType: 'EDU_PLACEMENT',
                referenceId: $placement->placement_code,
            ));

            $this->audit(
                action: 'edu_headhunter_fee_released',
                auditable: $fee,
                newValues: ['status' => 'RELEASED', 'reason' => $reason],
                impactType: 'headhunter',
            );
        });
    }
}
