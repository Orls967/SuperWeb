<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTOs\Resilience;

final readonly class DataSovereigntyCheckDto
{
    public function __construct(
        public string $jurisdictionCode,
        public string $dataType,
        public string $storageRegion,
        public bool $containsPii = true,
        public bool $isCrossBorderTransfer = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            jurisdictionCode: (string) ($data['jurisdiction_code'] ?? 'ID'),
            dataType: (string) ($data['data_type'] ?? 'FINANCIAL_TRANSACTION'),
            storageRegion: (string) ($data['storage_region'] ?? 'ap-southeast-3'),
            containsPii: (bool) ($data['contains_pii'] ?? true),
            isCrossBorderTransfer: (bool) ($data['is_cross_border_transfer'] ?? false),
        );
    }
}
