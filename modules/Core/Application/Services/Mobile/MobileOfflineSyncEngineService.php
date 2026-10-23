<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Mobile;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\DTOs\EpodSubmissionDto;
use Modules\Core\Application\DTOs\MobileFieldActionDto;
use Modules\Core\Application\DTOs\MobileSyncEventDto;
use Modules\Core\Domain\Contracts\MobileOfflineSyncInterface;
use Modules\Core\Domain\Models\CoreMobileDevice;
use Modules\Core\Domain\Models\CoreMobileEpodRecord;
use Modules\Core\Domain\Models\CoreMobileFieldAction;
use Modules\Core\Domain\Models\CoreMobileSyncEvent;

class MobileOfflineSyncEngineService implements MobileOfflineSyncInterface
{
    public function __construct(
        protected EpodIntegrityVerifierService $epodVerifier
    ) {}

    /**
     * Process batch of offline synchronization events.
     *
     * @param  list<MobileSyncEventDto|array<string, mixed>>  $events
     * @return array{
     *     total: int,
     *     applied: int,
     *     skipped_duplicates: int,
     *     resolved_conflicts: int,
     *     results: list<array<string, mixed>>
     * }
     */
    public function processBatch(array $events): array
    {
        $total = count($events);
        $applied = 0;
        $skippedDuplicates = 0;
        $resolvedConflicts = 0;
        $results = [];

        foreach ($events as $rawEvent) {
            $dto = $rawEvent instanceof MobileSyncEventDto ? $rawEvent : MobileSyncEventDto::fromArray($rawEvent);

            // 1. Idempotency guard: duplicate key is safely skipped
            $existing = CoreMobileSyncEvent::where('idempotency_key', $dto->idempotencyKey)->first();
            if ($existing) {
                $skippedDuplicates++;
                $results[] = [
                    'idempotency_key' => $dto->idempotencyKey,
                    'status' => 'SKIPPED_DUPLICATE',
                    'message' => 'Event already processed and recorded. Skipped duplicate mutation.',
                    'event_id' => $existing->id,
                ];

                continue;
            }

            // 2. Conflict Resolution (Last-Write-Wins with Timestamp Guard)
            $conflictStatus = 'APPLIED';
            $resolutionNotes = null;

            $referenceId = $dto->payload['reference_id'] ?? null;
            if ($referenceId) {
                $latestEventForEntity = CoreMobileSyncEvent::where('payload->reference_id', $referenceId)
                    ->orderByDesc('occurred_offline_at')
                    ->first();

                if ($latestEventForEntity && $dto->occurredOfflineAt < $latestEventForEntity->occurred_offline_at) {
                    $conflictStatus = 'RESOLVED_LWW';
                    $resolutionNotes = sprintf(
                        'Older offline timestamp (%s) guarded against newer recorded server state (%s).',
                        $dto->occurredOfflineAt->format('Y-m-d H:i:s'),
                        $latestEventForEntity->occurred_offline_at->format('Y-m-d H:i:s')
                    );
                    $resolvedConflicts++;
                }
            }

            // 3. Persist Sync Event
            $eventModel = DB::transaction(function () use ($dto, $conflictStatus, $resolutionNotes) {
                $event = CoreMobileSyncEvent::create([
                    'idempotency_key' => $dto->idempotencyKey,
                    'device_uuid' => $dto->deviceUuid,
                    'role_scope' => $dto->roleScope,
                    'event_type' => $dto->eventType,
                    'payload' => $dto->payload,
                    'occurred_offline_at' => $dto->occurredOfflineAt,
                    'server_received_at' => now(),
                    'conflict_status' => $conflictStatus,
                    'resolution_notes' => $resolutionNotes,
                ]);

                // Update device last seen
                CoreMobileDevice::updateOrCreate(
                    ['device_uuid' => $dto->deviceUuid],
                    [
                        'role_scope' => $dto->roleScope,
                        'app_version' => $dto->payload['app_version'] ?? '1.0.0',
                        'platform' => $dto->payload['platform'] ?? 'PWA',
                        'last_seen_at' => now(),
                    ]
                );

                // If event should execute field action
                if ($conflictStatus === 'APPLIED') {
                    $this->dispatchDomainAction($dto);
                }

                return $event;
            });

            if ($conflictStatus === 'APPLIED') {
                $applied++;
            }

            $results[] = [
                'idempotency_key' => $dto->idempotencyKey,
                'status' => $conflictStatus,
                'resolution_notes' => $resolutionNotes,
                'event_id' => $eventModel->id,
            ];
        }

        return [
            'total' => $total,
            'applied' => $applied,
            'skipped_duplicates' => $skippedDuplicates,
            'resolved_conflicts' => $resolvedConflicts,
            'results' => $results,
        ];
    }

