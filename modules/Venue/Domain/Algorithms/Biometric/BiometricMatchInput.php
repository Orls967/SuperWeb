<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Biometric;

use InvalidArgumentException;

/**
 * Nilai Masukan Algoritma V13 BiometricLivenessMatcherEngine.
 */
final readonly class BiometricMatchInput
{
    public function __construct(
        public int $userId,
        public string $featureVector,
        public string $challengeProof,
        public string $saltSecret,
        public int $minLivenessThresholdBps = 8500
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('User ID harus bernilai integer positif.');
        }
        if (trim($this->featureVector) === '') {
            throw new InvalidArgumentException('Feature vector biometrik tidak boleh kosong.');
        }
        if ($this->minLivenessThresholdBps < 0 || $this->minLivenessThresholdBps > 10000) {
            throw new InvalidArgumentException('Min liveness threshold bps harus berada di rentang 0 hingga 10000.');
        }
    }
}
