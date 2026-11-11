<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Core\Domain\Enums\ViolationCategory;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

final class BoundaryAuditCorruptionFixture implements CorruptionFixture
{
    private ?int $corruptedId = null;

    public function command(): string
    {
        return 'boundary:audit';
    }

    public function corrupt(): string
    {
        // Inject an active waiver with a non-existent anchor and expired date
        $violation = CoreBoundaryViolation::create([
            'rule_id' => 'A1',
            'category' => ViolationCategory::DOMAIN_IMPORT->value,
            'file_path' => 'modules/Core/CorruptFixtureSample.php',
            'line_number' => 999,
            'signature' => 'use CorruptFixtureTestClass',
            'client_module' => 'Core',
            'owner_module' => 'Core',
            'status' => ViolationStatus::WAIVED->value,
            'adr_anchor' => 'docs/DECISIONS.md#fake-nonexistent-anchor-w8-corrupt',
            'waiver_reason' => 'Intentional corruption for dual-source audit test',
            'waived_by' => 'fixture',
            'waived_at' => now()->subDays(100),
            'expires_at' => now()->subDays(10), // expired
        ]);

        $this->corruptedId = $violation->id;

        return "WAIVER_INVALID_ANCHOR: Waiver #{$violation->id}";
    }

    public function restore(): void
    {
        if ($this->corruptedId !== null) {
            CoreBoundaryViolation::where('id', $this->corruptedId)->delete();
            $this->corruptedId = null;
        }
    }
}
