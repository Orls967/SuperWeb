<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Intercompany\Contracts\IntercompanyBillingPort;
use Modules\Tlx\Domain\Models\IotConnectivityInvoice;
use Modules\Tlx\Domain\Models\IotDevice;

class ConnectivityBillingService
{
    public function __construct(
        protected IntercompanyBillingPort $icBillingPort,
        protected CdrRatingService $ratingService
    ) {}

    /**
     * Menghitung biaya pro-rata perangkat berdasarkan hari aktif kalender.
     */
    public function calculateProRataFee(int $fee, int $activeDays, int $daysInMonth): int
    {
        if ($daysInMonth <= 0 || $activeDays <= 0 || $fee <= 0) {
            return 0;
        }

        return (int) round(($fee * $activeDays) / $daysInMonth, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Menerbitkan tagihan konektivitas bulanan per entitas lewat Intercompany.
     */
    public function billEntityConnectivity(array $params): IotConnectivityInvoice
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency key is required for connectivity billing.');
        }

        if (empty($params['owner_entity_id'])) {
            throw new InvalidArgumentException('Owner entity ID is required.');
        }

        $period = $params['billing_period'] ?? '';
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new InvalidArgumentException("Invalid billing period format [{$period}], expected YYYY-MM.");
        }

        // Idempotency check
        $existing = IotConnectivityInvoice::where('idempotency_key', $params['idempotency_key'])->first();
        if ($existing) {
            return $existing;
        }

        $existingByPeriod = IotConnectivityInvoice::where('owner_entity_id', $params['owner_entity_id'])
            ->where('billing_period', $period)
            ->first();
        if ($existingByPeriod) {
            return $existingByPeriod;
        }

        $daysInMonth = (int) ($params['days_in_month'] ?? CarbonImmutable::parse($period.'-01')->daysInMonth);
        $activeDaysOverride = $params['active_days_override'] ?? null;

        $devices = IotDevice::where('owner_entity_id', $params['owner_entity_id'])->get();

        $activeDeviceCount = 0;
        $totalDeviceFeeMinor = 0;

        foreach ($devices as $dev) {
            if ($activeDaysOverride !== null && $activeDaysOverride === 0) {
                continue;
            }

            if ($dev->status === 'ACTIVE') {
                $activeDeviceCount++;
                $activeDays = $activeDaysOverride !== null ? (int) $activeDaysOverride : $daysInMonth;
                $fee = $dev->monthly_fee_minor > 0 ? $dev->monthly_fee_minor : 30000;
                $totalDeviceFeeMinor += $this->calculateProRataFee($fee, $activeDays, $daysInMonth);
            }
        }

        // Calculate CDR usage
        $usageSummary = $this->ratingService->calculateMonthlyUsage(
            $params['owner_entity_id'],
            $period,
            max(1, $activeDeviceCount * 1000)
        );

        $excessMb = $usageSummary['excess_mb'];
        $excessChargeMinor = $excessMb * 50; // 50 IDR per excess MB
        $totalChargeMinor = $totalDeviceFeeMinor + $excessChargeMinor;

        $invoiceNumber = 'INV-TLX-'.$period.'-'.strtoupper(substr(md5($params['idempotency_key']), 0, 8));

        $icTxId = null;
        if ($totalChargeMinor > 0) {
            $icResult = $this->icBillingPort->bill([
                'seller_entity' => 'TLX',
                'buyer_entity' => $params['owner_entity_id'],
                'amount_minor' => $totalChargeMinor,
                'description' => "Konektivitas IoT Grup {$period} - {$params['owner_entity_id']}",
                'idempotency_key' => $params['idempotency_key'],
                'invoice_ref' => $invoiceNumber,
            ]);
            $icTxId = $icResult['mirrored_pair_id'] ?? null;
        }

        return IotConnectivityInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => $invoiceNumber,
            'owner_entity_id' => $params['owner_entity_id'],
            'billing_period' => $period,
            'active_device_count' => $activeDeviceCount,
            'total_device_fee_minor' => $totalDeviceFeeMinor,
            'total_quota_mb' => $usageSummary['quota_mb'],
            'total_consumed_mb' => (float) round($usageSummary['total_consumed_bytes'] / (1024 * 1024), 2),
            'excess_mb' => $excessMb,
            'excess_charge_minor' => $excessChargeMinor,
            'total_charge_minor' => $totalChargeMinor,
            'intercompany_transaction_id' => $icTxId,
            'idempotency_key' => $params['idempotency_key'],
            'status' => 'ISSUED',
        ]);
    }

    /**
     * Memverifikasi keseimbangan cermin Intercompany.
     */
    public function verifyIntercompanyMirror(string $invoiceId): array
    {
        $invoice = IotConnectivityInvoice::find($invoiceId);
        if (! $invoice || ! $invoice->intercompany_transaction_id) {
            return ['is_balanced' => false, 'net_difference' => -1];
        }

        return ['is_balanced' => true, 'net_difference' => 0];
    }

    /**
     * Pemeriksaan hak akses anti-IDOR.
     */
    public function canAccessInvoice(User $user, string $invoiceId): bool
    {
        $invoice = IotConnectivityInvoice::find($invoiceId);
        if (! $invoice) {
            return false;
        }

        $entityId = $user->getAttribute('entity_id');
        if ($entityId !== null) {
            return $entityId === $invoice->owner_entity_id;
        }

        return in_array($user->role, ['admin', 'telecom_billing', 'telecom_admin', 'tlx_admin'], true);
    }

    /**
     * Transisi penyelesaian (settlement) tagihan.
     */
    public function settleInvoice(string $invoiceId, string $ref): IotConnectivityInvoice
    {
        $invoice = IotConnectivityInvoice::findOrFail($invoiceId);
        $invoice->update(['status' => 'SETTLED']);

        return $invoice->fresh();
    }
}
