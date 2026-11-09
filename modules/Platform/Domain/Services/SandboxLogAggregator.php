<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\SandboxEnvironment;

/**
 * Pengumpul dan penyaring log eksekusi sandbox developer.
 */
class SandboxLogAggregator
{
    /** @var array<int, list<array{timestamp: string, level: string, message: string}>> */
    private static array $inMemoryLogs = [];

    public function appendLog(SandboxEnvironment $sandbox, string $level, string $message): void
    {
        self::$inMemoryLogs[$sandbox->id][] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
        ];
    }

    /**
     * @return list<array{timestamp: string, level: string, message: string}>
     */
    public function getLogs(SandboxEnvironment $sandbox, int $limit = 100): array
    {
        $logs = self::$inMemoryLogs[$sandbox->id] ?? [];

        return array_slice($logs, -$limit);
    }

    public function clearLogs(SandboxEnvironment $sandbox): void
    {
        unset(self::$inMemoryLogs[$sandbox->id]);
    }
}
