<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Exceptions\CashbackVelocityExceededException;
use Modules\Ret\Domain\Models\RetCashbackTransaction;
use Modules\Shared\Application\BaseAction;

class EarnSuperAppCashbackAction extends BaseAction
{
    public const DAILY_VELOCITY_LIMIT_MINOR = 500_000;

    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetCashbackTransaction
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required to earn cashback.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = RetCashbackTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $customerId = (string) $params['customer_id'];
        $amountMinor = (int) $params['amount_minor'];
        $sourceDomain = (string) ($params['source_domain'] ?? 'superapp');
        $referenceId = isset($params['reference_id']) ? (string) $params['reference_id'] : null;
        $now = isset($params['current_time']) ? Carbon::parse($params['current_time']) : Carbon::now();
        $expiresAt = isset($params['expires_at']) ? Carbon::parse($params['expires_at']) : $now->copy()->addDays(90);

        // Anti-abuse velocity rule: maximum 500,000 IDR cashback per 24-hour day
        $todayEarned = (int) RetCashbackTransaction::where('customer_id', $customerId)
            ->where('type', 'EARNED')
            ->where('created_at', '>=', $now->copy()->startOfDay())
            ->where('created_at', '<=', $now->copy()->endOfDay())
            ->sum('amount_minor');

        if (($todayEarned + $amountMinor) > self::DAILY_VELOCITY_LIMIT_MINOR) {
            throw new CashbackVelocityExceededException($customerId, $todayEarned + $amountMinor, self::DAILY_VELOCITY_LIMIT_MINOR);
        }

        return DB::transaction(function () use ($customerId, $amountMinor, $sourceDomain, $referenceId, $expiresAt, $idempotencyKey) {
            $this->ensureCashbackAccounts();

            // Post Ledger: Expense Debit -> Liability Credit
            $this->ledgerService->post(new PostingDTO(
                type: 'RETAIL_CASHBACK_ISSUANCE',
                description: "Cashback earned for customer {$customerId}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ret:cashback_promo_expense:IDR', $amountMinor)
                    ->credit('ret:cashback_liability:IDR', $amountMinor)
                    ->toEntries('IDR'),
                referenceType: 'RET_CASHBACK',
                referenceId: $referenceId ?? $idempotencyKey,
            ));

            $cashbackCode = 'CB-'.strtoupper(Str::random(10));

            $tx = RetCashbackTransaction::create([
                'id' => (string) Str::uuid(),
                'cashback_code' => $cashbackCode,
                'customer_id' => $customerId,
                'order_id' => null,
                'reference_id' => $referenceId,
                'source_domain' => $sourceDomain,
                'source_line' => strtoupper($sourceDomain),
                'amount_minor' => $amountMinor,
                'transaction_amount_minor' => $amountMinor * 10,
                'cashback_pct' => 10.0,
                'cashback_earned_minor' => $amountMinor,
                'type' => 'EARNED',
                'status' => 'EARNED',
                'expires_at' => $expiresAt,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit(
                action: 'ret_cashback_earned',
                auditable: $tx,
                newValues: ['amount' => $amountMinor, 'source' => $sourceDomain],
                impactType: 'customer_loyalty',
            );

            return $tx;
        });
    }

    protected function ensureCashbackAccounts(): void
    {
        $accounts = [
            ['code' => 'ret:cashback_liability:IDR', 'name' => 'Super App Cashback Liability', 'kind' => AccountKind::LIABILITY, 'allow_negative' => true],
            ['code' => 'ret:cashback_promo_expense:IDR', 'name' => 'Cashback Promotion Expense', 'kind' => AccountKind::EXPENSE, 'allow_negative' => true],
            ['code' => 'ret:cashback_breakage_revenue:IDR', 'name' => 'Expired Cashback Breakage Revenue', 'kind' => AccountKind::REVENUE, 'allow_negative' => false],
        ];

        foreach ($accounts as $acc) {
            LedgerAccount::firstOrCreate(
                ['code' => $acc['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $acc['name'],
                    'asset_code' => 'IDR',
                    'kind' => $acc['kind']->value,
                    'allow_negative' => $acc['allow_negative'],
                    'cached_balance' => '0',
                    'is_frozen' => false,
                ]
            );
        }
    }
}
