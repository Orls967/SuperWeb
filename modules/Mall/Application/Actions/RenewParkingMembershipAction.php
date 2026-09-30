<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Core\Application\Services\NotificationService;
use Modules\Mall\Application\Services\MallLedgerAccounts;
use Modules\Mall\Domain\Enums\MemberStatus;
use Modules\Mall\Domain\Models\ParkingMember;

/**
 * Perpanjangan langganan parkir: pengingat H-3, lalu debit otomatis dari dompet
 * saat jatuh tempo. Saldo kurang atau auto_renew mati -> keanggotaan kedaluwarsa
 * dan kendaraan kembali dikenakan tarif normal di gate keluar.
 */
class RenewParkingMembershipAction
{
    public const REMINDER_DAYS_BEFORE = 3;

    public function __construct(
        protected Ledger $ledger,
        protected NotificationService $notifications,
        protected MallLedgerAccounts $accounts,
    ) {}

    /**
     * Kirim pengingat H-3 untuk keanggotaan yang akan berakhir.
     *
     * @return int jumlah pengingat terkirim
     */
    public function sendExpiryReminders(?Carbon $today = null): int
    {
        $today = $today ?? Carbon::today();
        $targetDate = $today->copy()->addDays(self::REMINDER_DAYS_BEFORE);

        $members = ParkingMember::query()
            ->where('status', MemberStatus::ACTIVE)
            ->whereDate('end_date', $targetDate->toDateString())
            ->whereNotNull('user_id')
            ->get();

        foreach ($members as $member) {
            $this->notifications->send(
                userId: (int) $member->user_id,
                type: 'mall.parking.membership_expiring',
                title: 'Langganan Parkir Segera Berakhir',
                body: "Keanggotaan parkir {$member->plate_number} berakhir pada {$member->end_date->format('d/m/Y')}. "
                    .($member->auto_renew
                        ? 'Perpanjangan otomatis akan mendebit Rp '.number_format($member->monthly_price, 0, ',', '.').' dari dompetmu.'
                        : 'Perpanjangan otomatis tidak aktif, silakan perpanjang manual.'),
                icon: 'car',
                actionUrl: route('mall.parking.members'),
                actionLabel: 'Kelola Langganan',
                meta: ['member_number' => $member->member_number],
            );
        }

        return $members->count();
    }

    /**
     * Proses semua keanggotaan yang jatuh tempo pada tanggal tertentu.
     *
     * @return array{renewed: int, expired: int}
     */
    public function processDue(?Carbon $today = null): array
    {
        $today = $today ?? Carbon::today();

        $members = ParkingMember::query()
            ->where('status', MemberStatus::ACTIVE)
            ->whereDate('end_date', '<', $today->toDateString())
            ->get();

        $renewed = 0;
        $expired = 0;

        foreach ($members as $member) {
            if ($this->renew($member, $today)) {
                $renewed++;
            } else {
                $expired++;
            }
        }

        return ['renewed' => $renewed, 'expired' => $expired];
    }

    /**
     * Perpanjang satu keanggotaan; false bila gagal dan status menjadi kedaluwarsa.
     */
    public function renew(ParkingMember $member, ?Carbon $today = null): bool
    {
        $today = $today ?? Carbon::today();
        $user = $member->user_id !== null ? User::find($member->user_id) : null;

        if (! $member->auto_renew || $user === null) {
            $this->expire($member, $user, 'Perpanjangan otomatis tidak aktif.');

            return false;
        }

        $walletAccount = $user->walletAccount('IDR');
        $balance = BigDecimal::of($walletAccount->cached_balance ?: '0')->toInt();

        if ($balance < $member->monthly_price) {
            $this->expire($member, $user, 'Saldo dompet tidak mencukupi untuk perpanjangan otomatis.');

            return false;
        }

        $this->accounts->ensure(RegisterParkingMemberAction::MEMBERSHIP_REVENUE_ACCOUNT);

        DB::transaction(function () use ($member, $user, $walletAccount, $today) {
            /** @var ParkingMember $locked */
            $locked = ParkingMember::query()->lockForUpdate()->findOrFail($member->id);

            $newEnd = $today->copy()->addMonth()->subDay();

            $this->ledger->post(new PostingDTO(
                type: TransactionType::PAYMENT->value,
                description: "Perpanjangan langganan parkir {$locked->member_number} ({$locked->plate_number})",
                idempotencyKey: 'mall:parking:renew:'.$locked->uuid.':'.$newEnd->toDateString(),
                entries: [
                    PostingEntryDTO::forAccount($walletAccount->id, 'IDR', BigDecimal::of($locked->monthly_price)->negated()),
                    PostingEntryDTO::forCode(
                        RegisterParkingMemberAction::MEMBERSHIP_REVENUE_ACCOUNT,
                        'IDR',
                        BigDecimal::of($locked->monthly_price)
                    ),
                ],
                referenceType: 'mall_parking_member',
                referenceId: $locked->id,
                meta: [
                    'member_number' => $locked->member_number,
                    'renewed_until' => $newEnd->toDateString(),
                ],
                createdBy: $user->id,
                postedAt: now(),
            ));

            $locked->update([
                'start_date' => $today,
                'end_date' => $newEnd,
                'status' => MemberStatus::ACTIVE,
            ]);
        }, attempts: 3);

        $member->refresh();

        $this->notifications->send(
            userId: (int) $user->id,
            type: 'mall.parking.membership_renewed',
            title: 'Langganan Parkir Diperpanjang',
            body: "Keanggotaan parkir {$member->plate_number} diperpanjang sampai {$member->end_date->format('d/m/Y')} "
                .'(Rp '.number_format($member->monthly_price, 0, ',', '.').').',
            icon: 'car',
            actionUrl: route('mall.parking.members'),
            actionLabel: 'Lihat Langganan',
            meta: ['member_number' => $member->member_number],
        );

        return true;
    }

    private function expire(ParkingMember $member, ?User $user, string $reason): void
    {
        $member->update(['status' => MemberStatus::EXPIRED]);

        if ($user !== null) {
            $this->notifications->send(
                userId: (int) $user->id,
                type: 'mall.parking.membership_expired',
                title: 'Langganan Parkir Berakhir',
                body: "Keanggotaan parkir {$member->plate_number} telah berakhir. {$reason} "
                    .'Kendaraan akan dikenakan tarif parkir normal.',
                icon: 'alert',
                actionUrl: route('mall.parking.members'),
                actionLabel: 'Perpanjang Sekarang',
                meta: ['member_number' => $member->member_number],
            );
        }
    }
}
