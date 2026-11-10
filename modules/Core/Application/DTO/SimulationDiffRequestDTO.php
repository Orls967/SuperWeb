<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTO;

use Illuminate\Http\Request;

final readonly class SimulationDiffRequestDTO
{
    public function __construct(
        public string $runA,
        public string $runB,
    ) {}

    public static function fromRequest(Request $request): ?self
    {
        $a = (string) $request->query('a');
        $b = (string) $request->query('b');

        if ($a === '' || $b === '') {
            return null;
        }

        return new self($a, $b);
    }

    public function isValid(): bool
    {
        return $this->runA !== '' && $this->runB !== '';
    }
}
