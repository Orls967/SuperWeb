<?php

declare(strict_types=1);

namespace Modules\Ret\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Ret\Domain\Models\RetCashbackTransaction;

class RetExpireCashbackCommand extends Command
{
    protected $signature = 'ret:expire-cashback {--at= : Virtual evaluation timestamp}';

    protected $description = 'Proses kedaluwarsa saldo cashback super app dan pengakuan pendapatan breakage';

    public function handle(LedgerService $ledgerService): int
    {
        $this->info('Starting expired cashback breakage process...');

        $now = $this->option('at') ? Carbon::parse((string) $this->option('at')) : Carbon::now();
        $expiredTranches = RetCashbackTransaction::where('type', 'EARNED')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->get();

        $processedCount = 0;
        foreach ($expiredTranches as $tranche) {
            $alreadyExpired = RetCashbackTransaction::where('type', 'EXPIRED')
                ->where('reference_id', $tranche->id)
                ->exists();

            if ($alreadyExpired) {
                continue;
            }

            $amountMinor = (int) $tranche->amount_minor;
            if ($amountMinor <= 0) {
                continue;
            }

            $idempotencyKey = "ret:cb:exp:{$tranche->id}";

            // Post Ledger: Liability Debit -> Breakage Revenue Credit
            $ledgerService->post(new PostingDTO(
                type: 'RETAIL_CASHBACK_BREAKAGE',
                description: "Cashback breakage for expired tranche {$tranche->id}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ret:cashback_liability:IDR', $amountMinor)
                    ->credit('ret:cashback_breakage_revenue:IDR', $amountMinor)
                    ->toEntries('IDR'),
                referenceType: 'RET_CASHBACK_BREAKAGE',
                referenceId: (string) $tranche->id,
            ));

            $cashbackCode = 'CB-EXP-'.strtoupper(Str::random(8));

            RetCashbackTransaction::create([
                'id' => (string) Str::uuid(),
                'cashback_code' => $cashbackCode,
                'customer_id' => $tranche->customer_id,
                'order_id' => null,
                'reference_id' => $tranche->id,
                'source_domain' => 'superapp',
                'source_line' => 'BREAKAGE',
                'amount_minor' => $amountMinor,
                'transaction_amount_minor' => $amountMinor,
                'cashback_pct' => 0.0,
                'cashback_earned_minor' => 0,
                'type' => 'EXPIRED',
                'status' => 'EXPIRED',
                'expires_at' => null,
                'idempotency_key' => $idempotencyKey,
            ]);

            $processedCount++;
        }

        $this->info("Completed cashback breakage: {$processedCount} tranches expired.");

        return self::SUCCESS;
    }
}
