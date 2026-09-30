<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\VerifiesWalletPin;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Mall\Application\Services\MallLedgerAccounts;
use Modules\Mall\Domain\Enums\ParkingPaymentMethod;
use Modules\Mall\Domain\Enums\ParkingPaymentStatus;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Exceptions\InvalidParkingTicketException;
use Modules\Mall\Domain\Exceptions\TicketAlreadySettledException;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Payment\Contracts\PaymentGateway;

class SettleParkingSessionAction
{
    public function __construct(
        protected Ledger $ledger,
        protected PaymentGateway $paymentGateway,
        protected VerifiesWalletPin $verifyPinAction,
        protected MallLedgerAccounts $accounts,
        protected CheckOutVehicleAction $checkOutAction,
    ) {}

    /**
     * Terima pembayaran tarif parkir di gate keluar lalu buka palang.
     *
     * @throws TicketAlreadySettledException
     * @throws InvalidParkingTicketException
     */
    public function execute(
        ParkingSession $session,
        ParkingPaymentMethod $method,
        ?User $payer = null,
        ?string $pin = null,
        ?int $cashTendered = null,
        ?string $idempotencyKey = null
    ): ParkingSession {
        if ($session->exit_time === null) {
            throw new InvalidParkingTicketException(
                "Tiket {$session->ticket_number} belum dihitung di gate keluar. Jalankan proses keluar terlebih dahulu."
            );
        }

        $idemKey = $idempotencyKey ?? 'mall:parking:settle:'.$session->uuid;

        return DB::transaction(function () use ($session, $method, $payer, $pin, $cashTendered, $idemKey) {
            /** @var ParkingSession $locked */
            $locked = ParkingSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($locked->status === ParkingSessionStatus::COMPLETED || $locked->isPaid()) {
                throw new TicketAlreadySettledException(
                    "Tiket {$locked->ticket_number} sudah lunas dan palang keluar sudah dibuka."
                );
            }

            if ($locked->total_fee <= 0) {
                throw new InvalidParkingTicketException(
                    "Tiket {$locked->ticket_number} tidak memiliki tarif yang harus dibayar."
                );
            }

            match ($method) {
                ParkingPaymentMethod::CASH => $this->payWithCash($locked, $cashTendered, $idemKey, $payer),
                ParkingPaymentMethod::WALLET => $this->payWithWallet($locked, $payer, $pin, $idemKey),
                default => throw new InvalidParkingTicketException(
                    "Metode pembayaran {$method->label()} tidak dapat dipakai untuk melunasi tarif parkir."
                ),
            };

            $locked->refresh();
            $locked->status = ParkingSessionStatus::COMPLETED;
            $locked->payment_status = ParkingPaymentStatus::PAID;
            $locked->payment_method = $method;
            $locked->paid_at = now();
            $locked->save();

            $this->checkOutAction->releaseSlot($locked);

            return $locked->fresh(['zone', 'member']);
        }, attempts: 3);
    }

    /**
     * Tunai di gate: kas fisik gate bertambah, pendapatan parkir diakui.
     */
    private function payWithCash(ParkingSession $session, ?int $cashTendered, string $idemKey, ?User $operator): void
    {
        $tendered = $cashTendered ?? $session->total_fee;

        if ($tendered < $session->total_fee) {
            throw new InvalidParkingTicketException(
                'Uang tunai diterima (Rp '.number_format($tendered, 0, ',', '.').
                ') kurang dari tarif parkir (Rp '.number_format($session->total_fee, 0, ',', '.').').'
            );
        }

        $this->accounts->ensure(MallLedgerAccounts::PARKING_CASH);
        $this->accounts->ensure(MallLedgerAccounts::PARKING_REVENUE);

        $this->ledger->post(new PostingDTO(
            type: TransactionType::PARKING->value,
            description: "Parkir tunai {$session->ticket_number} ({$session->plate_number})",
            idempotencyKey: $idemKey.':cash',
            entries: [
                PostingEntryDTO::forCode(
                    MallLedgerAccounts::PARKING_CASH,
                    'IDR',
                    BigDecimal::of($session->total_fee)->negated()
                ),
                PostingEntryDTO::forCode(
                    MallLedgerAccounts::PARKING_REVENUE,
                    'IDR',
                    BigDecimal::of($session->total_fee)
                ),
            ],
            referenceType: 'mall_parking_session',
            referenceId: $session->id,
            meta: [
                'ticket_number' => $session->ticket_number,
                'plate_number' => $session->plate_number,
                'duration_minutes' => $session->duration_minutes,
                'cash_tendered' => $tendered,
                'change_given' => $tendered - $session->total_fee,
            ],
            createdBy: $operator?->id,
            postedAt: now(),
        ));

        $session->payment_status = ParkingPaymentStatus::PAID;
        $session->save();
    }

    /**
     * Dompet digital: lewat Payment Hub supaya idempotensi & intent tercatat.
     */
    private function payWithWallet(ParkingSession $session, ?User $payer, ?string $pin, string $idemKey): void
    {
        if ($payer === null) {
            throw new InvalidParkingTicketException('Pengguna pembayar wajib dipilih untuk pembayaran dompet.');
        }

        if ($pin !== null) {
            $this->verifyPinAction->execute($payer, $pin);
        }

        $this->accounts->ensure(MallLedgerAccounts::PARKING_REVENUE);

        $session->paid_by_user_id = $payer->id;
        $session->save();

        $this->paymentGateway->charge($session, $idemKey.':wallet');
    }
}
