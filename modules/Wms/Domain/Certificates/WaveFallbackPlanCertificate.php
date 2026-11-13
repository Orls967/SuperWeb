<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

/**
 * Sertifikat rencana dispatch wave & kesiapan fallback manual oleh J3 WaveAutomationDispatcher.
 */
final readonly class WaveFallbackPlanCertificate
{
    public function __construct(
        public string $waveCode,
        public string $mode,
        public int $taskCount,
        public int $robotCount,
        public string $hash,
    ) {}

    public function isValid(): bool
    {
        if ($this->waveCode === '' || $this->taskCount < 0 || $this->robotCount < 0) {
            return false;
        }

        if (! in_array($this->mode, ['automated', 'manual_fallback', 'hybrid'], true)) {
            return false;
        }

        return $this->hash === self::computeHash($this->waveCode, $this->mode, $this->taskCount, $this->robotCount);
    }

    public static function computeHash(string $waveCode, string $mode, int $taskCount, int $robotCount): string
    {
        return hash('sha256', implode('|', [$waveCode, $mode, (string) $taskCount, (string) $robotCount]));
    }
}

