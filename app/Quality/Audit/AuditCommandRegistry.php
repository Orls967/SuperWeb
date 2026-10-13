<?php

declare(strict_types=1);

namespace App\Quality\Audit;

use App\Quality\Audit\Fixtures\ApiAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\BankReconcileCorruptionFixture;
use Illuminate\Support\Facades\Artisan;

final class AuditCommandRegistry
{
    /**
     * Known corruption fixtures for audit commands.
     *
     * @return array<string, CorruptionFixture>
     */
    public static function fixtures(): array
    {
        return [
            'bank:reconcile' => new BankReconcileCorruptionFixture,
            'api:audit' => new ApiAuditCorruptionFixture,
        ];
    }

    /**
     * All registered Artisan commands that act as audit, reconciliation, or verification commands.
     *
     * @param  list<string>|null  $commandNames  explicit list of names (default: Artisan::all() keys)
     * @return list<string>
     */
    public static function auditCommands(?array $commandNames = null): array
    {
        $names = $commandNames ?? array_keys(Artisan::all());

        $audits = array_values(array_filter($names, static fn (string $name): bool => self::isAuditCommand($name)));
        sort($audits);

        return $audits;
    }

    public static function isAuditCommand(string $name): bool
    {
        return str_contains($name, ':audit')
            || str_ends_with($name, 'audit')
            || str_contains($name, ':reconcile')
            || str_contains($name, 'verify-')
            || str_contains($name, ':verify');
    }

    /**
     * Returns list of audit commands without a corruption fixture.
     *
     * @param  list<string>|null  $commandNames
     * @return list<string> formatted as "cmd:{name}" for set baseline
     */
    public static function commandsWithoutFixtures(?array $commandNames = null): array
    {
        $audits = self::auditCommands($commandNames);
        $fixtures = self::fixtures();

        $missing = [];
        foreach ($audits as $cmd) {
            if (! isset($fixtures[$cmd])) {
                $missing[] = "cmd:{$cmd}";
            }
        }

        sort($missing);

        return $missing;
    }
}
