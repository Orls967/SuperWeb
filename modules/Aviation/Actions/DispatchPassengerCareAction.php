<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Algorithms\PassengerCareObligationCalculator;
use Modules\Aviation\Domain\Models\AviationCareVoucher;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;
use Modules\Aviation\Domain\Models\AviationDisruptionPassenger;
use Modules\Aviation\Domain\StateMachines\DisruptionCaseStateMachine;

class DispatchPassengerCareAction
{
    public function __construct(
        protected PassengerCareObligationCalculator $calculator,
        protected DisruptionCaseStateMachine $stateMachine
    ) {}

    /**
     * Terbitkan paket voucer kompensasi dan kepedulian bagi seluruh penumpang pada kasus disrupsi.
     *
     * @return array{case_id: int, vouchers_dispatched: int, status: string}
     */
    public function execute(int $disruptionCaseId): array
    {
        return DB::transaction(function () use ($disruptionCaseId) {
            $case = AviationDisruptionCase::lockForUpdate()->findOrFail($disruptionCaseId);

            if ($case->status === DisruptionCaseStateMachine::STATUS_CLOSED) {
                throw new DomainException('Kasus disrupsi telah ditutup, voucer tidak dapat diterbitkan lagi.');
            }

            $eval = $this->calculator->evaluateObligation($case->delay_minutes, $case->incident_category);

            if (! $eval['duty_of_care_required'] && ! $eval['cash_compensation_required']) {
                return [
                    'case_id' => $case->id,
                    'vouchers_dispatched' => 0,
                    'status' => $case->status,
                ];
            }

            $passengers = AviationDisruptionPassenger::where('disruption_case_id', $case->id)->get();
            $voucherCount = 0;
            $now = Carbon::now();
            $expiry = (clone $now)->addHours(24);

            foreach ($passengers as $pax) {
                // 1. Voucher Konsumsi (Refreshment / Snack / Meal)
                if (! empty($eval['voucher_type'])) {
                    $voucherCode = sprintf('VCR-%s-%s-%s', strtoupper($eval['voucher_type']), date('Ymd'), strtoupper(substr(uniqid('', true), -6)));

                    AviationCareVoucher::create([
                        'disruption_passenger_id' => $pax->id,
                        'voucher_code' => $voucherCode,
                        'voucher_type' => $eval['voucher_type'],
                        'nominal_amount_minor_unit' => $eval['voucher_nominal_minor_unit'],
                        'currency' => 'IDR',
                        'is_redeemed' => false,
                        'expires_at' => $expiry,
                    ]);
                    $voucherCount++;
                }

                // 2. Voucher Kompensasi Tunai PM 89 (Rp300.000,-) jika berlaku
                if ($eval['cash_compensation_required']) {
                    $cashCode = sprintf('VCR-CASH-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));

                    AviationCareVoucher::create([
                        'disruption_passenger_id' => $pax->id,
                        'voucher_code' => $cashCode,
                        'voucher_type' => 'cash_compensation',
                        'nominal_amount_minor_unit' => $eval['cash_compensation_amount_minor_unit'],
                        'currency' => 'IDR',
                        'is_redeemed' => false,
                        'expires_at' => $expiry,
                    ]);
                    $voucherCount++;
                }
            }

            // Update status state machine kasus
            if ($this->stateMachine->canTransition($case->status, DisruptionCaseStateMachine::STATUS_CARE_DISPATCHED)) {
                $case->status = $this->stateMachine->transition($case->status, DisruptionCaseStateMachine::STATUS_CARE_DISPATCHED);
                $case->save();
            }

            return [
                'case_id' => $case->id,
                'vouchers_dispatched' => $voucherCount,
                'status' => $case->status,
            ];
        });
    }
}
