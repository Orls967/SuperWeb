<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms\Certificates;

use JsonSerializable;

final class IntercompanyAmortizationCertificate implements JsonSerializable
{
    /**
     * @param  array<int, array<string, mixed>>  $scheduleDetails
     */
    public function __construct(
        public readonly int $totalPrincipalMinor,
        public readonly int $baseRateBps,
        public readonly int $spreadBps,
        public readonly int $withholdingTaxBps,
        public readonly int $periods,
        public readonly int $totalInterestGrossMinor,
        public readonly int $totalTaxWithheldMinor,
        public readonly int $totalInterestNetMinor,
        public readonly array $scheduleDetails,
        public string $sha256 = ''
    ) {
        if ($this->sha256 === '') {
            $this->sha256 = $this->computeHash();
        }
    }

    public function computeHash(): string
    {
        $payload = [
            'base_rate_bps' => $this->baseRateBps,
            'periods' => $this->periods,
            'spread_bps' => $this->spreadBps,
            'total_interest_gross_minor' => $this->totalInterestGrossMinor,
            'total_interest_net_minor' => $this->totalInterestNetMinor,
            'total_principal_minor' => $this->totalPrincipalMinor,
            'total_tax_withheld_minor' => $this->totalTaxWithheldMinor,
            'withholding_tax_bps' => $this->withholdingTaxBps,
        ];
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function jsonSerialize(): array
    {
        return [
            'total_principal_minor' => $this->totalPrincipalMinor,
            'base_rate_bps' => $this->baseRateBps,
            'spread_bps' => $this->spreadBps,
            'withholding_tax_bps' => $this->withholdingTaxBps,
            'periods' => $this->periods,
            'total_interest_gross_minor' => $this->totalInterestGrossMinor,
            'total_tax_withheld_minor' => $this->totalTaxWithheldMinor,
            'total_interest_net_minor' => $this->totalInterestNetMinor,
            'schedule_details' => $this->scheduleDetails,
            'sha256' => $this->sha256,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
