<?php

declare(strict_types=1);

use App\Quality\Audit\AuditCommandRegistry;

it('classifies commands as audit, reconciliation or verification commands', function (): void {
    expect(AuditCommandRegistry::isAuditCommand('bank:reconcile'))->toBeTrue()
        ->and(AuditCommandRegistry::isAuditCommand('api:audit'))->toBeTrue()
        ->and(AuditCommandRegistry::isAuditCommand('core:verify-passports'))->toBeTrue()
        ->and(AuditCommandRegistry::isAuditCommand('chain:audit-all'))->toBeTrue()
        ->and(AuditCommandRegistry::isAuditCommand('inspire'))->toBeFalse()
        ->and(AuditCommandRegistry::isAuditCommand('migrate'))->toBeFalse()
        ->and(AuditCommandRegistry::isAuditCommand('route:list'))->toBeFalse();
});

it('provides corruption fixtures for bank:reconcile and api:audit', function (): void {
    $fixtures = AuditCommandRegistry::fixtures();

    expect($fixtures)->toHaveKeys(['bank:reconcile', 'api:audit'])
        ->and($fixtures['bank:reconcile']->command())->toBe('bank:reconcile')
        ->and($fixtures['api:audit']->command())->toBe('api:audit');
});

it('lists missing fixtures for commands that do not have one yet', function (): void {
    $missing = AuditCommandRegistry::commandsWithoutFixtures([
        'bank:reconcile',
        'api:audit',
        'hcm:audit',
        'route:list',
    ]);

    expect($missing)->toBe(['cmd:hcm:audit']);
});
