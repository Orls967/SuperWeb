<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Str;
use Modules\Integration\Domain\Models\SecurityIncident;

/**
 * Threat Detection & Incident Response Service (Fase 144.4)
 *
 * Handles:
 *  - SOC simulation: detect anomalous patterns
 *  - Incident creation with severity and playbook selection
 *  - War room lifecycle: contain → resolve → postmortem → CAPA → regulator report
 */
class ThreatDetectionService
{
    /** Playbook steps by incident class */
    private const PLAYBOOKS = [
        SecurityIncident::CLASS_RANSOMWARE => [
            '1. Isolate affected services from network',
            '2. Take VM snapshots for forensic analysis',
            '3. Notify CISO & executive team via OOB channel',
            '4. Activate BC plan: shift workloads to standby region',
            '5. Contact law enforcement (BSSN) within 1 hour',
            '6. Begin decryption key negotiation (if applicable)',
            '7. Restore from last clean snapshot after clearance',
            '8. Post-incident: root cause analysis & patch cycle',
        ],
        SecurityIncident::CLASS_DATA_LEAK => [
            '1. Identify breached data scope (PII categories & subject count)',
            '2. Revoke access tokens for compromised service identities',
            '3. Notify DPO within 72 hours (UU PDP Pasal 46)',
            '4. Issue breach notification to affected subjects',
            '5. Rotate encryption keys in Privacy Vault',
            '6. Block outbound traffic from compromised endpoint',
            '7. Engage privacy vault audit trail review',
            '8. File regulator report to Kominfo within 14 days',
        ],
        SecurityIncident::CLASS_PAYMENT_FRAUD => [
            '1. Freeze affected wallet accounts immediately',
            '2. Reverse suspicious transactions (bank:reconcile)',
            '3. Notify Payment Hub team & acquiring banks',
            '4. Enable step-up authentication for affected users',
            '5. Run fraud pattern scan across 24h window',
            '6. Submit SAR (Suspicious Activity Report) to PPATK',
            '7. Restore affected user accounts after manual verification',
            '8. Audit trail: confirm all reversed transactions balanced',
        ],
        SecurityIncident::CLASS_UNAUTH_ACCESS => [
            '1. Terminate unauthorized sessions immediately',
            '2. Rotate service identity mTLS certificates',
            '3. Audit access logs: identify privilege escalation path',
            '4. Patch exploited vulnerability within 4 hours',
            '5. Run full pentest on affected route × role matrix',
            '6. Notify affected line owners',
            '7. Update Zero Trust ability allowlist',
            '8. Verify no lateral movement to adjacent modules',
        ],
    ];

    /**
     * Create a security incident and assign the appropriate playbook.
     */
    public function detectIncident(array $data): SecurityIncident
    {
        $class = strtoupper($data['incident_class']);
        $severity = strtoupper($data['severity'] ?? 'P3');

        $playbook = self::PLAYBOOKS[$class] ?? [
            '1. Identify and scope the incident',
            '2. Contain the threat',
            '3. Notify stakeholders',
            '4. Resolve and document',
        ];

        return SecurityIncident::create([
            'incident_code' => 'INC-'.strtoupper(Str::random(10)),
            'incident_class' => $class,
            'severity' => $severity,
            'status' => SecurityIncident::STATUS_DETECTED,
            'affected_lines' => $data['affected_lines'] ?? [],
            'description' => $data['description'],
            'playbook_steps' => $playbook,
            'detected_at' => now(),
        ]);
    }

    /**
     * Move incident to INVESTIGATING status.
     */
    public function openWarRoom(string $incidentCode): SecurityIncident
    {
        $incident = SecurityIncident::where('incident_code', $incidentCode)->firstOrFail();
        $incident->update(['status' => SecurityIncident::STATUS_INVESTIGATING]);

        return $incident->fresh();
    }

    /**
     * Mark incident as contained.
     */
    public function containIncident(string $incidentCode): SecurityIncident
    {
        $incident = SecurityIncident::where('incident_code', $incidentCode)->firstOrFail();
        $incident->update([
            'status' => SecurityIncident::STATUS_CONTAINED,
            'contained_at' => now(),
        ]);

        return $incident->fresh();
    }

    /**
     * Resolve incident with postmortem & CAPA documentation.
     */
    public function resolveIncident(string $incidentCode, string $postmortem, string $capa): SecurityIncident
    {
        $incident = SecurityIncident::where('incident_code', $incidentCode)->firstOrFail();
        $incident->update([
            'status' => SecurityIncident::STATUS_RESOLVED,
            'resolved_at' => now(),
            'postmortem' => $postmortem,
            'capa' => $capa,
        ]);

        return $incident->fresh();
    }

    /**
     * Generate regulator report and close the incident.
     */
    public function closeWithRegulatorReport(string $incidentCode, string $regulatorReport): SecurityIncident
    {
        $incident = SecurityIncident::where('incident_code', $incidentCode)->firstOrFail();
        $incident->update([
            'status' => SecurityIncident::STATUS_CLOSED,
            'regulator_report' => $regulatorReport,
        ]);

        return $incident->fresh();
    }

    /**
     * Run tabletop exercise: simulate incident flow end-to-end.
     */
    public function runTabletopExercise(string $incidentClass, array $affectedLines): array
    {
        $incident = $this->detectIncident([
            'incident_class' => $incidentClass,
            'severity' => 'P2',
            'description' => "Tabletop drill: {$incidentClass} simulation across ".implode(', ', $affectedLines),
            'affected_lines' => $affectedLines,
        ]);

        $this->openWarRoom($incident->incident_code);
        $this->containIncident($incident->incident_code);
        $this->resolveIncident(
            $incident->incident_code,
            postmortem: "Tabletop completed. Attack vector: simulated {$incidentClass}. No real data affected.",
            capa: '1. Update playbook. 2. Re-test within 90 days. 3. Train SOC team on findings.'
        );
        $this->closeWithRegulatorReport(
            $incident->incident_code,
            regulatorReport: "Drill report: IR test #{$incident->incident_code} completed successfully. MTTR: < 60 minutes."
        );

        $fresh = $incident->fresh();

        return [
            'incident_code' => $fresh->incident_code,
            'class' => $fresh->incident_class,
            'final_status' => $fresh->status,
            'mttr_minutes' => $fresh->mttr(),
            'playbook_steps' => count($fresh->playbook_steps ?? []),
        ];
    }

    /**
     * SOC audit: returns open/contained incident counts.
     */
    public function socAudit(): array
    {
        $open = SecurityIncident::whereIn('status', [
            SecurityIncident::STATUS_DETECTED,
            SecurityIncident::STATUS_INVESTIGATING,
        ])->count();

        $contained = SecurityIncident::where('status', SecurityIncident::STATUS_CONTAINED)->count();
        $resolved = SecurityIncident::whereIn('status', [
            SecurityIncident::STATUS_RESOLVED,
            SecurityIncident::STATUS_CLOSED,
        ])->count();

        $withoutCapa = SecurityIncident::whereIn('status', [
            SecurityIncident::STATUS_RESOLVED,
            SecurityIncident::STATUS_CLOSED,
        ])->whereNull('capa')->count();

        return [
            'status' => ($open === 0 && $withoutCapa === 0) ? 'HEALTHY' : 'ATTENTION',
            'open_incidents' => $open,
            'contained' => $contained,
            'resolved' => $resolved,
            'without_capa' => $withoutCapa,
            'discrepancy_count' => $open + $withoutCapa,
        ];
    }
}
