<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Audit;

use DateTimeImmutable;

/**
 * Access Governance & Isolation Anomaly Security Alert (W8-D58, W8-D60).
 *
 * Formats critical security incident events for SIEM/SOC event ingestion
 * when tenant isolation leaks, unauthorized break-glass, or exposure limit breaches occur.
 */
final class AccessAuditAnomalyAlert
{
    public const SEVERITY_INFO = 'INFO';

    public const SEVERITY_LOW = 'LOW';

    public const SEVERITY_MEDIUM = 'MEDIUM';

    public const SEVERITY_HIGH = 'HIGH';

    public const SEVERITY_CRITICAL = 'CRITICAL';

    /**
     * @param  string  $alertId  Unique event identifier
     * @param  string  $alertCode  Machine category code (e.g. 'CROSS_TENANT_LEAK_DETECTED')
     * @param  string  $severity  Alert severity
     * @param  string  $tenantId  Associated tenant UUID
     * @param  string  $summary  Human-readable summary
     * @param  array<string, mixed>  $contextData  Detailed payload context
     * @param  DateTimeImmutable  $occurredAt  Event timestamp
     */
    public function __construct(
        public readonly string $alertId,
        public readonly string $alertCode,
        public readonly string $severity,
        public readonly string $tenantId,
        public readonly string $summary,
        public readonly array $contextData = [],
        public readonly DateTimeImmutable $occurredAt = new DateTimeImmutable
    ) {}

    /**
     * Factory for critical cross-tenant data leak alert.
     *
     * @param  array<string, mixed>  $context
     */
    public static function crossTenantLeak(string $tenantId, string $targetModel, string $attemptedId, array $context = []): self
    {
        $id = 'ALT-LEAK-'.strtoupper(hash('crc32b', microtime()));

        return new self(
            alertId: $id,
            alertCode: 'CROSS_TENANT_LEAK_DETECTED',
            severity: self::SEVERITY_CRITICAL,
            tenantId: $tenantId,
            summary: "Cross-tenant access attempted on model [{$targetModel} #{$attemptedId}] outside active tenant context.",
            contextData: array_merge($context, [
                'model' => $targetModel,
                'target_id' => $attemptedId,
            ])
        );
    }

    /**
     * Factory for vendor group credit limit breach alert.
     *
     * @param  array<string, mixed>  $context
     */
    public static function vendorExposureBreach(string $vendorClusterCode, int $totalExposureIdr, int $limitIdr, array $context = []): self
    {
        $id = 'ALT-EXPO-'.strtoupper(hash('crc32b', microtime()));

        return new self(
            alertId: $id,
            alertCode: 'VENDOR_EXPOSURE_LIMIT_BREACHED',
            severity: self::SEVERITY_HIGH,
            tenantId: 'GLOBAL_PLATFORM',
            summary: "Vendor cluster [{$vendorClusterCode}] breached Rp 500M group credit limit: Rp {$totalExposureIdr} / Rp {$limitIdr}.",
            contextData: array_merge($context, [
                'vendor_cluster_code' => $vendorClusterCode,
                'total_exposure_idr' => $totalExposureIdr,
                'limit_idr' => $limitIdr,
                'excess_idr' => $totalExposureIdr - $limitIdr,
            ])
        );
    }

    /**
     * Format for syslog / SIEM RFC-5424 ingestion.
     *
     * @return array<string, mixed>
     */
    public function toSiemPayload(): array
    {
        return [
            'event_id' => $this->alertId,
            'event_code' => $this->alertCode,
            'severity' => $this->severity,
            'tenant_id' => $this->tenantId,
            'message' => $this->summary,
            'timestamp' => $this->occurredAt->format('c'),
            'details' => $this->contextData,
        ];
    }
}
