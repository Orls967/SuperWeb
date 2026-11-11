<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\Modules\BoundariesScanner;
use Illuminate\Console\Command;
use Modules\Core\Application\Boundaries\TransitionViolationAction;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

class BoundaryTriageCommand extends Command
{
    protected $signature = 'boundary:triage';

    protected $description = 'Sinkronkan pelanggaran batas arsitektur dari scan ke basis data secara idempoten';

    public function handle(BoundariesScanner $scanner, TransitionViolationAction $transitionAction): int
    {
        $this->info('Memindai pelanggaran batas arsitektur...');
        $analysis = $scanner->scan(base_path(), (array) config('modules'));
        $scannedViolations = $analysis['violations'];

        $createdCount = 0;
        $activeKeys = [];

        foreach ($scannedViolations as $v) {
            $key = "{$v['rule_id']}:{$v['file_path']}:{$v['signature']}";
            $activeKeys[$key] = true;

            $record = CoreBoundaryViolation::where('rule_id', $v['rule_id'])
                ->where('file_path', $v['file_path'])
                ->where('signature', $v['signature'])
                ->first();

            if ($record === null) {
                CoreBoundaryViolation::create([
                    'rule_id' => $v['rule_id'],
                    'category' => $v['category'],
                    'file_path' => $v['file_path'],
                    'line_number' => $v['line_number'],
                    'signature' => $v['signature'],
                    'client_module' => $v['client_module'],
                    'owner_module' => $v['owner_module'],
                    'status' => ViolationStatus::DETECTED->value,
                ]);
                $createdCount++;
            }
        }

        // Check for violations that disappeared from scan
        $migratingViolations = CoreBoundaryViolation::where('status', ViolationStatus::MIGRATING->value)->get();
        $verifiedCount = 0;

        foreach ($migratingViolations as $violation) {
            $key = "{$violation->rule_id}:{$violation->file_path}:{$violation->signature}";
            if (! isset($activeKeys[$key])) {
                $transitionAction->execute(
                    $violation,
                    ViolationStatus::VERIFIED,
                    'system:boundary-triage',
                    'Violation disappeared from static scan during migration',
                );
                $verifiedCount++;
            }
        }

        $this->info("Sinkronisasi selesai: {$createdCount} pelanggaran baru dicatat, {$verifiedCount} dipindahkan ke status verified.");

        return self::SUCCESS;
    }
}

