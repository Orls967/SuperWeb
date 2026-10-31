<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Tlx\Domain\Models\ColocationContract;
use Modules\Tlx\Domain\Models\ColocationInvoice;
use Modules\Tlx\Domain\Models\DcPowerReading;
use Modules\Tlx\Domain\Models\TelecomDataCenter;

class ColocationBillingService
{
    /**
     * Mengalokasikan kapasitas rak data center dengan validasi batas kapasitas.
     */
    public function allocateRack(array $params): array
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        if (empty($params['dc_id'])) {
            throw new InvalidArgumentException('Data center ID is required.');
        }

        $allocatedUnits = (int) ($params['rack_units_allocated'] ?? 0);
        if ($allocatedUnits <= 0) {
            throw new InvalidArgumentException('Rack units allocated must be positive.');
        }

        // Idempotency check
        $existing = ColocationContract::where('idempotency_key', $params['idempotency_key'])->first();
        if ($existing) {
            return $existing->toArray();
        }

        return DB::transaction(function () use ($params, $allocatedUnits) {
            $dc = TelecomDataCenter::where('id', $params['dc_id'])->lockForUpdate()->first();
            if (! $dc) {
                throw new InvalidArgumentException("Data center [{$params['dc_id']}] not found.");
            }

            if ($dc->occupied_racks + $allocatedUnits > $dc->total_racks_capacity) {
                throw new InvalidArgumentException(
                    "Insufficient rack capacity in DC [{$dc->dc_code}]. Available: ".
                    ($dc->total_racks_capacity - $dc->occupied_racks).", Requested: {$allocatedUnits}."
                );
            }

            $dc->increment('occupied_racks', $allocatedUnits);

            $contractNumber = 'COLO-'.strtoupper(substr(md5($params['idempotency_key']), 0, 10));

            $contract = ColocationContract::create([
                'id' => (string) Str::uuid(),
                'contract_number' => $contractNumber,
                'tenant_party_id' => $params['tenant_party_id'],
                'dc_id' => $dc->id,
                'rack_units_allocated' => $allocatedUnits,
                'power_commit_kw' => (int) ($params['power_commit_kw'] ?? 0),
                'monthly_rack_fee_minor' => (int) ($params['monthly_rack_fee_minor'] ?? 0),
                'power_rate_per_kwh_minor' => (int) ($params['power_rate_per_kwh_minor'] ?? 0),
                'excess_power_rate_per_kwh_minor' => (int) ($params['excess_power_rate_per_kwh_minor'] ?? 0),
                'port_status' => 'UP',
                'is_overdue' => false,
                'idempotency_key' => $params['idempotency_key'],
            ]);

            return $contract->fresh()->toArray();
        });
    }

    /**
     * Mencatat pembacaan daya fasilitas dan peralatan IT (append-only).
     */
    public function recordPowerReading(array $params): DcPowerReading
    {
        if (empty($params['dc_id'])) {
            throw new InvalidArgumentException('Data center ID is required.');
        }

        $facilityEnergy = (int) ($params['total_facility_energy_kwh'] ?? 0);
        $itEnergy = (int) ($params['it_equipment_energy_kwh'] ?? 0);

        if ($facilityEnergy < 0 || $itEnergy < 0) {
            throw new InvalidArgumentException('Energy readings cannot be negative.');
        }

        return DcPowerReading::create([
            'id' => (string) Str::uuid(),
            'dc_id' => $params['dc_id'],
            'reading_at' => $params['reading_at'] ?? now(),
            'total_facility_energy_kwh' => $facilityEnergy,
            'it_equipment_energy_kwh' => $itEnergy,
            'peak_power_kw' => (int) ($params['peak_power_kw'] ?? 0),
        ]);
    }

    /**
     * Menghitung PUE dinamis dari data terukur (bukan konstanta).
     */
    public function calculateDynamicPue(string $dcId, string $period): float
    {
        $startOfMonth = CarbonImmutable::parse($period.'-01')->startOfMonth();
        $endOfMonth = $startOfMonth->endOfMonth();

        $readings = DcPowerReading::where('dc_id', $dcId)
            ->whereBetween('reading_at', [$startOfMonth, $endOfMonth])
            ->get();

        if ($readings->isEmpty()) {
            // Check all readings for this DC if period empty
            $readings = DcPowerReading::where('dc_id', $dcId)->get();
        }

        $totalFacility = $readings->sum('total_facility_energy_kwh');
        $totalIt = $readings->sum('it_equipment_energy_kwh');

        if ($totalIt <= 0) {
            return 1.0;
        }

        return (float) round($totalFacility / $totalIt, 2);
    }

    /**
     * Memeriksa SLA efisiensi PUE data center.
     */
    public function checkPueSla(string $dcId, float $slaThreshold): array
    {
        $dc = TelecomDataCenter::findOrFail($dcId);
        $pue = $this->calculateDynamicPue($dcId, now()->format('Y-m'));

        $isBreached = $pue > $slaThreshold;

        return [
            'is_breached' => $isBreached,
            'measured_pue' => $pue,
            'threshold' => $slaThreshold,
        ];
    }

    /**
     * Menerbitkan tagihan colocation bulanan (rak + daya komit + kelebihan puncak).
     */
    public function billColocationContract(array $params): ColocationInvoice
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency key is required.');
        }

        if (empty($params['contract_id'])) {
            throw new InvalidArgumentException('Contract ID is required.');
        }

        $period = $params['billing_period'] ?? now()->format('Y-m');

        $existing = ColocationInvoice::where('idempotency_key', $params['idempotency_key'])->first();
        if ($existing) {
            return $existing;
        }

        $existingByPeriod = ColocationInvoice::where('contract_id', $params['contract_id'])
            ->where('billing_period', $period)
            ->first();
        if ($existingByPeriod) {
            return $existingByPeriod;
        }

        $contract = ColocationContract::findOrFail($params['contract_id']);

        $rackFee = $contract->monthly_rack_fee_minor;
        $committedPowerCharge = 0; // included in base or separate

        $measuredPeakKw = (int) ($params['measured_peak_kw'] ?? 0);
        $excessKw = max(0, $measuredPeakKw - $contract->power_commit_kw);
        $excessPowerCharge = $excessKw * $contract->excess_power_rate_per_kwh_minor;

        $totalMinor = $rackFee + $committedPowerCharge + $excessPowerCharge;
        $invoiceNumber = 'INV-COLO-'.$period.'-'.strtoupper(substr(md5($params['idempotency_key']), 0, 8));

        return ColocationInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => $invoiceNumber,
            'contract_id' => $contract->id,
            'tenant_party_id' => $contract->tenant_party_id,
            'billing_period' => $period,
            'rack_fee_minor' => $rackFee,
            'committed_power_charge_minor' => $committedPowerCharge,
            'excess_power_charge_minor' => $excessPowerCharge,
            'total_minor' => $totalMinor,
            'idempotency_key' => $params['idempotency_key'],
            'status' => 'ISSUED',
        ]);
    }

    /**
     * Memeriksa hak akses anti-IDOR untuk kontrak tenant.
     */
    public function canAccessContract(User $user, string $contractId): bool
    {
        $contract = ColocationContract::find($contractId);
        if (! $contract) {
            return false;
        }

        $entityId = $user->getAttribute('entity_id');
        if ($entityId !== null) {
            return $entityId === $contract->tenant_party_id;
        }

        return in_array($user->role, ['admin', 'datacenter_ops', 'telecom_admin', 'tlx_admin'], true);
    }

    /**
     * Menangguhkan port colocation ketika tagihan overdue.
     */
    public function suspendOverdueContract(string $contractId, string $reason, string $idempKey): ColocationContract
    {
        $contract = ColocationContract::findOrFail($contractId);
        $contract->update([
            'port_status' => 'SUSPENDED',
            'is_overdue' => true,
        ]);

        return $contract->fresh();
    }
}
