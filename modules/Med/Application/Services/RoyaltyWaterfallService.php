<?php

declare(strict_types=1);

namespace Modules\Med\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Med\Domain\Models\MedIpAdvanceBalance;
use Modules\Med\Domain\Models\MedIpLicense;
use Modules\Med\Domain\Models\MedRoyaltyStatement;
use Modules\Med\Domain\Models\MedRoyaltyStatementLine;
use RuntimeException;

class RoyaltyWaterfallService
{
    public function __construct(
        protected IpRightsRegistryService $rightsService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Record an advance payment to a rightsholder.
     *
     * @param array{
     *     license_id: string,
     *     rightsholder_party_id: string,
     *     advance_amount_minor: int,
     *     idempotency_key?: string
     * } $params
     */
    public function recordAdvance(array $params): MedIpAdvanceBalance
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency key is required to record advance balance.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = MedIpAdvanceBalance::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $advanceAmount = (int) $params['advance_amount_minor'];
        if ($advanceAmount <= 0) {
            throw new InvalidArgumentException('Advance amount must be greater than zero');
        }

        return MedIpAdvanceBalance::create([
            'id' => (string) Str::uuid(),
            'license_id' => $params['license_id'],
            'rightsholder_party_id' => $params['rightsholder_party_id'],
            'initial_advance_minor' => $advanceAmount,
            'recouped_amount_minor' => 0,
            'remaining_balance_minor' => $advanceAmount,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Calculate and settle multi-tier royalty waterfall for a license in a period.
     *
     * @param array{
     *     license_id: string,
     *     billing_period: string,
     *     gross_revenue_minor: int,
     *     distribution_fee_bps?: int,
     *     recoupment_minor?: int,
     *     idempotency_key?: string
     * } $params
     */
    public function settleRoyalty(array $params): MedRoyaltyStatement
    {
        $gross = (int) $params['gross_revenue_minor'];
        if ($gross < 0) {
            throw new InvalidArgumentException('Gross revenue cannot be negative');
        }

        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency key is required to settle royalty waterfall.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];
        $existing = MedRoyaltyStatement::where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $license = MedIpLicense::find($params['license_id']);
        if ($license === null) {
            throw new RuntimeException("License {$params['license_id']} not found");
        }

        return DB::transaction(function () use ($params, $license, $gross, $idempotencyKey): MedRoyaltyStatement {
            $feeBps = (int) ($params['distribution_fee_bps'] ?? 0);
            $distributionFee = (int) intdiv($gross * $feeBps, 10000);
            $netDistributable = $gross - $distributionFee;

            $periodStart = CarbonImmutable::parse($params['billing_period'].'-01');
            $activeShares = $this->rightsService->getActiveSharesAt($license->ip_id, $license->rights_type, $periodStart);

            $statementNumber = 'STMT-'.$license->license_code.'-'.$params['billing_period'].'-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 4));

            $statement = MedRoyaltyStatement::create([
                'id' => (string) Str::uuid(),
                'statement_number' => $statementNumber,
                'license_id' => $license->id,
                'billing_period' => $params['billing_period'],
                'gross_revenue_minor' => $gross,
                'total_distribution_fee_minor' => $distributionFee,
                'total_recouped_minor' => 0,
                'total_royalty_distributed_minor' => 0,
                'status' => 'ISSUED',
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($gross === 0 || empty($activeShares)) {
                $statement->update([
                    'total_recouped_minor' => 0,
                    'total_royalty_distributed_minor' => 0,
                ]);

                return $statement;
            }

            // Calculate share lines with integer division
            $totalAssignedGrossShares = 0;
            $shareCalculations = [];

            foreach ($activeShares as $partyId => $shareBps) {
                $grossShare = (int) intdiv($netDistributable * (int) $shareBps, 10000);
                $totalAssignedGrossShares += $grossShare;

                $shareCalculations[] = [
                    'party_id' => $partyId,
                    'share_bps' => (int) $shareBps,
                    'gross_share' => $grossShare,
                ];
            }

            // Penny Absorption: add remainder to the last line
            $remainder = $netDistributable - $totalAssignedGrossShares;
            $count = count($shareCalculations);
            if ($remainder > 0 && $count > 0) {
                $shareCalculations[$count - 1]['gross_share'] += $remainder;
            }

            $totalRecouped = 0;
            $totalNetPayable = 0;

            foreach ($shareCalculations as $calc) {
                $partyId = $calc['party_id'];
                $grossShare = $calc['gross_share'];

                // Deduct from advances if available
                $recoupedForParty = 0;
                $advances = MedIpAdvanceBalance::where('license_id', $license->id)
                    ->where('rightsholder_party_id', $partyId)
                    ->where('remaining_balance_minor', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                foreach ($advances as $advance) {
                    $needRecoup = $grossShare - $recoupedForParty;
                    if ($needRecoup <= 0) {
                        break;
                    }

                    $recoupAmount = min($needRecoup, (int) $advance->remaining_balance_minor);
                    $advance->recouped_amount_minor += $recoupAmount;
                    $advance->remaining_balance_minor -= $recoupAmount;
                    $advance->save();

                    $recoupedForParty += $recoupAmount;
                }

                $netPayable = $grossShare - $recoupedForParty;
                $totalRecouped += $recoupedForParty;
                $totalNetPayable += $netPayable;

                MedRoyaltyStatementLine::create([
                    'id' => (string) Str::uuid(),
                    'statement_id' => $statement->id,
                    'rightsholder_party_id' => $partyId,
                    'rights_type' => $license->rights_type,
                    'share_bps' => $calc['share_bps'],
                    'gross_share_minor' => $grossShare,
                    'recoupment_deducted_minor' => $recoupedForParty,
                    'net_payable_minor' => $netPayable,
                ]);
            }

            // Total royalty distributed is net distributable minus recouped (which equals totalNetPayable)
            $statement->update([
                'total_recouped_minor' => $totalRecouped,
                'total_royalty_distributed_minor' => $totalNetPayable,
            ]);

            // Post balanced double-entry ledger entries if totalNetPayable > 0
            if ($totalNetPayable > 0) {
                $expenseAccount = 'med:talent_royalty_expense:IDR';
                $payableAccount = 'med:talent_royalty_payable:IDR';

                $this->ledgerService->post(new PostingDTO(
                    type: 'MEDIA_TALENT_ROYALTY_ACCRUAL',
                    description: "Royalty settlement {$statement->statement_number}",
                    idempotencyKey: "med:royalty:{$statement->statement_number}",
                    entries: Posting::lines()
                        ->debit($expenseAccount, $totalNetPayable)
                        ->credit($payableAccount, $totalNetPayable)
                        ->toEntries('IDR'),
                    referenceType: 'MED_ROYALTY_STATEMENT',
                    referenceId: $statement->statement_number,
                ));
            }

            return $statement;
        });
    }
}
