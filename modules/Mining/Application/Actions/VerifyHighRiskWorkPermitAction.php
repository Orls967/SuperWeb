<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Mining\Domain\Enums\WorkPermitStatus;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\StateMachines\WorkPermitStateMachine;

class VerifyHighRiskWorkPermitAction
{
    /**
     * Verifikasi keabsahan izin kerja K3 berisiko tinggi (PTW) di lapangan (D94-5).
     * Menolak akses pekerja bila masa berlaku izin telah habis / kedaluwarsa.
     */
    public function execute(string $permitId, ?string $checkTime = null): bool
    {
        $permit = MiningWorkPermit::findOrFail($permitId);
        $time = $checkTime ? CarbonImmutable::parse($checkTime) : CarbonImmutable::now();

        $validUntil = $permit->valid_until ?? $permit->valid_to;

        $currentStatus = $permit->statusEnum();

        if ($validUntil && $time->greaterThan($validUntil)) {
            // Ubah status ke expired bila masih active
            if ($currentStatus === WorkPermitStatus::ACTIVE) {
                try {
                    $sm = new WorkPermitStateMachine($permit);
                    $sm->transitionTo(WorkPermitStatus::EXPIRED, 'Waktu berlaku izin kerja telah terlampaui saat verifikasi pintu gerbang');
                } catch (\Throwable) {
                    $permit->status = WorkPermitStatus::EXPIRED->value;
                    $permit->save();
                }
            }

            throw new DomainException("Izin kerja {$permit->permit_number} telah kedaluwarsa sejak ".($validUntil ? $validUntil->toDateTimeString() : 'waktu yang ditentukan').'.');
        }

        if ($currentStatus !== WorkPermitStatus::ACTIVE) {
            throw new DomainException("Izin kerja {$permit->permit_number} tidak aktif (status: {$currentStatus->value}).");
        }

        return true;
    }
}
