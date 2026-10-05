<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Pricing\Contracts\PriceLocker;
use Modules\Pricing\Domain\Models\AnalyticsSnapshot;
use Modules\Pricing\Domain\Models\DiscountRule;
use Modules\Pricing\Domain\Models\MarginPolicy;
use Modules\Pricing\Domain\Models\PriceEvent;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\PriceListItem;
use Modules\Pricing\Domain\Models\PriceLock;
use Modules\Pricing\Domain\Models\PriceOverride;
use Modules\Pricing\Domain\Models\Promotion;
use Modules\Pricing\Domain\Models\PromotionClaim;

/**
 * Price list, discount waterfall, trade promotion, immutable price locks,
 * margin floor + approval override, and pricing analytics (Fase 44).
 */
class PricingService implements PriceLocker
{
    public function __construct(
        private readonly ApprovalEngineInterface $approvals,
        private readonly DocumentNumberingInterface $numbering,
    ) {}

    // ── 44.1 Price lists ────────────────────────────────────────────────

    /**
     * Create a draft price list; active periods may not overlap within the
     * same channel/segment/region scope.
     *
     * @param array{code:string,name:string,channel?:string,segment?:string|null,
     *   region_code?:string|null,currency?:string,priority?:int,valid_from:string,
     *   valid_until?:string|null,notes?:string} $data
     */
    public function createPriceList(array $data, User $creator): PriceList
    {
        $validUntil = $data['valid_until'] ?? null;
        if ($validUntil !== null && $validUntil < $data['valid_from']) {
            throw new InvalidArgumentException('Tanggal akhir price list harus setelah tanggal mulai.');
        }

        return PriceList::create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'channel' => $data['channel'] ?? 'general',
            'segment' => $data['segment'] ?? null,
            'region_code' => $data['region_code'] ?? null,
            'currency' => strtoupper($data['currency'] ?? 'IDR'),
            'priority' => (int) ($data['priority'] ?? 100),
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'] ?? null,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $creator->id,
        ]);
    }

    /**
     * Create/update one SKU tier in a draft price list.
     */
    public function setPriceListItem(PriceList $list, string $sku, int $priceIdr, float $minQty = 1): PriceListItem
    {
        if ($list->status !== 'draft') {
            throw new InvalidArgumentException('Price list aktif immutable; buat versi baru untuk perubahan.');
        }
        if ($priceIdr < 0 || $minQty <= 0) {
            throw new InvalidArgumentException('Harga tidak boleh negatif dan min qty harus > 0.');
        }

        return PriceListItem::updateOrCreate(
            ['price_list_id' => $list->id, 'sku' => strtoupper($sku), 'min_qty' => $minQty],
            ['price_idr' => $priceIdr]
        );
    }

    /**
     * Activate price list only if no active same-scope date overlap.
     * Change event is idempotent.
     */
    public function activatePriceList(PriceList $list): PriceList
    {
        return DB::transaction(function () use ($list) {
            /** @var PriceList $locked */
            $locked = PriceList::query()->lockForUpdate()->findOrFail($list->getKey());
            if ($locked->status === 'active') {
                return $locked;
            }
            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException("Price list {$locked->code} tidak dapat diaktifkan ({$locked->status}).");
            }
            if ($locked->items()->count() === 0) {
                throw new InvalidArgumentException('Price list kosong tidak dapat diaktifkan.');
            }

            $overlap = PriceList::where('status', 'active')
                ->where('channel', $locked->channel)
                ->where('segment', $locked->segment)
                ->where('region_code', $locked->region_code)
                ->whereDate('valid_from', '<=', $locked->valid_until ?? '9999-12-31')
                ->where(function ($q) use ($locked) {
                    $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $locked->valid_from);
                })
                ->exists();

            if ($overlap) {
                throw new InvalidArgumentException('Periode price list overlap pada channel/segmen/wilayah yang sama.');
            }

            $locked->status = 'active';
            $locked->save();
            $this->recordEvent('price_list_activated', PriceList::class, (string) $locked->id, [
                'code' => $locked->code, 'channel' => $locked->channel,
                'valid_from' => $locked->valid_from->toDateString(),
            ]);

            return $locked;
        });
    }

    /**
     * Resolve price list with specificity then priority: region > segment >
     * channel; lower priority number wins among same specificity.
     */
    public function resolveList(string $sku, string $channel = 'general', ?string $segment = null, ?string $regionCode = null, ?string $at = null): ?PriceList
    {
        $date = $at ?? now()->toDateString();
        $lists = PriceList::where('status', 'active')
            ->where('channel', $channel)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date);
            })
            ->whereHas('items', fn ($q) => $q->where('sku', strtoupper($sku)))
            ->get();

        return $lists->filter(fn (PriceList $list) => ($list->segment === null || $list->segment === $segment)
            && ($list->region_code === null || $list->region_code === $regionCode)
        )->sortBy(fn (PriceList $list) => [
            ($list->region_code !== null ? 0 : 1) + ($list->segment !== null ? 0 : 2),
            (int) $list->priority,
        ])->first();
    }

    public function resolveListPrice(PriceList $list, string $sku, float $qty): int
    {
        $tier = $list->items()->where('sku', strtoupper($sku))
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('min_qty')
            ->first();

        if ($tier === null) {
            throw new InvalidArgumentException("SKU {$sku} tidak ada pada price list {$list->code}.");
        }

        return (int) $tier->price_idr;
    }

    // ── 44.2 Deterministic discount waterfall ────────────────────────────

    /**
     * Compute list price → discount rules in ascending order → coupon.
     * Returns a full audit waterfall.
     *
     * @param  array<int, array{sku:string,qty:float,price_idr:int}>  $lines
     * @return array{list_total:int,discount_total:int,net_total:int,waterfall:array<int,array<string,mixed>>}
     */
    public function calculateWaterfall(array $lines, string $channel = 'general', ?string $region = null, ?string $coupon = null, ?string $at = null): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Waterfall harga harus punya baris.');
        }

        $date = $at ?? now()->toDateString();
        $listTotal = 0;
        $discountTotal = 0;
        $waterfall = [];
        $lineNets = [];

        foreach ($lines as $line) {
            $qty = (float) $line['qty'];
            $price = (int) $line['price_idr'];
            if ($qty <= 0 || $price < 0) {
                throw new InvalidArgumentException('Qty harus >0 dan harga tidak boleh negatif.');
            }
            $gross = (int) floor($qty * $price);
            $listTotal += $gross;
            $lineNets[] = ['sku' => strtoupper($line['sku']), 'qty' => $qty, 'gross' => $gross, 'net' => $gross];
        }

        $rules = DiscountRule::where('is_active', true)
            ->where('channel', $channel)
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date);
            })
            ->orderBy('order')->orderBy('code')->get();

        $stackable = true;
        foreach ($rules as $rule) {
            if ($rule->kind === 'coupon') {
                continue;
            }
            $eligible = array_filter($lineNets, fn (array $line) => $this->ruleAppliesToSku($rule, $line['sku']));
            $eligibleQty = array_sum(array_column($eligible, 'qty'));
            $eligibleValue = array_sum(array_column($eligible, 'net'));
            if ($eligible === [] || $eligibleQty < (float) $rule->threshold_qty
                || $eligibleValue < (int) $rule->threshold_amount_idr) {
                continue;
            }

            $base = (int) $eligibleValue;
            $discount = (int) floor($base * (float) $rule->percent_off / 100)
                + min($base, (int) $rule->amount_off_idr);
            if ($discount <= 0) {
                continue;
            }
            $discount = min($base, $discount);
            $this->applyProportionalDiscount($lineNets, $discount, $rule);
            $discountTotal += $discount;
            $waterfall[] = [
                'step' => 'rule', 'code' => $rule->code, 'kind' => $rule->kind,
                'base_idr' => $base, 'discount_idr' => $discount,
            ];
            if (! $rule->stackable) {
                $stackable = false;
            }
        }

        if ($coupon !== null) {
            $couponRule = DiscountRule::where('coupon_code', strtoupper($coupon))
                ->where('is_active', true)->first();
            if ($couponRule === null || ! $couponRule->isLive($date)) {
                throw new InvalidArgumentException('Kupon tidak valid atau tidak aktif.');
            }
            if (! $stackable && $waterfall !== []) {
                throw new InvalidArgumentException('Kupon tidak dapat ditumpuk dengan diskon yang terpakai.');
            }
            $base = (int) array_sum(array_column($lineNets, 'net'));
            $discount = min($base, (int) floor($base * (float) $couponRule->percent_off / 100)
                + (int) $couponRule->amount_off_idr);
            if ($discount > 0) {
                $this->applyProportionalDiscount($lineNets, $discount, $couponRule);
                $discountTotal += $discount;
                $waterfall[] = [
                    'step' => 'coupon', 'code' => $couponRule->code,
                    'coupon' => $couponRule->coupon_code,
                    'base_idr' => $base, 'discount_idr' => $discount,
                ];
            }
        }

        return [
            'list_total' => $listTotal,
            'discount_total' => $discountTotal,
            'net_total' => max(0, $listTotal - $discountTotal),
            'waterfall' => $waterfall,
            'lines' => $lineNets,
        ];
    }

    /** @param array<int, array<string,mixed>> $lineNets */
    private function applyProportionalDiscount(array &$lineNets, int $discount, DiscountRule $rule): void
    {
        $eligibleIndexes = [];
        $eligibleTotal = 0;
        foreach ($lineNets as $i => $line) {
            if ($this->ruleAppliesToSku($rule, $line['sku'])) {
                $eligibleIndexes[] = $i;
                $eligibleTotal += (int) $line['net'];
            }
        }
        if ($eligibleTotal <= 0) {
            return;
        }

        $remaining = $discount;
        foreach ($eligibleIndexes as $position => $index) {
            $lineNet = (int) $lineNets[$index]['net'];
            $share = $position === count($eligibleIndexes) - 1
                ? $remaining
                : (int) floor($discount * $lineNet / $eligibleTotal);
            $share = min($lineNet, max(0, $share));
            $lineNets[$index]['net'] = $lineNet - $share;
            $lineNets[$index]['discount_idr'] = (int) ($lineNets[$index]['discount_idr'] ?? 0) + $share;
            $remaining -= $share;
        }
    }

    private function ruleAppliesToSku(DiscountRule $rule, string $sku): bool
    {
        $applies = $rule->applies_to ?? [];
        $skus = $applies['skus'] ?? null;

        return $skus === null || in_array($sku, $skus, true);
    }

    // ── 44.3 Promo dagang & klaim ────────────────────────────────────────

    public function createPromotion(array $data): Promotion
    {
        if (($data['valid_until'] ?? '') < ($data['valid_from'] ?? '')) {
            throw new InvalidArgumentException('Periode promo tidak valid.');
        }

        return Promotion::create([
            'code' => strtoupper($data['code']), 'name' => $data['name'],
            'mechanic' => $data['mechanic'], 'budget_idr' => (int) $data['budget_idr'],
            'percent_off' => $data['percent_off'] ?? 0, 'amount_off_idr' => $data['amount_off_idr'] ?? 0,
            'applies_to' => $data['applies_to'] ?? null, 'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'], 'is_active' => true,
        ]);
    }

    /** Submit klaim dengan bukti; validasi promo live + sisa anggaran. */
    public function submitPromotionClaim(Promotion $promotion, ?string $distributorId, string $sourceRef, int $amountIdr, User $submitter, ?string $evidence = null): PromotionClaim
    {
        if (! $promotion->isLive()) {
            throw new InvalidArgumentException('Promo tidak aktif/di luar periode.');
        }
        $pendingClaims = (int) PromotionClaim::where('promotion_id', $promotion->id)
            ->whereIn('status', ['submitted', 'validated'])->sum('amount_idr');
        if ($amountIdr <= 0
            || $amountIdr + $pendingClaims > $promotion->remainingBudget()) {
            throw new InvalidArgumentException('Klaim promo melebihi sisa anggaran (termasuk klaim tertunda).');
        }
        if ($evidence === null || trim($evidence) === '') {
            throw new InvalidArgumentException('Bukti klaim wajib disertakan.');
        }

        return PromotionClaim::create([
            'promotion_id' => $promotion->id, 'distributor_id' => $distributorId,
            'source_ref' => $sourceRef, 'amount_idr' => $amountIdr, 'status' => 'submitted',
            'evidence_note' => $evidence, 'submitted_by_user_id' => $submitter->id,
        ]);
    }

    /** Validasi klaim promo + kirim approval four-eyes. */
    public function approvePromotionClaim(PromotionClaim $claim, User $creator): PromotionClaim
    {
        return DB::transaction(function () use ($claim, $creator) {
            /** @var PromotionClaim $locked */
            $locked = PromotionClaim::query()->lockForUpdate()->findOrFail($claim->getKey());
            if ($locked->status === 'approved') {
                return $locked;
            }
            if ($locked->status !== 'submitted') {
                throw new InvalidArgumentException("Klaim promo tidak dapat disetujui ({$locked->status}).");
            }

            $approval = $this->approvals->submit(
                approvalType: 'PRICING_PROMOTION_CLAIM',
                title: "Klaim promo {$locked->promotion?->code} ({$locked->source_ref})",
                creator: $creator,
                approvable: $locked,
                amount: (float) (int) $locked->amount_idr,
                steps: [['role' => 'procurement'], ['role' => 'admin']],
                slaHours: 48,
                metadata: ['claim_id' => $locked->id, 'promotion_id' => $locked->promotion_id],
            );
            $locked->approval_id = (int) $approval->id;
            $locked->status = 'validated';
            $locked->save();

            return $locked;
        });
    }

    /** Settlement klaim approved → settled; anggaran promo terpakai. */
    public function settlePromotionClaim(PromotionClaim $claim, User $approver): PromotionClaim
    {
        return DB::transaction(function () use ($claim, $approver) {
            /** @var PromotionClaim $locked */
            $locked = PromotionClaim::query()->lockForUpdate()->findOrFail($claim->getKey());
            if ($locked->status === 'settled') {
                return $locked;
            }
            if ($locked->status !== 'validated' || $locked->approval_id === null) {
                throw new InvalidArgumentException('Klaim belum lolos validasi/approval.');
            }

            $approval = $this->approvals->approve((int) $locked->approval_id, $approver, 'Klaim promo disetujui');
            for ($i = 0; $i < 5 && property_exists($approval, 'status') && $approval->status !== 'approved'; $i++) {
                $approval = $this->approvals->approve((int) $locked->approval_id, $approver, 'Klaim promo disetujui');
            }
            if (property_exists($approval, 'status') && $approval->status !== 'approved') {
                throw new InvalidArgumentException('Approval klaim belum tuntas.');
            }

            $promotion = Promotion::query()->lockForUpdate()->findOrFail($locked->promotion_id);
            if ((int) $promotion->spent_idr + (int) $locked->amount_idr > (int) $promotion->budget_idr) {
                throw new InvalidArgumentException('Anggaran promo tidak cukup saat settlement.');
            }
            $promotion->spent_idr = (int) $promotion->spent_idr + (int) $locked->amount_idr;
            $promotion->save();
            $locked->status = 'settled';
            $locked->save();

            return $locked;
        });
    }

    // ── 44.4 Price lock + 44.5 floor/override ───────────────────────────

    /**
     * Resolve price + waterfall, honor active contract locked price first,
     * then active price list; reject below-margin unless an approved override.
     *
     * @return array{price_idr:int,discount_idr:int,source_kind:string,price_list:?PriceList,waterfall:array<int,array<string,mixed>>,override:?PriceOverride}
     */
    public function quote(
        string $sku, float $qty, string $channel = 'general', ?string $segment = null,
        ?string $region = null, ?int $contractPriceIdr = null, ?string $coupon = null,
        ?string $subjectType = null, ?string $subjectId = null,
    ): array {
        $list = $this->resolveList($sku, $channel, $segment, $region);
        $listPrice = $list !== null ? $this->resolveListPrice($list, $sku, $qty) : null;

        if ($contractPriceIdr !== null) {
            $basePrice = $contractPriceIdr;
            $source = 'contract';
        } elseif ($listPrice !== null) {
            $basePrice = $listPrice;
            $source = 'price_list';
        } else {
            throw new InvalidArgumentException("Tidak ada harga untuk SKU {$sku}.");
        }

        $waterfall = $this->calculateWaterfall([[
            'sku' => $sku, 'qty' => $qty, 'price_idr' => $basePrice,
        ]], $channel, $region, $coupon);
        $line = $waterfall['lines'][0];
        $applied = $qty > 0 ? (int) floor($line['net'] / $qty) : $basePrice;

        $policy = MarginPolicy::where('is_active', true)
            ->where('channel', $channel)
            ->where(function ($q) use ($sku) {
                $q->whereNull('sku')->orWhere('sku', strtoupper($sku));
            })
            ->orderByRaw('CASE WHEN sku IS NULL THEN 1 ELSE 0 END')
            ->first();

        $override = null;
        if ($policy !== null) {
            $floor = max((int) $policy->minAllowedPrice(), (int) $policy->floor_cost_idr);
            if ($applied < $floor) {
                $approved = PriceOverride::where('sku', strtoupper($sku))
                    ->where('channel', $channel)->where('status', 'approved')
                    ->where('proposed_price_idr', $applied)->latest('decided_at')->first();
                if ($approved === null) {
                    throw new InvalidArgumentException(sprintf(
                        'Harga %s di bawah floor margin (%s); perlu approval override.',
                        number_format($applied), number_format($floor)
                    ));
                }
                $override = $approved;
                $source = 'override';
            }
        }

        if ($subjectType !== null && $subjectId !== null) {
            $this->lockPrice($subjectType, $subjectId, $sku, $qty, (int) $listPrice, $applied,
                (int) ($line['discount_idr'] ?? 0), $list, $source, $override?->id, $waterfall['waterfall']);
        }

        return [
            'price_idr' => $applied,
            'discount_idr' => (int) ($line['discount_idr'] ?? 0),
            'source_kind' => $source,
            'price_list' => $list,
            'waterfall' => $waterfall['waterfall'],
            'override' => $override,
        ];
    }

    /** Price lock immutable: replay returns existing snapshot, never rewrites. */
    public function lockPrice(
        string $subjectType, string $subjectId, string $sku, float $qty,
        int $listPrice, int $appliedPrice, int $discount, ?PriceList $list,
        string $sourceKind, ?string $sourceRuleId, array $waterfall = [], ?string $reason = null,
    ): PriceLock {
        $existing = PriceLock::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)->where('sku', strtoupper($sku))->first();
        if ($existing !== null) {
            return $existing;
        }

        return PriceLock::create([
            'subject_type' => $subjectType, 'subject_id' => $subjectId, 'sku' => strtoupper($sku),
            'qty' => $qty, 'list_price_idr' => $listPrice, 'applied_price_idr' => $appliedPrice,
            'discount_idr' => $discount, 'price_list_id' => $list?->id,
            'source_rule_id' => $sourceRuleId, 'source_kind' => $sourceKind,
            'waterfall' => $waterfall, 'reason' => $reason, 'locked_at' => now(),
        ]);
    }

    /** Request price override (below margin floor) → approval four-eyes. */
    public function requestOverride(string $sku, string $channel, int $proposedPrice, string $reason, User $requester): PriceOverride
    {
        $policy = MarginPolicy::where('is_active', true)->where('channel', $channel)
            ->where(fn ($q) => $q->whereNull('sku')->orWhere('sku', strtoupper($sku)))
            ->orderByRaw('CASE WHEN sku IS NULL THEN 1 ELSE 0 END')->first();
        $floor = $policy !== null ? max($policy->minAllowedPrice(), (int) $policy->floor_cost_idr) : 0;
        if ($proposedPrice < 0 || $reason === '') {
            throw new InvalidArgumentException('Harga override tidak valid; alasan wajib.');
        }

        $cost = max(1, $floor);
        $margin = (($proposedPrice - $cost) / $cost) * 100;
        $override = PriceOverride::create([
            'sku' => strtoupper($sku), 'channel' => $channel,
            'proposed_price_idr' => $proposedPrice, 'floor_price_idr' => $floor,
            'margin_percent' => round($margin, 4), 'status' => 'pending', 'reason' => $reason,
            'requested_by_user_id' => $requester->id,
        ]);

        $approval = $this->approvals->submit(
            approvalType: 'PRICING_MARGIN_OVERRIDE',
            title: "Override harga {$sku} ({$channel})",
            creator: $requester,
            approvable: $override,
            amount: (float) $proposedPrice,
            steps: [['role' => 'procurement'], ['role' => 'admin']],
            slaHours: 48,
            metadata: ['override_id' => $override->id, 'floor_price_idr' => $floor, 'reason' => $reason],
        );
        $override->approval_id = (int) $approval->id;
        $override->save();

        return $override;
    }

    public function decideOverride(PriceOverride $override, User $approver, bool $approve, ?string $note = null): PriceOverride
    {
        return DB::transaction(function () use ($override, $approver, $approve, $note) {
            /** @var PriceOverride $locked */
            $locked = PriceOverride::query()->lockForUpdate()->findOrFail($override->getKey());
            if ($locked->status !== 'pending' || $locked->approval_id === null) {
                throw new InvalidArgumentException('Override tidak menunggu approval.');
            }

            if ($approve) {
                $approval = $this->approvals->approve((int) $locked->approval_id, $approver, $note ?? 'Override disetujui');
                for ($i = 0; $i < 5 && property_exists($approval, 'status') && $approval->status !== 'approved'; $i++) {
                    $approval = $this->approvals->approve((int) $locked->approval_id, $approver, $note ?? 'Override disetujui');
                }
                if (property_exists($approval, 'status') && $approval->status !== 'approved') {
                    throw new InvalidArgumentException('Approval override belum tuntas.');
                }
                $locked->status = 'approved';
            } else {
                $this->approvals->reject((int) $locked->approval_id, $approver, $note ?? 'Override ditolak');
                $locked->status = 'rejected';
            }
            $locked->decided_at = now();
            $locked->save();

            return $locked;
        });
    }

    /** Contract PriceLocker: kunci harga per baris (dipakai Store/Distribusi). */
    public function lockLine(string $subjectType, string $subjectId, array $line, string $sourceKind, array $waterfall = [], ?string $reason = null): void
    {
        $this->lockPrice(
            $subjectType,
            $subjectId,
            (string) $line['sku'],
            (float) $line['qty'],
            (int) ($line['list_price_idr'] ?? $line['price_idr'] ?? 0),
            (int) $line['price_idr'],
            (int) ($line['discount_idr'] ?? 0),
            null,
            $sourceKind,
            null,
            $waterfall,
            $reason,
        );
    }

    // ── 44.6 Event perubahan harga ───────────────────────────────────────

    public function recordEvent(string $kind, string $subjectType, string $subjectId, array $payload): PriceEvent
    {
        $key = hash('sha256', implode('|', [$kind, $subjectType, $subjectId, json_encode($payload, JSON_THROW_ON_ERROR)]));

        return PriceEvent::firstOrCreate(
            ['event_key' => $key],
            ['kind' => $kind, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'payload' => $payload, 'recorded_at' => now()]
        );
    }

    // ── 44.7 Analitik ────────────────────────────────────────────────────

    /**
     * Analitik dari price locks: realisasi vs list, leakage (discount lock
     * tanpa rule/promo/contract), volume per periode & channel.
     */
    public function computeAnalytics(string $period, string $channel = 'general'): AnalyticsSnapshot
    {
        $locks = PriceLock::whereBetween('locked_at', [
            $period.'-01 00:00:00',
            Carbon::parse($period.'-01')->endOfMonth()->endOfDay()->toDateTimeString(),
        ])->get();

        $list = 0;
        $applied = 0;
        $discount = 0;
        $leakage = 0;
        foreach ($locks as $lock) {
            $gross = (int) floor((float) $lock->qty * (int) $lock->list_price_idr);
            $net = (int) floor((float) $lock->qty * (int) $lock->applied_price_idr);
            $list += $gross;
            $applied += $net;
            $discount += (int) $lock->discount_idr;
            if ((int) $lock->discount_idr > 0 && ! in_array($lock->source_kind, ['discount', 'promo', 'contract', 'override'], true)) {
                $leakage += (int) $lock->discount_idr;
            }
        }

        $ratio = $list > 0 ? $applied * 100 / $list : 0.0;
        $avgDiscount = $list > 0 ? $discount * 100 / $list : 0.0;
        $promoSpent = Promotion::whereBetween('valid_from', [$period.'-01', $period.'-31'])->sum('spent_idr');
        $effectiveness = $promoSpent > 0 ? min(100.0, max(0.0, ($applied - $promoSpent) * 100 / $promoSpent)) : 0.0;

        return AnalyticsSnapshot::updateOrCreate(
            ['period' => $period, 'channel' => $channel],
            [
                'realized_vs_list_percent' => round($ratio, 4),
                'discount_leakage_idr' => $leakage,
                'promo_effectiveness_percent' => round($effectiveness, 4),
                'avg_discount_percent' => round($avgDiscount, 4),
                'volume_idr' => $applied,
                'breakdown' => ['locks' => $locks->count(), 'list_idr' => $list, 'discount_idr' => $discount],
            ]
        );
    }
}
