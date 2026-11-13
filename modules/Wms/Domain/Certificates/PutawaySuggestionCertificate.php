<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

/**
 * Sertifikat deterministik hasil algoritma PutawaySlotter (H1).
 */
final readonly class PutawaySuggestionCertificate
{
    /**
     * @param  list<array{bin_id: string, qty: int}>  $allocations
     * @param  list<array{id: string, distance: int, freeCapacity: int}>  $inputBins
     */
    public function __construct(
        public int $requestedQty,
        public string $abcClass,
        public string $mode,
        public array $allocations,
        public array $inputBins,
        public string $signatureHash,
    ) {}

    public static function create(
        int $requestedQty,
        string $abcClass,
        string $mode,
        array $allocations,
        array $inputBins,
    ): self {
        $payload = json_encode([
            'q' => $requestedQty,
            'abc' => $abcClass,
            'mode' => $mode,
            'allocations' => $allocations,
        ], JSON_THROW_ON_ERROR);

        return new self(
            requestedQty: $requestedQty,
            abcClass: $abcClass,
            mode: $mode,
            allocations: $allocations,
            inputBins: $inputBins,
            signatureHash: hash('sha256', $payload),
        );
    }
}
