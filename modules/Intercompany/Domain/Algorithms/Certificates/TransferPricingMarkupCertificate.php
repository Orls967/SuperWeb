<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms\Certificates;

use JsonSerializable;

final class TransferPricingMarkupCertificate implements JsonSerializable
{
    public function __construct(
        public readonly string $method,
        public readonly int $costMinor,
        public readonly int $actualPriceMinor,
        public readonly int $actualMarginBps,
        public readonly int $q1Bps,
        public readonly int $medianBps,
        public readonly int $q3Bps,
        public readonly string $status,
        public readonly int $adjustmentMinor,
        public readonly int $benchmarkCount,
        public string $sha256 = ''
    ) {
        if ($this->sha256 === '') {
            $this->sha256 = $this->computeHash();
        }
    }

    public function computeHash(): string
    {
        $payload = [
            'actual_margin_bps' => $this->actualMarginBps,
            'actual_price_minor' => $this->actualPriceMinor,
            'adjustment_minor' => $this->adjustmentMinor,
            'benchmark_count' => $this->benchmarkCount,
            'cost_minor' => $this->costMinor,
            'median_bps' => $this->medianBps,
            'method' => $this->method,
            'q1_bps' => $this->q1Bps,
            'q3_bps' => $this->q3Bps,
            'status' => $this->status,
        ];
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function jsonSerialize(): array
    {
        return [
            'method' => $this->method,
            'cost_minor' => $this->costMinor,
            'actual_price_minor' => $this->actualPriceMinor,
            'actual_margin_bps' => $this->actualMarginBps,
            'q1_bps' => $this->q1Bps,
            'median_bps' => $this->medianBps,
            'q3_bps' => $this->q3Bps,
            'status' => $this->status,
            'adjustment_minor' => $this->adjustmentMinor,
            'benchmark_count' => $this->benchmarkCount,
            'sha256' => $this->sha256,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
