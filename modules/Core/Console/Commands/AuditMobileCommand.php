<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Application\Services\Mobile\EpodIntegrityVerifierService;
use Modules\Core\Domain\Models\CoreMobileDevice;
use Modules\Core\Domain\Models\CoreMobileEpodRecord;
use Modules\Core\Domain\Models\CoreMobileSyncEvent;

class AuditMobileCommand extends Command
{
    protected $signature = 'mobile:audit';

    protected $description = 'Audit offline-first mobile synchronization idempotency and e-POD cryptographic signature integrity (Fase 65.3)';

    public function handle(EpodIntegrityVerifierService $verifier): int
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║   mobile:audit — Enterprise Mobile Suite & e-POD Audit       ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        if (! Schema::hasTable('core_mobile_sync_events') || ! Schema::hasTable('core_mobile_epod_records')) {
            $this->warn('Mobile operational tables not found; skipping mobile audit.');

            return self::SUCCESS;
        }

        // 1. Audit Sync Idempotency (Zero duplicate processed events)
        $this->line('1. Auditing offline synchronization event stream and duplicate prevention...');
        $totalEvents = CoreMobileSyncEvent::count();
        $duplicateKeys = CoreMobileSyncEvent::select('idempotency_key')
            ->groupBy('idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateKeys->isNotEmpty()) {
            $this->error("mobile:audit FAILED: Duplicate idempotency keys detected in event store ({$duplicateKeys->count()} duplicate keys).");

            return self::FAILURE;
        }

        // 2. Audit e-POD Digital Signatures & Anti-Tamper Verification
        $this->line('2. Auditing cryptographic SHA-256 signatures on all e-POD records...');
        $epodRecords = CoreMobileEpodRecord::all();
        $verifiedCount = 0;
        $tamperedCount = 0;

        foreach ($epodRecords as $epod) {
            if ($epod->is_tampered) {
                $this->error("mobile:audit FAILED: Tampered e-POD record detected: {$epod->epod_code} (shipment: {$epod->shipment_ref})");

                return self::FAILURE;
            }

            if (! $verifier->verifySignature($epod)) {
                $this->error("mobile:audit FAILED: Signature SHA-256 mismatch on e-POD {$epod->epod_code}");

                return self::FAILURE;
            }

            $verifiedCount++;
        }

        // 3. Device Governance Summary
        $deviceCount = CoreMobileDevice::count();
        $revokedCount = CoreMobileDevice::where('is_revoked', true)->count();
        $duplicatesPrevented = CoreMobileSyncEvent::where('conflict_status', 'SKIPPED_DUPLICATE')->count();

        $this->newLine();
        $this->line("  Registered Mobile Devices  : {$deviceCount} (revoked: {$revokedCount})");
        $this->line("  Sync Events Ingested       : {$totalEvents}");
        $this->line("  Duplicate Retries Skipped  : {$duplicatesPrevented}");
        $this->line("  e-POD Cryptographic Proofs : {$verifiedCount} verified (tampered: {$tamperedCount})");
        $this->newLine();

        $this->info('mobile:audit PASSED — 100% cryptographic integrity verified, zero duplicate records detected.');

        return self::SUCCESS;
    }
}
