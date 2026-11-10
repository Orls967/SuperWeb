<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Algorithms\Certificates;

use JsonSerializable;
use Modules\Treasury\Domain\Exceptions\InvalidCertificateException;

final class FxRevaluationCertificate implements JsonSerializable
{
    public string $certificateHash;

    public function __construct(
        public array $canonicalInputs,
        public array $steps,
        public array $results,
        public string $sha256
    ) {
        $this->certificateHash = $sha256;
    }

    public static function create(array $canonicalInputs, array $steps, array $results): self
    {
        ksort($canonicalInputs);
        ksort($results);

        $payload = json_encode([
            'inputs' => $canonicalInputs,
            'steps' => $steps,
            'results' => $results,
        ], JSON_THROW_ON_ERROR);

        $hash = hash('sha256', $payload);

        return new self($canonicalInputs, $steps, $results, $hash);
    }

    public function jsonSerialize(): array
    {
        return [
            'inputs' => $this->canonicalInputs,
            'steps' => $this->steps,
            'results' => $this->results,
            'sha256' => $this->sha256,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
