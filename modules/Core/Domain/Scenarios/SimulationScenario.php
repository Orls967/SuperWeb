<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Scenarios;

use InvalidArgumentException;

/**
 * Representasi skenario simulasi bisnis (misal: "Lonjakan Pasien Pasca Bencana", "Peak Season Lebaran", "Gagal Bayar Cicilan Masal").
 */
final readonly class SimulationScenario
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<int, string>  $activeModules
     */
    public function __construct(
        public string $code,
        public string $title,
        public string $description,
        public int $durationDays,
        public int $defaultSeed,
        public array $parameters = [],
        public array $activeModules = ['hospital', 'hotel', 'logistics', 'finance'],
    ) {
        if (trim($this->code) === '') {
            throw new InvalidArgumentException('Kode skenario tidak boleh kosong.');
        }
        if ($this->durationDays <= 0) {
            throw new InvalidArgumentException("Durasi hari harus positif: {$this->durationDays}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'duration_days' => $this->durationDays,
            'default_seed' => $this->defaultSeed,
            'parameters' => $this->parameters,
            'active_modules' => $this->activeModules,
        ];
    }
}
