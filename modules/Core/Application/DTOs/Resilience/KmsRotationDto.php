<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTOs\Resilience;

final readonly class KmsRotationDto
{
    public function __construct(
        public string $keyAlias,
        public string $encryptionAlgorithm = 'AES-256-GCM',
        public ?string $rotationReason = null,
        public ?int $userId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            keyAlias: (string) ($data['key_alias'] ?? 'master-database-envelope-key'),
            encryptionAlgorithm: (string) ($data['encryption_algorithm'] ?? 'AES-256-GCM'),
            rotationReason: isset($data['rotation_reason']) ? (string) $data['rotation_reason'] : null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
        );
    }
}