    /**
     * Submit and record an electronic proof of delivery (e-POD).
     */
    public function submitEpod(EpodSubmissionDto $dto): CoreMobileEpodRecord
    {
        $signatureHash = $dto->calculateSignatureHash();
        $photoHash = $dto->calculatePhotoHash();

        return DB::transaction(function () use ($dto, $signatureHash, $photoHash) {
            $record = CoreMobileEpodRecord::updateOrCreate(
                ['epod_code' => $dto->epodCode],
                [
                    'shipment_ref' => $dto->shipmentRef,
                    'driver_ref' => $dto->driverRef,
                    'recipient_name' => $dto->recipientName,
                    'recipient_role' => $dto->recipientRole,
                    'photo_proof_hash' => $photoHash,
                    'signature_hash' => $signatureHash,
                    'latitude' => $dto->latitude,
                    'longitude' => $dto->longitude,
                    'delivered_offline_at' => $dto->deliveredOfflineAt,
                    'synced_at' => now(),
                    'signature_payload_b64' => $dto->signaturePayloadB64,
                    'is_tampered' => false,
                ]
            );

            // Record field action entry
            $this->recordFieldAction(new MobileFieldActionDto(
                actionCode: 'ACT-EPOD-'.$dto->epodCode,
                roleScope: 'driver',
                actionType: 'EPOD_RECEIPT',
                referenceId: $dto->shipmentRef,
                operatorName: $dto->driverRef,
                details: [
                    'epod_code' => $dto->epodCode,
                    'recipient_name' => $dto->recipientName,
                    'signature_hash' => $signatureHash,
                    'delivered_offline_at' => $dto->deliveredOfflineAt->format('Y-m-d H:i:s'),
                ]
            ));

            return $record;
        });
    }

    /**
     * Record field action for operational roles.
     */
    public function recordFieldAction(MobileFieldActionDto $dto): CoreMobileFieldAction
    {
        return CoreMobileFieldAction::updateOrCreate(
            ['action_code' => $dto->actionCode],
            [
                'role_scope' => $dto->roleScope,
                'action_type' => $dto->actionType,
                'reference_id' => $dto->referenceId,
                'operator_name' => $dto->operatorName,
                'status' => 'COMMITTED',
                'details' => $dto->details,
            ]
        );
    }

    /**
     * Dispatch field domain actions across 4 key roles.
     */
    protected function dispatchDomainAction(MobileSyncEventDto $dto): void
    {
        $actionType = match ($dto->eventType) {
            'MFG_OUTPUT_RECORDED' => 'WORK_ORDER_LOG',
            'MFG_DOWNTIME_LOGGED' => 'DOWNTIME_LOG',
            'WMS_PUTAWAY_CONFIRMED' => 'WMS_PUTAWAY',
            'WMS_PICK_WAVE_CONFIRMED' => 'WMS_PICK_WAVE',
            'LOG_EPOD_SUBMITTED' => 'EPOD_RECEIPT',
            'AGY_OFFLINE_ORDER_CREATED' => 'AGENT_ORDER',
            default => 'GENERAL_MUTATION',
        };

        $referenceId = (string) ($dto->payload['reference_id'] ?? $dto->idempotencyKey);
        $operatorName = (string) ($dto->payload['operator_name'] ?? 'Petugas Lapangan ('.$dto->roleScope.')');

        CoreMobileFieldAction::create([
            'action_code' => 'ACT-SYNC-'.bin2hex(random_bytes(6)),
            'role_scope' => $dto->roleScope,
            'action_type' => $actionType,
            'reference_id' => $referenceId,
            'operator_name' => $operatorName,
            'status' => 'COMMITTED',
            'details' => $dto->payload,
        ]);
    }
}
