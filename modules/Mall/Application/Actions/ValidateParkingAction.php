<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mall\Contracts\ParkingValidationResult;
use Modules\Mall\Contracts\ParkingValidator;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Exceptions\InvalidParkingTicketException;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\Tenant;

/**
 * Validasi parkir oleh tenant: tenant menanggung N jam pertama bila pelanggan
 * berbelanja minimal X. Nominalnya tidak hilang dari pendapatan mall, melainkan
 * menjadi piutang tenant yang ditagihkan pada invoice bulanan (lihat
 * GenerateMonthlyInvoicesAction, baris PARKING_VALIDATION).
 */
class ValidateParkingAction implements ParkingValidator
{
    public function validateTicket(string $ticketNumber, string $tenantExternalRef, int $spendAmount): ParkingValidationResult
    {
        $lease = $this->resolveLease($tenantExternalRef);
        $freeHours = $this->freeHoursFor($lease, $spendAmount);

        if ($freeHours <= 0) {
            throw new InvalidParkingTicketException(
                'Belanja Rp '.number_format($spendAmount, 0, ',', '.').
                ' belum mencapai minimal Rp '.number_format((int) $lease->parking_validation_min_spend, 0, ',', '.').
                ' untuk validasi parkir.'
            );
        }

        return DB::transaction(function () use ($ticketNumber, $lease, $freeHours, $spendAmount) {
            $session = ParkingSession::query()
                ->lockForUpdate()
                ->where('ticket_number', $ticketNumber)
                ->where('property_id', $lease->property_id)
                ->first();

            if ($session === null) {
                throw new InvalidParkingTicketException("Tiket parkir {$ticketNumber} tidak ditemukan.");
            }

            if ($session->status !== ParkingSessionStatus::ACTIVE) {
                throw new InvalidParkingTicketException(
                    "Tiket {$ticketNumber} sudah tidak aktif, validasi hanya bisa dilakukan sebelum kendaraan keluar."
                );
            }

            if ($session->validated_by_tenant_id !== null) {
                throw new InvalidParkingTicketException(
                    "Tiket {$ticketNumber} sudah divalidasi sebelumnya dan tidak bisa divalidasi dua kali."
                );
            }

            /** @var Tenant $tenant */
            $tenant = $lease->tenant;

            $session->validated_by_tenant_id = $tenant->id;
            $session->validation_reference = 'LEASE-'.$lease->lease_number;
            $session->validation_free_hours = $freeHours;
            $session->validation_spend_amount = $spendAmount;
            $session->save();

            return new ParkingValidationResult(
                ticketNumber: $session->ticket_number,
                plateNumber: $session->plate_number,
                freeHours: $freeHours,
                tenantName: $tenant->brand_name,
                note: "Validasi parkir {$freeHours} jam oleh {$tenant->brand_name}",
            );
        }, attempts: 3);
    }

    public function eligibleFreeHours(string $tenantExternalRef, int $spendAmount): int
    {
        try {
            $lease = $this->resolveLease($tenantExternalRef);
        } catch (InvalidParkingTicketException) {
            return 0;
        }

        return $this->freeHoursFor($lease, $spendAmount);
    }

    private function freeHoursFor(Lease $lease, int $spendAmount): int
    {
        $hours = (int) $lease->parking_validation_hours;
        $minSpend = (int) $lease->parking_validation_min_spend;

        if ($hours <= 0 || $spendAmount < $minSpend) {
            return 0;
        }

        return $hours;
    }

    /**
     * @throws InvalidParkingTicketException
     */
    private function resolveLease(string $tenantExternalRef): Lease
    {
        $lease = Lease::query()
            ->where('status', LeaseStatus::ACTIVE)
            ->whereHas('tenant', fn ($query) => $query->where('external_ref', $tenantExternalRef))
            ->with('tenant')
            ->first();

        if ($lease === null) {
            throw new InvalidParkingTicketException(
                "Tenant dengan referensi {$tenantExternalRef} tidak memiliki kontrak sewa aktif di Duta Mall."
            );
        }

        return $lease;
    }
}
