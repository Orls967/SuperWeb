<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use DomainException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Domain\Enums\BordereauStatus;
use Modules\Insurance\Domain\Models\ReinsuranceBordereau;

final class SendBordereauAction
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function execute(ReinsuranceBordereau $bordereau): ReinsuranceBordereau
    {
        $bordereau->status->assertCanTransitionTo(BordereauStatus::Sent);

        if ($bordereau->line_count === 0) {
            throw new DomainException('Bordereau kosong tidak dapat dikirim.');
        }

        $bordereau->forceFill([
            'status' => BordereauStatus::Sent,
            'sent_at' => $this->clock->now(),
        ])->save();

        return $bordereau;
    }
}
