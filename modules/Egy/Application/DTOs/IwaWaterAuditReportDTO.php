<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * Data Transfer Object untuk Ringkasan Audit Neraca Air IWA (International Water Association).
 */
final class IwaWaterAuditReportDTO
{
    public function __construct(
        public readonly int $networkId,
        public readonly string $networkName,
        public readonly string $zoneCode,
        public readonly int $systemInputVolumeL,
        public readonly int $billedAuthorizedL,
        public readonly int $unbilledAuthorizedL,
        public readonly int $apparentLossesL,
        public readonly int $realLossesL,
        public readonly int $nonRevenueWaterL,
        public readonly int $nrwPercentageBps,
        public readonly string $status,
        public readonly string $verificationHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'network_id' => $this->networkId,
            'network_name' => $this->networkName,
            'zone_code' => $this->zoneCode,
            'system_input_volume_l' => $this->systemInputVolumeL,
            'billed_authorized_l' => $this->billedAuthorizedL,
            'unbilled_authorized_l' => $this->unbilledAuthorizedL,
            'apparent_losses_l' => $this->apparentLossesL,
            'real_losses_l' => $this->realLossesL,
            'non_revenue_water_l' => $this->nonRevenueWaterL,
            'nrw_percentage_bps' => $this->nrwPercentageBps,
            'status' => $this->status,
            'verification_hash' => $this->verificationHash,
        ];
    }
}
