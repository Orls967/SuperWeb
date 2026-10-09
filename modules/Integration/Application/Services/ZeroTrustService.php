<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Integration\Domain\Models\ServiceIdentity;

/**
 * Zero Trust Architecture Service (Fase 144.1)
 *
 * Handles:
 *  - Service identity registration with least-privilege abilities
 *  - mTLS certificate simulation per service
 *  - Access enforcement: caller must have the required ability
 *  - Daily access audit log
 */
class ZeroTrustService
{
    /** All valid abilities across 17 lines */
    private const LINE_ABILITIES = [
        'EGY' => ['egy.meter.read', 'egy.dispatch', 'egy.billing'],
        'TLX' => ['tlx.isp.manage', 'tlx.billing', 'tlx.iot.read'],
        'MED' => ['med.campaign.book', 'med.content.publish', 'med.royalty.settle'],
        'EDU' => ['edu.program.manage', 'edu.cert.issue', 'edu.cohort.enroll'],
        'RET' => ['ret.inventory.manage', 'ret.order.create', 'ret.cashback.issue'],
        'MINE' => ['mine.dispatch', 'mine.weighbridge.record', 'mine.export.approve'],
        'HTL' => ['htl.reservation.manage', 'htl.rate.set', 'htl.housekeeping'],
        'VEN' => ['ven.event.manage', 'ven.ticket.issue', 'ven.resale.approve'],
        'HOS' => ['hos.emr.read', 'hos.emr.write', 'hos.billing'],
        'FIN' => ['fin.loan.approve', 'fin.ledger.post', 'fin.transfer'],
        'HCM' => ['hcm.payroll.run', 'hcm.employee.manage', 'hcm.cert.verify'],
        'LOG' => ['log.shipment.create', 'log.dispatch', 'log.fleet.manage'],
        'MALL' => ['mall.lease.manage', 'mall.parking.manage', 'mall.footfall.read'],
        'PROP' => ['prop.listing.manage', 'prop.rent.collect', 'prop.tenant.manage'],
        'AGR' => ['agr.harvest.record', 'agr.soil.read', 'agr.trade.execute'],
        'EPC' => ['epc.project.manage', 'epc.milestone.approve', 'epc.subcon.pay'],
        'INT' => ['int.orchestrate', 'int.audit', 'int.security.manage'],
    ];

    /**
     * Register or update a service identity with least-privilege abilities.
     */
    public function registerServiceIdentity(array $data): ServiceIdentity
    {
        $identity = ServiceIdentity::firstOrNew(['service_name' => $data['service_name']]);

        $lineCode = strtoupper($data['line_code']);
        $lineAbilities = self::LINE_ABILITIES[$lineCode] ?? [];
        $requestedAbilities = $data['abilities'] ?? $lineAbilities;
        // Enforce least-privilege: only allow abilities from that line
        $grantedAbilities = array_intersect($requestedAbilities, $lineAbilities);

        $certExpiry = Carbon::now()->addYear();

        $identity->fill([
            'line_code' => $lineCode,
            'sanctum_token_hash' => hash('sha512', Str::random(64)),
            'allowed_abilities' => array_values($grantedAbilities),
            'device_trust_level' => $data['device_trust_level'] ?? 'STANDARD',
            'mtls_cert_fingerprint' => hash('sha256', $data['service_name'].':mtls:'.now()->timestamp),
            'cert_issued_at' => now(),
            'cert_expires_at' => $certExpiry,
            'is_active' => true,
        ]);
        $identity->save();

        return $identity;
    }

    /**
     * Enforce access: verify caller service has the required ability.
     * Logs the access attempt.
     */
    public function enforceAccess(string $callerService, string $calleeService, string $ability): bool
    {
        $identity = ServiceIdentity::where('service_name', $callerService)
            ->where('is_active', true)
            ->first();

        $allowed = $identity
            && $identity->isCertValid()
            && $identity->hasAbility($ability);

        $this->logAccess($callerService, $calleeService, $ability, $allowed ? 'ALLOWED' : 'DENIED');

        return $allowed;
    }

    /**
     * Audit daily: return stats on denied access attempts per line.
     */
    public function dailyAccessAudit(Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $logs = \DB::table('sec_access_audit_logs')
            ->whereBetween('accessed_at', [$start, $end])
            ->select('caller_service', 'status', \DB::raw('count(*) as cnt'))
            ->groupBy('caller_service', 'status')
            ->get();

        $denied = $logs->where('status', 'DENIED')->sum('cnt');
        $allowed = $logs->where('status', 'ALLOWED')->sum('cnt');
        $total = $allowed + $denied;

        return [
            'date' => $date->toDateString(),
            'total_attempts' => $total,
            'allowed' => $allowed,
            'denied' => $denied,
            'denial_rate_pct' => $total > 0 ? round($denied / $total * 100, 2) : 0.0,
        ];
    }

    /**
     * Get all registered service identities.
     */
    public function listIdentities(): array
    {
        return ServiceIdentity::all()->map(fn ($id) => [
            'service' => $id->service_name,
            'line' => $id->line_code,
            'abilities' => $id->allowed_abilities,
            'cert_valid' => $id->isCertValid(),
            'cert_expires_at' => $id->cert_expires_at?->toDateString(),
        ])->all();
    }

    private function logAccess(string $caller, string $callee, string $ability, string $status): void
    {
        \DB::table('sec_access_audit_logs')->insert([
            'caller_service' => $caller,
            'callee_service' => $callee,
            'action' => $ability,
            'status' => $status,
            'ability_used' => $ability,
            'source_ip' => request()->ip() ?? '127.0.0.1',
            'context' => json_encode([]),
            'accessed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
