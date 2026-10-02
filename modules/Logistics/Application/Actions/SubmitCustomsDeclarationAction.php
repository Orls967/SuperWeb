<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\ExceptionType;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\CustomsException;
use Modules\Logistics\Domain\Models\CustomsDeclaration;
use Modules\Logistics\Domain\Models\HsTariff;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Services\CustomsDutyCalculator;

class SubmitCustomsDeclarationAction
{
    public function __construct(
        private readonly CustomsDutyCalculator $calculator,
        private readonly RecordTrackingEventAction $recordEvent,
        private readonly RaiseShipmentExceptionAction $raiseException
    ) {}

    /**
     * Alur 13 (langkah 1): ajukan PIB (impor) / PEB (ekspor), hitung simulasi bea masuk, PPN, PPh 22.
     * Jalur merah (HS lartas atau nilai >= ambang) menahan resi di status CustomsHold.
     *
     * @param  array<int, array{hs_code: string, value_idr: int}>  $lines
     */
    public function execute(User $user, Shipment $shipment, string $type, array $lines, bool $hasApi = false): CustomsDeclaration
    {
        $isStaff = $user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher();
        if (! $isStaff && $user->id !== $shipment->shipper_id) {
            throw CustomsException::forbidden('mengajukan dokumen kepabeanan untuk resi ini');
        }

        $type = strtoupper($type);
        if (! in_array($type, ['PIB', 'PEB'], true)) {
            throw new CustomsException('Jenis dokumen harus PIB atau PEB.');
        }

        $clean = [];
        foreach ($lines as $line) {
            $hs = preg_replace('/\D/', '', (string) ($line['hs_code'] ?? ''));
            $value = (int) ($line['value_idr'] ?? 0);
            if (strlen($hs) !== 8 || $value <= 0) {
                throw CustomsException::invalidLines();
            }
            $clean[] = ['hs_code' => $hs, 'value_idr' => $value];
        }
        if ($clean === []) {
            throw CustomsException::invalidLines();
        }

        $tariffs = HsTariff::whereIn('hs_code', array_column($clean, 'hs_code'))->where('is_active', true)->get()->keyBy('hs_code');

        return DB::transaction(function () use ($user, $shipment, $type, $clean, $hasApi, $tariffs) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($shipment->status, [ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::AtHub], true)) {
                throw CustomsException::shipmentNotEligible($shipment->tracking_number, $shipment->status->label());
            }

            if (CustomsDeclaration::where('shipment_id', $shipment->id)->where('type', $type)->where('status', '!=', CustomsDeclaration::STATUS_CLEARED)->exists()) {
                throw CustomsException::duplicate($shipment->tracking_number, $type);
            }

            $bm = $ppn = $pph = $value = 0;
            $inspect = false;
            $detail = [];

            foreach ($clean as $line) {
                $tariff = $tariffs[$line['hs_code']] ?? throw CustomsException::unknownHs($line['hs_code']);
                $duty = $type === 'PIB' ? $this->calculator->forLine($line['value_idr'], $tariff, $hasApi) : ['bm' => 0, 'ppn' => 0, 'pph22' => 0, 'total' => 0];

                $bm += $duty['bm'];
                $ppn += $duty['ppn'];
                $pph += $duty['pph22'];
                $value += $line['value_idr'];
                $inspect = $inspect || $tariff->requires_inspection;
                $detail[] = $line + ['description' => $tariff->description, 'bm_idr' => $duty['bm'], 'ppn_idr' => $duty['ppn'], 'pph22_idr' => $duty['pph22']];
            }

            $threshold = (int) config('logistics.customs_red_lane_threshold_idr', 500_000_000);
            $red = $inspect || $value >= $threshold;
            $reason = $red ? ($inspect ? 'Barang larangan/pembatasan (lartas): pemeriksaan fisik.' : 'Nilai pabean melebihi ambang jalur merah.') : null;

            $declaration = CustomsDeclaration::create([
                'declaration_number' => 'TMP',
                'shipment_id' => $shipment->id,
                'type' => $type,
                'has_api' => $hasApi,
                'status' => $red ? CustomsDeclaration::STATUS_ON_HOLD : CustomsDeclaration::STATUS_SUBMITTED,
                'lane' => $red ? 'red' : 'green',
                'lines' => $detail,
                'customs_value_idr' => $value,
                'bm_idr' => $bm,
                'ppn_idr' => $ppn,
                'pph22_idr' => $pph,
                'total_duty_idr' => $bm + $ppn + $pph,
                'hold_reason' => $reason,
                'previous_shipment_status' => $red ? $shipment->status->value : null,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ]);
            $declaration->update(['declaration_number' => sprintf('%s-%s-%05d', $type, now()->format('Ym'), $declaration->id)]);

            if ($red) {
                if ($shipment->status->canTransitionTo(ShipmentStatus::CustomsHold)) {
                    $shipment->transitionTo(ShipmentStatus::CustomsHold);
                }

                $this->recordEvent->execute(
                    shipment: $shipment,
                    eventType: 'CUSTOMS_HOLD',
                    actor: $user,
                    description: 'Kargo ditahan untuk pemeriksaan bea cukai (jalur merah).',
                    payload: ['declaration_id' => $declaration->id, 'type' => $type]
                );

                $this->raiseException->execute(
                    shipment: $shipment,
                    type: ExceptionType::CustomsHold,
                    description: "{$declaration->declaration_number}: {$reason}",
                    reporter: $user,
                    dedupeKey: "customs_hold:{$declaration->id}",
                    payload: ['declaration_id' => $declaration->id],
                );
            }

            return $declaration;
        });
    }
}
