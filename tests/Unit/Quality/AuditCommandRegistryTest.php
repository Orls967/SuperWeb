<?php

declare(strict_types=1);

namespace Tests\Unit\Quality;

use App\Quality\Audit\AuditCommandRegistry;
use App\Quality\Audit\Fixtures\CircularAuditCorruptionFixture;
use Tests\TestCase;

class AuditCommandRegistryTest extends TestCase
{
    public function test_audit_command_registry_registers_fixtures_and_identifies_audit_commands(): void
    {
        $fixtures = AuditCommandRegistry::fixtures();

        $this->assertArrayHasKey('logi:circular-audit', $fixtures);
        $this->assertInstanceOf(CircularAuditCorruptionFixture::class, $fixtures['logi:circular-audit']);

        $this->assertTrue(AuditCommandRegistry::isAuditCommand('logi:circular-audit'));
        $this->assertTrue(AuditCommandRegistry::isAuditCommand('foo:audit'));
        $this->assertTrue(AuditCommandRegistry::isAuditCommand('bar:reconcile'));
        $this->assertTrue(AuditCommandRegistry::isAuditCommand('baz:verify-chain'));
        $this->assertFalse(AuditCommandRegistry::isAuditCommand('optimize:clear'));

        $auditCommands = AuditCommandRegistry::auditCommands(['logi:circular-audit', 'optimize:clear', 'prt:audit']);
        $this->assertSame(['logi:circular-audit', 'prt:audit'], $auditCommands);

        $missing = AuditCommandRegistry::commandsWithoutFixtures(['logi:circular-audit', 'unknown:audit']);
        $this->assertSame(['cmd:unknown:audit'], $missing);
    }
}
