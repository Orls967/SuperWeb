<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\DesignSystem;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Domain\Exceptions\DesignSystem\InvalidComponentReleaseException;
use Modules\Core\Domain\Models\DsA11yFinding;
use Modules\Core\Domain\Models\DsComponent;
use Modules\Core\Domain\Models\DsComponentHistory;
use Modules\Core\Domain\StateMachines\ComponentReleaseStateMachine;

class ComponentReleaseAction
{
    /**
     * Cache idempotensi in-memory atau query deterministik.
     * @var array<string, int>
     */
    private static array $idempotencyCache = [];

    /**
     * Mengajukan usulan komponen kanonikal baru (status awal: proposed).
     *
     * @param array{
     *   component_name: string,
     *   requested_by_module: string,
     *   reason: string,
     *   idempotency_key?: string
     * } $data
     */
    public function propose(array $data): DsComponent
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if (empty($idempotencyKey)) {
            throw new InvalidArgumentException('Idempotency-Key wajib disertakan dalam aksi rilis komponen.');
        }

        // Cek cache idempotensi
        if (isset(self::$idempotencyCache[$idempotencyKey])) {
            return DsComponent::findOrFail(self::$idempotencyCache[$idempotencyKey]);
        }

        $componentName = strtolower(trim((string) ($data['component_name'] ?? '')));
        if ($componentName === '') {
            throw new InvalidArgumentException('Nama komponen wajib diisi.');
        }

        // Cek apakah komponen sudah ada
        $existing = DsComponent::where('component_name', $componentName)->first();
        if ($existing) {
            // Bila memiliki key sama, kembalikan objek yang sama
            self::$idempotencyCache[$idempotencyKey] = $existing->id;

            return $existing;
        }

        return DB::transaction(function () use ($data, $componentName, $idempotencyKey): DsComponent {
            $component = DsComponent::create([
                'component_name' => $componentName,
                'status' => ComponentReleaseStateMachine::STATUS_PROPOSED,
                'requested_by_module' => trim((string) ($data['requested_by_module'] ?? 'CORE')),
                'reason' => trim((string) ($data['reason'] ?? '')),
            ]);

            DsComponentHistory::create([
                'component_id' => $component->id,
                'from_status' => null,
                'to_status' => ComponentReleaseStateMachine::STATUS_PROPOSED,
                'actor_id' => null,
                'actor_role' => 'module_developer',
                'reason' => 'Pengajuan usulan komponen baru',
                'metadata' => ['idempotency_key' => $idempotencyKey],
                'created_at' => now(),
            ]);

            self::$idempotencyCache[$idempotencyKey] = $component->id;

            return $component;
        });
    }

    /**
     * Menjalankan transisi status komponen menggunakan State Machine.
     *
     * @param array<string, mixed> $context
     */
    public function transition(
        int $componentId,
        string $targetStatus,
        string $actorRole = 'system',
        ?int $actorId = null,
        string $reason = '',
        array $context = [],
        ?string $idempotencyKey = null
    ): DsComponent {
        if ($idempotencyKey && isset(self::$idempotencyCache[$idempotencyKey])) {
            return DsComponent::findOrFail(self::$idempotencyCache[$idempotencyKey]);
        }

        $component = DsComponent::findOrFail($componentId);

        // Jika status sudah sama persis, idempoten
        if ($component->status === $targetStatus) {
            return $component;
        }

        DB::transaction(function () use ($component, $targetStatus, $actorRole, $actorId, $reason, $context, $idempotencyKey): void {
            $sm = new ComponentReleaseStateMachine($component);
            $sm->transitionTo(
                $targetStatus,
                $actorRole,
                $actorId,
                $reason,
                $context,
                $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : []
            );
        });

        if ($idempotencyKey) {
            self::$idempotencyCache[$idempotencyKey] = $component->id;
        }

        return $component->fresh();
    }

    /**
     * Mencatat temuan aksesibilitas pada komponen di fase in_design / a11y_review.
     */
    public function logA11yFinding(int $componentId, string $rule, string $severity, string $description): DsA11yFinding
    {
        $component = DsComponent::findOrFail($componentId);

        return DsA11yFinding::create([
            'component_id' => $component->id,
            'rule_violated' => $rule,
            'severity' => in_array($severity, ['low', 'medium', 'high', 'critical'], true) ? $severity : 'medium',
            'description' => $description,
            'is_remediated' => false,
        ]);
    }

    /**
     * Menandai temuan aksesibilitas telah tuntas diperbaiki.
     */
    public function remediateA11yFinding(int $findingId): void
    {
        $finding = DsA11yFinding::findOrFail($findingId);
        $finding->markRemediated();
    }
}

