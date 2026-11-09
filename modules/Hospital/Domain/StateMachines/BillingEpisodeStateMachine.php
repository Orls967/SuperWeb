<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;
use Modules\Hospital\Domain\Models\BillingEpisode;

class BillingEpisodeStateMachine
{
    public const STATUS_OPEN = 'OPEN';

    public const STATUS_VERIFIED_CODER = 'VERIFIED_CODER';

    public const STATUS_BILLED = 'BILLED';

    public const STATUS_WATERFALL_PENDING = 'WATERFALL_PENDING';

    public const STATUS_SETTLED = 'SETTLED';

    public const STATUS_DISPUTED = 'DISPUTED';

    public const STATUS_REFUNDED = 'REFUNDED';

    public const STATUS_CLOSED = 'CLOSED';

    /**
     * @var array<string, list<string>>
     */
    private const VALID_TRANSITIONS = [
        self::STATUS_OPEN => [
            self::STATUS_VERIFIED_CODER,
            self::STATUS_BILLED,
        ],
        self::STATUS_VERIFIED_CODER => [
            self::STATUS_BILLED,
            self::STATUS_WATERFALL_PENDING,
            self::STATUS_SETTLED,
        ],
        self::STATUS_BILLED => [
            self::STATUS_WATERFALL_PENDING,
            self::STATUS_SETTLED,
            self::STATUS_DISPUTED,
        ],
        self::STATUS_WATERFALL_PENDING => [
            self::STATUS_SETTLED,
            self::STATUS_DISPUTED,
        ],
        self::STATUS_SETTLED => [
            self::STATUS_REFUNDED,
            self::STATUS_CLOSED,
        ],
        self::STATUS_DISPUTED => [
            self::STATUS_BILLED,
            self::STATUS_SETTLED,
            self::STATUS_CLOSED,
        ],
        self::STATUS_REFUNDED => [
            self::STATUS_CLOSED,
        ],
        self::STATUS_CLOSED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    public static function transition(BillingEpisode $episode, string $targetStatus, string $reason = '', ?int $actorId = null): BillingEpisode
    {
        $current = $episode->status;

        if ($current === $targetStatus) {
            return $episode;
        }

        if (! self::canTransition($current, $targetStatus)) {
            throw new DomainException("Transisi status billing episode dari [{$current}] ke [{$targetStatus}] tidak valid.");
        }

        // Invariant: jika menuju SETTLED, pastikan sisa tagihan ter-cover oleh kombinasi waterfall / deposit
        if ($targetStatus === self::STATUS_SETTLED) {
            $totalCovered = $episode->bpjs_coverage_idr + $episode->insurance_copay_idr + $episode->patient_share_idr;
            if ($episode->total_charges_idr > 0 && $totalCovered < $episode->total_charges_idr) {
                // Selisih harus tercakup oleh escrow deposit atau pembebasan rumah sakit
                $deficit = $episode->total_charges_idr - $totalCovered;
                if ($episode->escrow_deposit_idr < $deficit) {
                    throw new DomainException("Tidak dapat menyelesaikan billing: total pertanggungan ({$totalCovered}) ditambah deposit ({$episode->escrow_deposit_idr}) kurang dari total tagihan ({$episode->total_charges_idr}).");
                }
            }
        }

        $episode->status = $targetStatus;
        $episode->save();

        return $episode;
    }
}
