<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Certificates;

final class ConsolidatedTrialBalanceCertificate
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly string $sha256Signature
    ) {}

    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'signature' => $this->sha256Signature,
        ];
    }
}
