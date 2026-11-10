<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Contracts\SimStreamRegistry;
use Modules\Core\Domain\Algorithms\Simulation\SimulationStream;
use Modules\Core\Domain\Algorithms\Simulation\StreamSeedAndFingerprint;

class SimStreamRegistryService implements SimStreamRegistry
{
    protected int $activeSeed = 42;

    /**
     * @var array<string, SimulationStream>
     */
    protected array $streams = [];

    public function __construct(
        protected StreamSeedAndFingerprint $algo = new StreamSeedAndFingerprint,
    ) {}

    public function setActiveSeed(int $seed): void
    {
        $this->activeSeed = $seed;
        $this->streams = [];
    }

    public function getActiveSeed(): int
    {
        return $this->activeSeed;
    }

    public function seedFor(string $streamName): int
    {
        return $this->algo->split($this->activeSeed, $streamName);
    }

    public function getStream(string $streamName): SimulationStream
    {
        if (! isset($this->streams[$streamName])) {
            $streamSeed = $this->seedFor($streamName);
            $this->streams[$streamName] = $this->algo->createStream($streamSeed);
        }

        return $this->streams[$streamName];
    }
}
