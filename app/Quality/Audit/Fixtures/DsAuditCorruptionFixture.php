<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Core\Domain\Models\DsComponent;

final class DsAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ds:audit';
    }

    public function corrupt(): string
    {
        // Suntikkan komponen dengan status anomali yang tidak sah
        DsComponent::create([
            'name' => 'corrupted-component-fixture',
            'display_name' => 'Komponen Anomali Fixture',
            'category' => 'fixture',
            'status' => 'unauthorized_corrupted_state',
            'current_version' => '0.0.0-corrupt',
            'wcag_level' => 'AA',
            'spec_doc_url' => 'docs/corrupt.md',
            'props_schema' => [],
            'slots_schema' => [],
            'events_schema' => [],
            'owner_id' => null,
            'idempotency_key' => 'ds:corrupt:audit:fixture:01',
        ]);

        return 'status komponen ds tidak sah';
    }
}
