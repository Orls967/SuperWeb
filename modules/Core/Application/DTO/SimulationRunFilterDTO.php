<?php

declare(strict_types=1);

namespace Modules\Core\Application\DTO;

use Illuminate\Http\Request;

final readonly class SimulationRunFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?string $mode = null,
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->filled('search') ? (string) $request->query('search') : null,
            status: $request->filled('status') ? (string) $request->query('status') : null,
            mode: $request->filled('mode') ? (string) $request->query('mode') : null,
            perPage: max(5, min(100, (int) $request->query('per_page', 15))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toFilterArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status,
            'mode' => $this->mode,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
