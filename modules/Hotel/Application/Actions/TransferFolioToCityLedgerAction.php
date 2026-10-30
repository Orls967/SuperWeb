<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Services\HotelBusinessDateService;
use Modules\Hotel\Domain\Models\HotelCityLedgerAccount;
use Modules\Hotel\Domain\Models\HotelCityLedgerTransfer;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Shared\Application\BaseAction;

class TransferFolioToCityLedgerAction extends BaseAction
{
    public function __construct(
        private readonly HotelBusinessDateService $businessDateService,
        private readonly ?Ledger $ledger = null,
    ) {}

    /**
     * Mengalihkan sebagian atau seluruh tagihan folio tamu ke akun City Ledger korporat.
     */
    public function execute(
        string $folioId,
        string $cityLedgerAccountIdOrCode,
        int $amountIdr,
        ?string $notes,
        string $idempotencyKey,
    ): HotelCityLedgerTransfer {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency-Key diperlukan untuk pengalihan ke city ledger.');
        }

        if ($amountIdr <= 0) {
            throw new \InvalidArgumentException('Nominal pengalihan city ledger harus lebih besar dari 0.');
        }

        return DB::transaction(function () use (
            $folioId,
            $cityLedgerAccountIdOrCode,
            $amountIdr,
            $notes,
            $idempotencyKey,
        ) {
            $folio = HotelFolio::findOrFail($folioId);

            $account = HotelCityLedgerAccount::where('id', $cityLedgerAccountIdOrCode)
                ->orWhere('company_code', $cityLedgerAccountIdOrCode)
                ->first();

            if (! $account) {
                throw new \DomainException("Akun City Ledger korporat tidak ditemukan untuk: {$cityLedgerAccountIdOrCode}.");
            }

            if (! $account->is_active) {
                throw new \DomainException("Akun City Ledger '{$account->company_name}' sedang dinonaktifkan.");
            }

            // H-6: Batas kredit korporat
            if (! $account->hasSufficientCredit($amountIdr)) {
                $sisaLimit = $account->credit_limit_idr - $account->outstanding_balance_idr;
                throw new \DomainException("Pengalihan ditolak: Batas kredit korporat tidak mencukupi (Sisa limit: {$sisaLimit}, Diminta: {$amountIdr}).");
            }

            // Cek idempotensi
            $existing = HotelCityLedgerTransfer::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $reservation = HotelReservation::findOrFail($folio->reservation_id);
            $businessDate = $this->businessDateService->getCurrentBusinessDate($reservation->property_id);

            $transferId = (string) Str::uuid();

            if ($this->ledger) {
                $this->ledger->post(new PostingDTO(
                    type: HotelTransactionTypes::HOTEL_CITY_LEDGER_TRANSFER->value,
                    description: "Transfer folio ke City Ledger {$account->company_name}",
                    idempotencyKey: "htl:city_ledger:{$idempotencyKey}",
                    entries: [
                        PostingEntryDTO::forCode('htl:city_ledger:IDR', 'IDR', -$amountIdr),
                        PostingEntryDTO::forCode('htl:guest_ledger:IDR', 'IDR', $amountIdr),
                    ],
                    referenceType: 'htl_city_ledger_transfers',
                    referenceId: $transferId,
                ));
            }

            $transfer = HotelCityLedgerTransfer::create([
                'id' => $transferId,
                'city_ledger_account_id' => $account->id,
                'folio_id' => $folio->id,
                'transferred_amount_idr' => $amountIdr,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Tambahkan baris kredit pengurang di folio tamu (append-only)
            HotelFolioLine::create([
                'id' => (string) Str::uuid(),
                'folio_id' => $folio->id,
                'business_date' => $businessDate,
                'line_type' => 'CITY_LEDGER_TRANSFER',
                'description' => "Pengalihan tagihan ke City Ledger: {$account->company_name}",
                'amount_idr' => -$amountIdr,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Perbarui saldo folio dan saldo korporat
            $folio->outstanding_balance -= $amountIdr;
            $folio->save();

            $account->outstanding_balance_idr += $amountIdr;
            $account->save();

            return $transfer;
        });
    }
}
