<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Models\User;
use App\Quality\Audit\CorruptionFixture;
use Modules\Platform\Domain\Models\SandboxEnvironment;

final class PlatformAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'platform:audit';
    }

    public function corrupt(): string
    {
        $user = User::first() ?? User::create([
            'name' => 'Audit Corrupt User',
            'email' => 'corrupt-audit@platform.test',
            'password' => bcrypt('secret123'),
        ]);

        SandboxEnvironment::create([
            'name' => 'Corrupted Sandbox Environment',
            'slug' => 'sbx-corrupted-audit-fixture',
            'tier' => 'standard',
            'status' => 'unauthorized_corrupted_state',
            'owner_id' => $user->id,
            'idempotency_key' => 'plt:corrupt:sandbox:audit:fixture:01',
        ]);

        return 'status tidak sah';
    }
}
