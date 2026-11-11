<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Baseline\FingerprintBaseline;
use App\Quality\Docs\DecisionLog;
use App\Quality\Modules\BoundariesScanner;
use Illuminate\Console\Command;
use Modules\Core\Domain\Algorithms\Boundaries\PrefixCertificateVerifier;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

class BoundaryAuditCommand extends Command
{
    protected $signature = 'boundary:audit';

    protected $description = 'Audit rekonsiliasi dua sumber: graf pemindaian batas vs database, baseline, waiver, dan siklus';

    public function handle(BoundariesScanner $scanner): int
    {
        $this->info('Memulai audit dua sumber batas arsitektur modul...');
        $discrepancies = [];

        $config = (array) config('modules', require base_path('config/modules.php'));
        $analysis = $scanner->scan(base_path(), $config);

        // 1. Audit: Scan vs Database (every scanned violation must have a row in core_boundary_violations)
        foreach ($analysis['violations'] as $v) {
            $record = CoreBoundaryViolation::where('rule_id', $v['rule_id'])
                ->where('file_path', $v['file_path'])
                ->where('signature', $v['signature'])
                ->first();

            if ($record === null) {
                $discrepancies[] = "SCAN_DB_MISMATCH: Pelanggaran {$v['rule_id']} pada {$v['file_path']}:{$v['line_number']} belum tercatat di core_boundary_violations.";
            }
        }

        // 2. Audit: Baseline vs Scan (no new violations exceeding ratchet baseline)
        $baselinePath = base_path(ArchScanCommand::DEFAULT_BASELINE);
        if (file_exists($baselinePath)) {
            $baseline = FingerprintBaseline::fromFile($baselinePath);
            $baselineData = json_decode((string) file_get_contents($baselinePath), true);
            $a1BaselineEntries = $baselineData['rules']['A1']['entries'] ?? [];
            $a2BaselineEntries = $baselineData['rules']['A2']['entries'] ?? [];

            // Check if any active scan violation is missing from baseline
            foreach ($analysis['violations'] as $v) {
                if ($v['rule_id'] === 'A1') {
                    if (! isset($a1BaselineEntries[$v['file_path']][$v['signature']])) {
                        $discrepancies[] = "BASELINE_NEW_VIOLATION: Pelanggaran baru A1 pada {$v['file_path']} tidak ada di baseline.";
                    }
                } elseif ($v['rule_id'] === 'A2') {
                    if (! isset($a2BaselineEntries[$v['file_path']][$v['signature']])) {
                        $discrepancies[] = "BASELINE_NEW_VIOLATION: Pelanggaran baru A2 pada {$v['file_path']} tidak ada di baseline.";
                    }
                }
            }
        }

        // 3. Audit: Waivers (no waiver without DECISIONS anchor or expired)
        $decisions = DecisionLog::fromFile(base_path('docs/DECISIONS.md'));
        $waivedViolations = CoreBoundaryViolation::where('status', ViolationStatus::WAIVED->value)->get();

        foreach ($waivedViolations as $waiver) {
            if (empty($waiver->adr_anchor)) {
                $discrepancies[] = "WAIVER_NO_ANCHOR: Waiver #{$waiver->id} ({$waiver->signature}) tidak memiliki rujukan adr_anchor.";
            } else {
                $cleanAnchor = ltrim(trim($waiver->adr_anchor), '#');
                if (str_contains($cleanAnchor, '#')) {
                    $parts = explode('#', $cleanAnchor);
                    $cleanAnchor = end($parts);
                }

                if (! $decisions->has($cleanAnchor)) {
                    $discrepancies[] = "WAIVER_INVALID_ANCHOR: Waiver #{$waiver->id} merujuk anchor '{$waiver->adr_anchor}' yang tidak ditemukan di docs/DECISIONS.md.";
                }
            }

            if ($waiver->expires_at === null || $waiver->expires_at->isPast()) {
                $discrepancies[] = "WAIVER_EXPIRED: Waiver #{$waiver->id} telah kedaluwarsa pada {$waiver->expires_at?->toIso8601String()}.";
            }
        }

        // 4. Audit: SCC Cycles > 0
        if ($analysis['scc']['hasCycles']) {
            foreach ($analysis['scc']['cycles'] as $cycle) {
                $cycleStr = implode(' -> ', $cycle).' -> '.$cycle[0];
                $discrepancies[] = "CYCLE_DETECTED: Siklus dependensi modul terlarang: {$cycleStr}.";
            }
        }

        // 5. Audit: PREFIX_CONFLICT
        $prefixVerifier = new PrefixCertificateVerifier;
        if (! $prefixVerifier->verifyPrefixes($config['table_prefixes'] ?? [])) {
            $discrepancies[] = 'PREFIX_CONFLICT: Terdeteksi duplikasi atau format tidak valid pada prefiks tabel di config/modules.php.';
        }

        // 6. Audit: Status CLOSED must NOT exist in baseline
        if (isset($a1BaselineEntries, $a2BaselineEntries)) {
            $closedViolations = CoreBoundaryViolation::where('status', ViolationStatus::CLOSED->value)->get();
            foreach ($closedViolations as $closed) {
                if ($closed->rule_id === 'A1' && isset($a1BaselineEntries[$closed->file_path][$closed->signature])) {
                    $discrepancies[] = "CLOSED_IN_BASELINE: Pelanggaran #{$closed->id} berstatus CLOSED tetapi masih ada di baseline A1.";
                } elseif ($closed->rule_id === 'A2' && isset($a2BaselineEntries[$closed->file_path][$closed->signature])) {
                    $discrepancies[] = "CLOSED_IN_BASELINE: Pelanggaran #{$closed->id} berstatus CLOSED tetapi masih ada di baseline A2.";
                }
            }
        }

        // Report audit results
        if ($discrepancies !== []) {
            $this->error('[AUDIT_FAIL] boundary:audit mendeteksi '.count($discrepancies).' diskrepansi:');
            foreach ($discrepancies as $idx => $d) {
                $this->line(sprintf('  [%d] %s', $idx + 1, $d));
            }

            return self::FAILURE;
        }

        $this->info('[AUDIT_PASS] boundary:audit bersih: seluruh invarian batas modul terverifikasi.');

        return self::SUCCESS;
    }
}
