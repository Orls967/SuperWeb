<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Bridge;

use App\Models\User;
use Modules\Core\Application\Services\ApprovalEngineService;
use Modules\Core\Domain\Models\ApprovalRequest;

/**
 * Jembatan Persetujuan Khusus Operasi Event Spine (SpineApprovalBridgeService)
 *
 * Mengintegrasikan tindakan berisiko tinggi pada Event Spine ke alur persetujuan ApprovalEngine:
 * - Pembuangan (discard) massal pesan DLQ lebih dari 20 pesan.
 * - Override pendaftaran skema event dengan breaking change darurat.
 * - Replay massal lebih dari 1000 event pada jam operasional sibuk.
 */
class SpineApprovalBridgeService
{
    public const TYPE_DLQ_BATCH_DISCARD = 'SPINE_DLQ_BATCH_DISCARD';

    public const TYPE_SCHEMA_BREAKING_OVERRIDE = 'SPINE_SCHEMA_BREAKING_OVERRIDE';

    public const TYPE_HIGH_VOLUME_REPLAY = 'SPINE_HIGH_VOLUME_REPLAY';

    public function __construct(
        protected ApprovalEngineService $approvalService
    ) {}

    /**
     * Mengajukan permintaan persetujuan untuk pembuangan pesan DLQ massal (> 20 pesan).
     *
     * @param  array<int, int>  $deliveryIds
     * @param  array<string, mixed>  $metadata
     */
    public function requestBatchDiscardApproval(
        User $requestor,
        array $deliveryIds,
        string $businessReason,
        array $metadata = []
    ): ApprovalRequest {
        $count = count($deliveryIds);
        $title = "Persetujuan Pembuangan Massal DLQ ({$count} Pesan) — {$businessReason}";

        $steps = [
            ['role' => 'spine_operator', 'order' => 1],
            ['role' => 'it_operations_manager', 'order' => 2],
        ];

        return $this->approvalService->submit(
            approvalType: self::TYPE_DLQ_BATCH_DISCARD,
            title: $title,
            creator: $requestor,
            approvable: null,
            amount: $count,
            steps: $steps,
            slaHours: 24,
            metadata: array_merge($metadata, [
                'delivery_ids' => $deliveryIds,
                'count' => $count,
                'reason' => $businessReason,
                'requested_at' => now()->toIso8601String(),
            ])
        );
    }

    /**
     * Mengajukan persetujuan untuk breaking change skema darurat.
     *
     * @param  array<string, mixed>  $schemaDefinition
     */
    public function requestBreakingSchemaOverride(
        User $requestor,
        string $topic,
        int $proposedVersion,
        array $schemaDefinition,
        string $businessJustification
    ): ApprovalRequest {
        $title = "Pengecualian Breaking Change Skema: Topik '{$topic}' Versi {$proposedVersion}";

        $steps = [
            ['role' => 'lead_architect', 'order' => 1],
            ['role' => 'head_of_engineering', 'order' => 2],
        ];

        return $this->approvalService->submit(
            approvalType: self::TYPE_SCHEMA_BREAKING_OVERRIDE,
            title: $title,
            creator: $requestor,
            approvable: null,
            amount: null,
            steps: $steps,
            slaHours: 12,
            metadata: [
                'topic' => $topic,
                'version' => $proposedVersion,
                'schema' => $schemaDefinition,
                'justification' => $businessJustification,
            ]
        );
    }
}
