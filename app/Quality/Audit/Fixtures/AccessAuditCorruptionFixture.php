<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Models\User;
use App\Quality\Audit\CorruptionFixture;
use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreTenant;

final class AccessAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'access:audit';
    }

    public function corrupt(): string
    {
        $this->inject();

        return 'ACCESS_AUDIT_FAIL';
    }

    /**
     * Inject a corrupt grant with active status but no approval history.
     */
    public function inject(): int
    {
        $tenant = CoreTenant::first() ?? CoreTenant::create([
            'id' => '0193188a-9999-7000-8000-000000000099',
            'code' => 'TENANT-CORRUPT-FIXTURE',
            'name' => 'Tenant Corrupt Fixture',
            'tier' => 'SILVER',
            'status' => 'active',
        ]);

        $user = User::first() ?? User::factory()->create();

        $grant = CoreAccessGrant::create([
            'grant_code' => 'GRT-CORRUPT-'.time(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'resource' => 'asset:equipment',
            'action' => 'dispose',
            'status' => 'active', // Active without approved transition!
            'scope_dimensions' => ['entity_id' => 'ILLEGAL'],
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDays(10),
            'grant_version' => 1,
            'reason' => 'Illegally injected grant for audit testing',
        ]);

        return $grant->id;
    }

    /**
     * Restore database state by deleting injected corrupt record.
     */
    public function restore(int $corruptId): void
    {
        CoreAccessGrant::where('id', $corruptId)->delete();
    }
}
