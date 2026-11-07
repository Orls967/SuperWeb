<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\DTOs;

final class CorporateRateAgreementDTO
{
    public function __construct(
        public readonly string $corporateAccountId,
        public readonly string $companyName,
        public readonly int $publishedRateIdr,
        public readonly int $negotiatedRateIdr,
        public readonly int $savingsIdr,
        public readonly bool $isBlackoutEnforced,
        public readonly string $rateTier
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'corporate_account_id' => $this->corporateAccountId,
            'company_name' => $this->companyName,
            'published_rate_idr' => $this->publishedRateIdr,
            'negotiated_rate_idr' => $this->negotiatedRateIdr,
            'savings_idr' => $this->savingsIdr,
            'is_blackout_enforced' => $this->isBlackoutEnforced,
            'rate_tier' => $this->rateTier,
        ];
    }
}
