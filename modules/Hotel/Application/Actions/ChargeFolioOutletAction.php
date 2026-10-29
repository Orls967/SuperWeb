<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Shared\Application\BaseAction;

class ChargeFolioOutletAction extends BaseAction
{
    public function __construct(
        private readonly HotelBusinessDateService $businessDateService,
        private readonly ?Ledger $ledger = null,
    ) {}

    /**
     * Membebankan outlet/F&B ke folio tamu in-house dengan perhitungan service charge & pajak bertingkat.
     */
    public function execute(
        string $reservationOrFolioId,
        int $baseAmountIdr,
        string $description,
        string $idempotencyKey,
        string $lineType = 'FNB_OUTLET',
    ): HotelFolioLine {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency-Key diperlukan untuk pembebanan folio.');
        }

        return DB::transaction(function () use (
            $reservationOrFolioId,
            $baseAmountIdr,
            $description,
            $idempotencyKey,
            $lineType,
        ) {
            // Temukan folio berdasarkan folio_id atau reservation_id
            $folio = HotelFolio::where('id', $reservationOrFolioId)
                ->orWhere('reservation_id', $reservationOrFolioId)
                ->first();

            if (! $folio) {
                // Mungkin yang dipassing adalah reservation_id
                $res = HotelReservation::find($reservationOrFolioId);
                if ($res) {
                    $folio = HotelFolio::where('reservation_id', $res->id)->first();
                }
            }

            if (! $folio) {
                // Cek apakah reservasi ada tapi belum punya folio
                $res = HotelReservation::find($reservationOrFolioId);
                if ($res && $res->status !== 'checked_in') {
                    throw new \DomainException("Pembebanan outlet ditolak: Tamu harus berstatus 'checked_in' (status saat ini: {$res->status}).");
                }
                throw new \DomainException("Folio tidak ditemukan untuk ID: {$reservationOrFolioId}.");
            }

            $reservation = HotelReservation::with('property')->findOrFail($folio->reservation_id);

            // Cek status reservasi: hanya tamu in-house (checked_in) yang boleh dibebankan
            if ($reservation->status !== 'checked_in') {
                throw new \DomainException("Pembebanan outlet ditolak: Tamu harus berstatus 'checked_in' (status saat ini: {$reservation->status}).");
            }

            // Cek idempotensi
            $existing = HotelFolioLine::where('folio_id', $folio->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $property = $reservation->property;
            $serviceChargeBps = $property->service_charge_bps ?? 1000;
            $taxPbjtBps = $property->tax_pbjt_bps ?? 1000;

            // H-3: Service dari tarif dasar, pajak dari (tarif + service). Pembulatan half-up IDR penuh.
            $serviceCharge = (int) round($baseAmountIdr * $serviceChargeBps / 10000, 0, PHP_ROUND_HALF_UP);
            $taxPbjt = (int) round(($baseAmountIdr + $serviceCharge) * $taxPbjtBps / 10000, 0, PHP_ROUND_HALF_UP);
            $totalAmount = $baseAmountIdr + $serviceCharge + $taxPbjt;

            $businessDate = $this->businessDateService->getCurrentBusinessDate($property->id);

            $lineId = (string) Str::uuid();

            if ($this->ledger) {
                $this->ledger->post(new PostingDTO(
                    type: HotelTransactionTypes::HOTEL_FOLIO_OUTLET_CHARGE->value,
                    description: "Pembebanan outlet folio: {$description}",
                    idempotencyKey: "htl:outlet:{$idempotencyKey}",
                    entries: [
                        PostingEntryDTO::forCode('htl:guest_ledger:IDR', 'IDR', -$totalAmount),
                        PostingEntryDTO::forCode('htl:fnb_revenue:IDR', 'IDR', $baseAmountIdr),
                        PostingEntryDTO::forCode('htl:service_charge_payable:IDR', 'IDR', $serviceCharge),
                        PostingEntryDTO::forCode('htl:tax_payable:IDR', 'IDR', $taxPbjt),
                    ],
                    referenceType: 'htl_folio_lines',
                    referenceId: $lineId,
                ));
            }

            $line = HotelFolioLine::create([
                'id' => $lineId,
                'folio_id' => $folio->id,
                'business_date' => $businessDate,
                'line_type' => $lineType,
                'description' => $description,
                'amount_idr' => $totalAmount,
                'base_amount_idr' => $baseAmountIdr,
                'service_charge_idr' => $serviceCharge,
                'tax_pbjt_idr' => $taxPbjt,
                'reversal_of_id' => null,
                'idempotency_key' => $idempotencyKey,
            ]);

            $folio->total_addon_charges += $totalAmount;
            $folio->outstanding_balance += $totalAmount;
            $folio->save();

            return $line;
        });
    }

    /**
     * Membalik pembebanan folio menggunakan baris reversal append-only.
     */
    public function reverseLine(string $folioLineId, string $reason, string $idempotencyKey): HotelFolioLine
    {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency-Key diperlukan untuk reversal folio.');
        }

        return DB::transaction(function () use ($folioLineId, $reason, $idempotencyKey) {
            $originalLine = HotelFolioLine::with('folio')->findOrFail($folioLineId);

            $existing = HotelFolioLine::where('folio_id', $originalLine->folio_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $folio = $originalLine->folio;
            $reservation = HotelReservation::with('property')->findOrFail($folio->reservation_id);
            $businessDate = $this->businessDateService->getCurrentBusinessDate($reservation->property_id);

            $reversalAmount = -$originalLine->amount_idr;
            $reversalBase = -$originalLine->base_amount_idr;
            $reversalService = -$originalLine->service_charge_idr;
            $reversalTax = -$originalLine->tax_pbjt_idr;

            $reversalLineId = (string) Str::uuid();

            if ($this->ledger) {
                $this->ledger->post(new PostingDTO(
                    type: HotelTransactionTypes::HOTEL_FOLIO_OUTLET_CHARGE->value,
                    description: "Reversal pembebanan folio: {$reason}",
                    idempotencyKey: "htl:reversal:{$idempotencyKey}",
                    entries: [
                        PostingEntryDTO::forCode('htl:guest_ledger:IDR', 'IDR', -$reversalAmount),
                        PostingEntryDTO::forCode('htl:fnb_revenue:IDR', 'IDR', $reversalBase),
                        PostingEntryDTO::forCode('htl:service_charge_payable:IDR', 'IDR', $reversalService),
                        PostingEntryDTO::forCode('htl:tax_payable:IDR', 'IDR', $reversalTax),
                    ],
                    referenceType: 'htl_folio_lines',
                    referenceId: $reversalLineId,
                ));
            }

            $reversalLine = HotelFolioLine::create([
                'id' => $reversalLineId,
                'folio_id' => $folio->id,
                'business_date' => $businessDate,
                'line_type' => 'REVERSAL',
                'description' => "Koreksi: {$reason} (Ref: {$originalLine->id})",
                'amount_idr' => $reversalAmount,
                'base_amount_idr' => $reversalBase,
                'service_charge_idr' => $reversalService,
                'tax_pbjt_idr' => $reversalTax,
                'reversal_of_id' => $originalLine->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            $folio->total_addon_charges += $reversalAmount;
            $folio->outstanding_balance += $reversalAmount;
            $folio->save();

            return $reversalLine;
        });
    }
}
