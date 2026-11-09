<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class FolioItemDTO
{
    public function __construct(
        public readonly string $category,
        public readonly string $description,
        public readonly int $quantity,
        public readonly int $unitPriceIdr,
        public readonly int $subtotalIdr,
        public readonly ?string $referenceCode = null,
        public readonly bool $isCoveredByBpjs = true,
        public readonly bool $isCoveredByPrivateInsurance = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $qty = max(1, (int) ($data['quantity'] ?? 1));
        $unitPrice = max(0, (int) ($data['unit_price_idr'] ?? 0));
        $subtotal = $data['subtotal_idr'] ?? ($qty * $unitPrice);

        return new self(
            category: (string) ($data['category'] ?? $data['item_category'] ?? 'PROCEDURE'),
            description: (string) ($data['description'] ?? 'Item Pelayanan'),
            quantity: $qty,
            unitPriceIdr: $unitPrice,
            subtotalIdr: (int) $subtotal,
            referenceCode: isset($data['reference_code']) ? (string) $data['reference_code'] : null,
            isCoveredByBpjs: (bool) ($data['is_covered_by_bpjs'] ?? true),
            isCoveredByPrivateInsurance: (bool) ($data['is_covered_by_private_insurance'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'item_category' => $this->category,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price_idr' => $this->unitPriceIdr,
            'subtotal_idr' => $this->subtotalIdr,
            'reference_code' => $this->referenceCode,
            'is_covered_by_bpjs' => $this->isCoveredByBpjs,
            'is_covered_by_private_insurance' => $this->isCoveredByPrivateInsurance,
        ];
    }
}
