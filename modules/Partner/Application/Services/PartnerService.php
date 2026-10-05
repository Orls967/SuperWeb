<?php

declare(strict_types=1);

namespace Modules\Partner\Application\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Partner\Domain\Models\CosellListing;
use Modules\Partner\Domain\Models\DueDiligence;
use Modules\Partner\Domain\Models\ExitTransition;
use Modules\Partner\Domain\Models\IntellectualProperty;
use Modules\Partner\Domain\Models\JointPlan;
use Modules\Partner\Domain\Models\Partner;
use Modules\Partner\Domain\Models\PartnerScorecard;
use Modules\Partner\Domain\Models\RevenueShare;

class PartnerService
{
    public const ACCT_REV_SHARE_EXPENSE = 'ptn:rev_share_expense:IDR';

    public const ACCT_REV_SHARE_PAYABLE = 'ptn:rev_share_payable:IDR';

    public const ACCT_CLEARING = 'clearing:external:IDR';

    public function __construct(
        private readonly ApprovalEngineInterface $approvals,
        private readonly Ledger $ledger,
    ) {}

    // ── 47.1 Partner Lifecycle ──────────────────────────────────────────

    /**
     * @param  array{code:string,name:string,kind:string,party_id?:string,
     *   owner_user_id?:int,notes?:string}  $data
     */
    public function registerPartner(array $data): Partner
    {
        $code = strtoupper($data['code']);
        if (Partner::where('code', $code)->exists()) {
            throw new InvalidArgumentException("Kode mitra {$code} sudah dipakai.");
        }

        if (! in_array($data['kind'], Partner::KINDS, true)) {
            throw new InvalidArgumentException("Jenis mitra {$data['kind']} tidak dikenal.");
        }

        return Partner::create([
            'code' => $code,
            'name' => $data['name'],
            'kind' => $data['kind'],
            'party_id' => $data['party_id'] ?? null,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'status' => 'prospect',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function transition(Partner $partner, string $to): Partner
    {
        if (! in_array($to, Partner::STATUSES, true)) {
            throw new InvalidArgumentException("Status {$to} tidak dikenal.");
        }

        return DB::transaction(function () use ($partner, $to) {
            /** @var Partner $locked */
            $locked = Partner::query()->lockForUpdate()->findOrFail($partner->getKey());
            if ($locked->status === $to) {
                return $locked;
            }

            $allowed = [
                'prospect' => ['due_diligence', 'exit'],
                'due_diligence' => ['negotiation', 'exit'],
                'negotiation' => ['active', 'exit'],
                'active' => ['review', 'exit'],
                'review' => ['active', 'exit'],
                'exit' => [],
            ];

            if (! in_array($to, $allowed[$locked->status] ?? [], true)) {
                throw new InvalidArgumentException("Transisi {$locked->status} → {$to} tidak sah.");
            }

            $locked->update(['status' => $to]);

            return $locked;
        });
    }

    // ── 47.2 Due Diligence ──────────────────────────────────────────────

    /**
     * @param  array{score:int,checklist?:array,notes?:string}  $data
     */
    public function submitDueDiligence(Partner $partner, array $data): DueDiligence
    {
        $score = (int) $data['score'];
        $status = $score >= 70 ? 'approved' : 'rejected';

        return DueDiligence::create([
            'partner_id' => $partner->id,
            'score' => $score,
            'checklist' => $data['checklist'] ?? [
                'legal' => true,
                'financial' => $score >= 60,
                'reputation' => true,
                'esg' => true,
                'sanction' => true,
            ],
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    // ── 47.3 Joint Business Plan ────────────────────────────────────────

    /**
     * @param  array{title:string,period:string,budget_idr?:int,target_revenue_idr?:int,
     *   internal_pic?:string,partner_pic?:string,kpi_targets?:array}  $data
     */
    public function createJointPlan(Partner $partner, array $data): JointPlan
    {
        return JointPlan::create([
            'partner_id' => $partner->id,
            'title' => $data['title'],
            'period' => $data['period'],
            'budget_idr' => (int) ($data['budget_idr'] ?? 0),
            'target_revenue_idr' => (int) ($data['target_revenue_idr'] ?? 0),
            'internal_pic' => $data['internal_pic'] ?? null,
            'partner_pic' => $data['partner_pic'] ?? null,
            'kpi_targets' => $data['kpi_targets'] ?? [],
            'status' => 'active',
        ]);
    }

    // ── 47.4 Revenue / Profit Sharing ───────────────────────────────────

    public function computeRevenueShare(Partner $partner, string $period, int $grossRevenue, int $deductibleCost, float $shareRatePercent): RevenueShare
    {
        $netBase = max(0, $grossRevenue - $deductibleCost);
        $shareAmount = (int) floor($netBase * ($shareRatePercent / 100));

        return DB::transaction(function () use ($partner, $period, $grossRevenue, $deductibleCost, $netBase, $shareRatePercent, $shareAmount) {
            $share = RevenueShare::updateOrCreate(
                ['partner_id' => $partner->id, 'period' => $period],
                [
                    'gross_revenue_idr' => $grossRevenue,
                    'deductible_cost_idr' => $deductibleCost,
                    'net_base_idr' => $netBase,
                    'share_rate_percent' => $shareRatePercent,
                    'share_amount_idr' => $shareAmount,
                    'status' => 'draft',
                ]
            );

            return $share;
        });
    }

    public function payRevenueShare(RevenueShare $share): RevenueShare
    {
        return DB::transaction(function () use ($share) {
            /** @var RevenueShare $locked */
            $locked = RevenueShare::query()->lockForUpdate()->findOrFail($share->getKey());
            if ($locked->status === 'paid') {
                return $locked;
            }

            $this->ensureAccounts();

            $amount = (int) $locked->share_amount_idr;
            if ($amount > 0) {
                $tx = $this->ledger->post(new PostingDTO(
                    type: TransactionType::MANUAL_ADJUSTMENT->value,
                    description: "Bagi hasil kemitraan {$locked->partner?->code} periode {$locked->period}",
                    idempotencyKey: 'ptn:rev_share:'.$locked->id,
                    entries: [
                        PostingEntryDTO::forCode(self::ACCT_REV_SHARE_EXPENSE, 'IDR', BigDecimal::of($amount)),
                        PostingEntryDTO::forCode(self::ACCT_CLEARING, 'IDR', BigDecimal::of($amount)->negated()),
                    ],
                    referenceType: RevenueShare::class,
                    referenceId: $locked->id,
                    meta: ['partner_id' => $locked->partner_id, 'period' => $locked->period],
                    postedAt: now(),
                ));

                $locked->ledger_transaction_id = $tx->id;
            }

            $locked->status = 'paid';
            $locked->save();

            return $locked;
        });
    }

    // ── 47.5 Co-selling ─────────────────────────────────────────────────

    public function createCosellListing(Partner $partner, array $data): CosellListing
    {
        return CosellListing::create([
            'partner_id' => $partner->id,
            'title' => $data['title'],
            'category' => $data['category'],
            'price_idr' => (int) ($data['price_idr'] ?? 0),
            'referral_fee_percent' => (float) ($data['referral_fee_percent'] ?? 0),
            'status' => 'active',
        ]);
    }

    // ── 47.7 SLA Scorecard ──────────────────────────────────────────────

    public function recordScorecard(Partner $partner, string $period, int $score, float $slaCompliance, int $penalties = 0, ?string $remediation = null): PartnerScorecard
    {
        return PartnerScorecard::updateOrCreate(
            ['partner_id' => $partner->id, 'period' => $period],
            [
                'score' => $score,
                'sla_compliance_percent' => $slaCompliance,
                'penalties_idr' => $penalties,
                'remediation_plan' => $remediation,
            ]
        );
    }

    // ── 47.8 HKI & Aset Bersama ─────────────────────────────────────────

    public function registerIntellectualProperty(Partner $partner, array $data): IntellectualProperty
    {
        return IntellectualProperty::create([
            'partner_id' => $partner->id,
            'type' => $data['type'],
            'registration_number' => $data['registration_number'],
            'name' => $data['name'],
            'registered_at' => $data['registered_at'],
            'expires_at' => $data['expires_at'] ?? null,
            'status' => 'valid',
        ]);
    }

    // ── 47.9 Exit Transition ────────────────────────────────────────────

    public function executeExit(Partner $partner, string $reason, int $finalSettlement = 0, ?string $splitSummary = null): ExitTransition
    {
        return DB::transaction(function () use ($partner, $reason, $finalSettlement, $splitSummary) {
            $partner->status = 'exit';
            $partner->save();

            return ExitTransition::create([
                'partner_id' => $partner->id,
                'reason' => $reason,
                'final_settlement_idr' => $finalSettlement,
                'asset_split_summary' => $splitSummary,
                'exit_date' => now()->toDateString(),
                'status' => 'completed',
            ]);
        });
    }

    public function ensureAccounts(): void
    {
        foreach ([
            [self::ACCT_REV_SHARE_EXPENSE, 'Beban Bagi Hasil Mitra', AccountKind::EXPENSE],
            [self::ACCT_REV_SHARE_PAYABLE, 'Hutang Bagi Hasil Mitra', AccountKind::LIABILITY],
            [self::ACCT_CLEARING, 'Rekening Kliring Eksternal IDR', AccountKind::CLEARING],
        ] as [$code, $name, $kind]) {
            LedgerAccount::firstOrCreate(
                ['code' => $code, 'asset_code' => 'IDR'],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name,
                    'kind' => $kind->value,
                    'allow_negative' => true,
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }
    }
}
