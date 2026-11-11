<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Canonical parsed representation of a simulated UN/EDIFACT or ASC X12 interchange.
 */
class CanonicalEdiDocument
{
    /**
     * @param  list<array{tag: string, elements: list<list<string>>, raw: string}>  $segments
     * @param  list<array{line_number: string, sku: string, quantity: int, unit_price: int}>  $lineItems
     * @param  list<string>  $errorCodes
     * @param  array<string, string>  $delimiters
     * @param  array<string, mixed>  $certificate
     */
    public function __construct(
        public readonly string $standard,
        public readonly string $messageType,
        public readonly string $interchangeControlRef,
        public readonly string $messageControlRef,
        public readonly string $senderId,
        public readonly string $receiverId,
        public readonly string $documentTypeCode,
        public readonly string $documentNumber,
        public readonly string $buyerIdentifier,
        public readonly int $segmentCount,
        public readonly int $messageSegmentCount,
        public readonly bool $isValid,
        public readonly string $acknowledgmentAction,
        public readonly string $acknowledgmentPayload,
        public readonly array $segments,
        public readonly array $lineItems,
        public readonly array $errorCodes,
        public readonly array $delimiters,
        public array $certificate,
        public readonly string $rawPayload,
    ) {}
}

