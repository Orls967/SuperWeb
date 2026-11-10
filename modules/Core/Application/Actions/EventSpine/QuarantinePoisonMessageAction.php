<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\EventSpine;

use Modules\Core\Application\Services\PoisonMessageQuarantineService;
use Modules\Core\Domain\Models\CorePoisonMessage;
use Throwable;

class QuarantinePoisonMessageAction
{
    public function __construct(
        protected PoisonMessageQuarantineService $quarantineService
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        string $messageId,
        string $consumerGroup,
        string $topic,
        ?string $eventName,
        array $payload,
        string $failureReason,
        ?Throwable $exception = null,
        int $attempts = 3
    ): CorePoisonMessage {
        return $this->quarantineService->quarantine(
            $messageId,
            $consumerGroup,
            $topic,
            $eventName,
            $payload,
            $failureReason,
            $exception,
            $attempts
        );
    }
}
