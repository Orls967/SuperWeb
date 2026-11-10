<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines;

use DomainException;
use Modules\Core\Domain\Exceptions\DesignSystem\ComponentTransitionGuardException;
use Modules\Core\Domain\Models\DsComponent;
use Modules\Core\Domain\Models\DsComponentHistory;

/**
 * ComponentReleaseStateMachine (State Machine 7 Status — Fase 240)
 *
 * Mengatur siklus hidup rilis komponen kanonikal x-ds.*:
 * proposed -> in_design -> a11y_review -> approved -> released -> deprecated -> removed
 * Dengan riwayat transisi append-only dan guard ketat di setiap transisi status.
 */
class ComponentReleaseStateMachine
{
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_IN_DESIGN = 'in_design';
    public const STATUS_A11Y_REVIEW = 'a11y_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RELEASED = 'released';
    public const STATUS_DEPRECATED = 'deprecated';
    public const STATUS_REMOVED = 'removed';

    /**
     * @var array<string, list<string>> Tabel transisi status kanonikal
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_PROPOSED => [self::STATUS_IN_DESIGN, self::STATUS_REMOVED],
        self::STATUS_IN_DESIGN => [self::STATUS_A11Y_REVIEW],
        self::STATUS_A11Y_REVIEW => [self::STATUS_IN_DESIGN, self::STATUS_APPROVED],
        self::STATUS_APPROVED => [self::STATUS_RELEASED],
        self::STATUS_RELEASED => [self::STATUS_DEPRECATED],
        self::STATUS_DEPRECATED => [self::STATUS_REMOVED],
        self::STATUS_REMOVED => [],
    ];

    public function __construct(
        private readonly DsComponent $component
    ) {}

    /**
     * Memeriksa apakah transisi secara struktural diizinkan dalam tabel transisi.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        $current = $this->component->status;
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        return in_array($targetStatus, $allowed, true);
    }

    /**
     * Memeriksa guard aturan bisnis untuk transisi ke target status.
     * Melempar ComponentTransitionGuardException bila syarat tidak terpenuhi.
     *
     * @param array<string, mixed> $context
     */
    public function assertTransitionAllowed(string $targetStatus, array $context = []): void
    {
        $current = $this->component->status;
        if (! $this->canTransitionTo($targetStatus)) {
            throw new DomainException(
                "Transisi ilegal dari '{$current}' ke '{$targetStatus}' untuk komponen '{$this->component->component_name}'."
            );
        }

        // Guard per transisi:
        switch ("{$current}->{$targetStatus}") {
            case self::STATUS_PROPOSED.'->'.self::STATUS_IN_DESIGN:
                if (empty($this->component->requested_by_module) || empty($this->component->reason)) {
                    throw new ComponentTransitionGuardException(
                        $this->component->component_name,
                        $current,
                        $targetStatus,
                        'Modul pemohon dan alasan kebutuhan wajib diisi.'
                    );
                }
                break;

            case self::STATUS_IN_DESIGN.'->'.self::STATUS_A11Y_REVIEW:
                $hasLintError = (bool) ($context['has_lint_error'] ?? false);
                $contrastPassed = (bool) ($context['contrast_passed'] ?? true);
                if ($hasLintError) {
                    throw new ComponentTransitionGuardException(
                        $this->component->component_name,
                        $current,
                        $targetStatus,
                        'Hasil ui:lint masih memiliki pelanggaran aksesibilitas.'
                    );
                }
                if (! $contrastPassed) {
                    throw new ComponentTransitionGuardException(
                        $this->component->component_name,
                        $current,
                        $targetStatus,
                        'Pasangan token warna gagal memenuhi ambang batas kontras WCAG AA (45.000 bps).'
                    );
                }
                break;

            case self::STATUS_A11Y_REVIEW.'->'.self::STATUS_APPROVED:
                if ($this->component->hasOpenA11yFindings()) {
                    throw new ComponentTransitionGuardException(
                        $this->component->component_name,
                        $current,
                        $targetStatus,
                        'Komponen masih memiliki temuan aksesibilitas yang belum tuntas (remediated).'
                    );
                }
                break;

            case self::STATUS_APPROVED.'->'.self::STATUS_RELEASED:
                // Guard: entri rilis siap didokumentasikan
                break;

            case self::STATUS_RELEASED.'->'.self::STATUS_DEPRECATED:
                $replacement = $context['replacement_component'] ?? $this->component->replacement_component;
                if (empty($replacement)) {
                    throw new ComponentTransitionGuardException(
                        $this->component->component_name,
                        $current,
                        $targetStatus,
                        'Komponen pengganti (replacement_component) wajib ditunjuk sebelum deprecate.'
                    );
                }
                break;

            case self::STATUS_DEPRECATED.'->'.self::STATUS_REMOVED:
                $usageCount = $this->component->getTotalUsageCount();
                if ($usageCount > 0) {
                    throw new ComponentTransitionGuardException(
                        $this->component->component_name,
                        $current,
                        $targetStatus,
                        "Komponen masih dipanggil oleh {$usageCount} view aktif. Hapus pemakaian terlebih dahulu."
                    );
                }
                break;

            case self::STATUS_PROPOSED.'->'.self::STATUS_REMOVED:
                $rejectionReason = $context['reason'] ?? '';
                if (trim((string) $rejectionReason) === '') {
                    throw new ComponentTransitionGuardException(
                        $this->component->component_name,
                        $current,
                        $targetStatus,
                        'Alasan penolakan komponen usulan wajib diisi.'
                    );
                }
                break;
        }
    }

    /**
     * Mengeksekusi transisi status dan mencatat riwayat append-only.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $metadata
     */
    public function transitionTo(
        string $targetStatus,
        string $actorRole = 'system',
        ?int $actorId = null,
        string $reason = '',
        array $context = [],
        array $metadata = []
    ): DsComponentHistory {
        $this->assertTransitionAllowed($targetStatus, $context);

        $fromStatus = $this->component->status;
        $this->component->status = $targetStatus;

        if (isset($context['replacement_component'])) {
            $this->component->replacement_component = (string) $context['replacement_component'];
        }
        if (isset($context['documentation_url'])) {
            $this->component->documentation_url = (string) $context['documentation_url'];
        }

        $this->component->save();

        return DsComponentHistory::create([
            'component_id' => $this->component->id,
            'from_status' => $fromStatus,
            'to_status' => $targetStatus,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'reason' => $reason ?: "Transisi status dari {$fromStatus} ke {$targetStatus}",
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
