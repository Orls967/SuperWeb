<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class FHIRBundlePayloadDTO
{
    /**
     * @param  list<array<string, mixed>>  $resources
     */
    public function __construct(
        public readonly string $bundleType, // transaction, batch, collection, document
        public readonly string $bundleIdentifier,
        public readonly string $timestamp,
        public readonly int $totalResources,
        public readonly array $resources = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toFHIRJson(): array
    {
        $entries = [];
        foreach ($this->resources as $res) {
            $entries[] = [
                'fullUrl' => 'urn:uuid:'.($res['id'] ?? uniqid()),
                'resource' => $res,
                'request' => [
                    'method' => 'POST',
                    'url' => (string) ($res['resourceType'] ?? 'Observation'),
                ],
            ];
        }

        return [
            'resourceType' => 'Bundle',
            'id' => $this->bundleIdentifier,
            'type' => $this->bundleType,
            'timestamp' => $this->timestamp,
            'total' => count($entries),
            'entry' => $entries,
        ];
    }
}
