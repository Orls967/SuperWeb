<?php

declare(strict_types=1);

namespace Modules\Payment\Console\Commands;

use Illuminate\Console\Command;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Payment\Domain\Models\PaymentIntent;

class ReleaseExpiredHoldsCommand extends Command
{
    protected $signature = 'payment:release-expired-holds';

    protected $description = 'Otomatis melepaskan dana escrow untuk payment intent status held yang telah melewati masa berlaku';

    public function handle(PaymentGateway $gateway): int
    {
        $expiredIntents = PaymentIntent::where('status', PaymentIntentStatus::HELD->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $this->info("Menemukan {$expiredIntents->count()} payment intent held yang kadaluarsa.");

        foreach ($expiredIntents as $intent) {
            try {
                $gateway->release($intent);
                $this->info("✓ Intent #{$intent->id} berhasil dilepaskan kembali ke payer.");
            } catch (\Throwable $e) {
                $this->error("✗ Gagal melepaskan intent #{$intent->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
