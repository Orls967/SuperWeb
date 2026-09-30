<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\Actions\VerifyPinAction;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Mall\Application\Services\MallLedgerAccounts;
use Modules\Mall\Domain\Enums\MemberStatus;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Mall\Domain\Models\ParkingMember;
use Modules\Mall\Domain\Models\Property;

/**
 * Pendaftaran langganan parkir bulanan. Kendaraan dipilih dari My Garage
 * (core_vehicles) supaya plat nomor selalu sinkron dengan registri kendaraan.
 */
class RegisterParkingMemberAction
{
    public const DEFAULT_MONTHLY_PRICE = 150_000;

    public const MEMBERSHIP_REVENUE_ACCOUNT = 'revenue:mall:membership:IDR';

    public function __construct(
        protected Ledger $ledger,
        protected VerifyPinAction $verifyPinAction,
        protected MallLedgerAccounts $accounts,
    ) {}

    public function execute(
        Property $property,
        User $user,
        Vehicle $vehicle,
        string $pin,
        int $months = 1,
        ?int $monthlyPrice = null,
        bool $autoRenew = true,
    ): ParkingMember {
        if ($months < 1) {
            throw new InvalidArgumentException('Durasi keanggotaan minimal 1 bulan.');
        }

        if ($vehicle->user_id !== $user->id) {
            throw new InvalidArgumentException('Kendaraan yang dipilih bukan milik akun ini.');
        }

        if (blank($vehicle->plate_number)) {
            throw new InvalidArgumentException('Kendaraan belum memiliki plat nomor, lengkapi dulu di My Garage.');
        }

        $this->verifyPinAction->execute($user, $pin);

        $price = $monthlyPrice ?? self::DEFAULT_MONTHLY_PRICE;
        $totalPrice = $price * $months;
        $plate = CheckInVehicleAction::normalizePlate((string) $vehicle->plate_number);

        return DB::transaction(function () use ($property, $user, $vehicle, $plate, $price, $totalPrice, $months, $autoRenew) {
            $existing = ParkingMember::query()
                ->lockForUpdate()
                ->where('property_id', $property->id)
                ->where('plate_number', $plate)
                ->where('status', MemberStatus::ACTIVE)
                ->first();

            if ($existing !== null && $existing->isValid()) {
                throw new InvalidArgumentException(
                    "Plat {$plate} sudah memiliki keanggotaan parkir aktif sampai {$existing->end_date->format('d/m/Y')}."
                );
            }

            $walletAccount = $user->walletAccount('IDR');
            $balance = BigDecimal::of($walletAccount->cached_balance ?: '0')->toInt();

            if ($balance < $totalPrice) {
                throw new InvalidArgumentException(
                    'Saldo dompet tidak mencukupi (Tersedia: Rp '.number_format($balance, 0, ',', '.').
                    ', Diperlukan: Rp '.number_format($totalPrice, 0, ',', '.').').'
                );
            }

            $this->accounts->ensure(self::MEMBERSHIP_REVENUE_ACCOUNT);

            $startDate = Carbon::today();
            $endDate = $startDate->copy()->addMonths($months)->subDay();
            $memberNumber = 'MBR-'.strtoupper(Str::random(8));

            $member = ParkingMember::create([
                'property_id' => $property->id,
                'user_id' => $user->id,
                'vehicle_id' => $vehicle->id,
                'member_number' => $memberNumber,
                'plate_number' => $plate,
                'vehicle_type' => VehicleType::CAR,
                'monthly_price' => $price,
                'auto_renew' => $autoRenew,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => MemberStatus::ACTIVE,
                'notes' => "Langganan {$months} bulan untuk kendaraan {$plate}",
            ]);

            $this->ledger->post(new PostingDTO(
                type: TransactionType::PAYMENT->value,
                description: "Langganan parkir bulanan {$memberNumber} ({$plate}, {$months} bulan)",
                idempotencyKey: 'mall:parking:member:'.$member->uuid,
                entries: [
                    PostingEntryDTO::forAccount($walletAccount->id, 'IDR', BigDecimal::of($totalPrice)->negated()),
                    PostingEntryDTO::forCode(self::MEMBERSHIP_REVENUE_ACCOUNT, 'IDR', BigDecimal::of($totalPrice)),
                ],
                referenceType: 'mall_parking_member',
                referenceId: $member->id,
                meta: [
                    'member_number' => $memberNumber,
                    'plate_number' => $plate,
                    'months' => $months,
                    'monthly_price' => $price,
                ],
                createdBy: $user->id,
                postedAt: now(),
            ));

            return $member->fresh();
        }, attempts: 3);
    }
}
