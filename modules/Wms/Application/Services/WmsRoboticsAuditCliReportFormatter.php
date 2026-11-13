<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

/**
 * Pemformat laporan CLI terstruktur untuk hasil audit dua sumber robotika (`robotics:audit`).
 */
final readonly class WmsRoboticsAuditCliReportFormatter
{
    /**
     * @param  array{ok:bool, issue_count:int, issues:list<array<string, mixed>>, state_hash:string}  $auditResult
     * @return list<string>
     */
    public function formatLines(array $auditResult): array
    {
        $lines = [];
        $status = $auditResult['ok'] ? 'BERSIH' : 'GAGAL';
        $lines[] = sprintf(
            'robotics:audit [%s] issues=%d state_hash=%s',
            $status,
            (int) $auditResult['issue_count'],
            substr((string) $auditResult['state_hash'], 0, 16),
        );

        foreach ($auditResult['issues'] as $idx => $issue) {
            if (is_string($issue)) {
                $lines[] = sprintf('  [%d] %s', $idx + 1, $issue);
                continue;
            }
            $lines[] = sprintf(
                '  [%d] %s | %s | %s',
                $idx + 1,
                (string) ($issue['check'] ?? 'UNKNOWN'),
                (string) ($issue['entity_code'] ?? '-'),
                (string) ($issue['detail'] ?? ''),
            );
        }

        return $lines;
    }
}
