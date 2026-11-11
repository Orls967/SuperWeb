<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

/**
 * Event subscriber for architectural boundary domain events (DR4-7d).
 *
 * Consumes events emitted by BoundaryOutboxPublisher to update audit trails,
 * trigger platform notifications, and log security compliance records.
 */
final class BoundaryEventSubscriber
{
    /**
     * Handle incoming boundary violation detected event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function onViolationDetected(array $payload): void
    {
        Log::channel('daily')->warning(
            sprintf(
                '[BOUNDARY_EVENT] Pelanggaran arsitektur baru terdeteksi: [%s] %s di %s:%d (Klien: %s, Pemilik: %s)',
                $payload['rule_id'] ?? 'A1',
                $payload['signature'] ?? '',
                $payload['file_path'] ?? '',
                (int) ($payload['line_number'] ?? 0),
                $payload['client_module'] ?? '',
                $payload['owner_module'] ?? ''
            )
        );
    }

    /**
     * Handle incoming waiver granted event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function onWaiverGranted(array $payload): void
    {
        Log::channel('daily')->info(
            sprintf(
                '[BOUNDARY_EVENT] Waiver arsitektur disetujui: Pelanggaran #%d (%s) disetujui oleh %s dengan rujukan %s (berlaku s.d. %s)',
                (int) ($payload['violation_id'] ?? 0),
                $payload['rule_id'] ?? '',
                $payload['waived_by'] ?? 'Unknown',
                $payload['adr_anchor'] ?? '',
                $payload['expires_at'] ?? ''
            )
        );
    }

    /**
     * Handle incoming waiver expired event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function onWaiverExpired(array $payload): void
    {
        Log::channel('daily')->alert(
            sprintf(
                '[BOUNDARY_EVENT] WAIVER KEDALUWARSA: Waiver #%d (%s) pada %s telah kedaluwarsa. Status dikembalikan ke triaged.',
                (int) ($payload['violation_id'] ?? 0),
                $payload['rule_id'] ?? '',
                $payload['client_module'] ?? ''
            )
        );
    }

    /**
     * Handle status transitioned event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function onStatusTransitioned(array $payload): void
    {
        Log::channel('daily')->info(
            sprintf(
                '[BOUNDARY_EVENT] Status pelanggaran #%d berubah: %s -> %s oleh %s. Alasan: %s',
                (int) ($payload['violation_id'] ?? 0),
                $payload['from_status'] ?? '',
                $payload['to_status'] ?? '',
                $payload['actor'] ?? '',
                $payload['reason'] ?? ''
            )
        );
    }

    /**
     * Register listeners with Laravel's Event Dispatcher.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            'core.boundary.violation_detected.v1',
            [self::class, 'onViolationDetected']
        );

        $events->listen(
            'core.boundary.waiver_granted.v1',
            [self::class, 'onWaiverGranted']
        );

        $events->listen(
            'core.boundary.waiver_expired.v1',
            [self::class, 'onWaiverExpired']
        );

        $events->listen(
            'core.boundary.status_transitioned.v1',
            [self::class, 'onStatusTransitioned']
        );
    }
}

