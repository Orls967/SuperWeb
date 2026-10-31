<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Ret\Contracts\MapPriceLookupInterface;
use Modules\Ret\Domain\Exceptions\MapPriceViolationException;
use Modules\Ret\Domain\Models\RetChannel;
use Modules\Ret\Domain\Models\RetListing;
use Modules\Ret\Domain\Models\RetSeller;
use Modules\Shared\Application\BaseAction;

class CreateOrUpdateListingAction extends BaseAction
{
    public function __construct(
        protected MapPriceLookupInterface $mapPriceLookup
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetListing
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required to create or update a listing.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        // Return existing listing if idempotency key matches
        $existing = RetListing::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $seller = RetSeller::findOrFail($params['seller_id']);
        $channel = RetChannel::findOrFail($params['channel_id']);
        $sku = (string) $params['sku'];
        $title = (string) ($params['title'] ?? $sku);
        $priceMinor = (int) $params['price_minor'];

        try {
            $this->mapPriceLookup->assertPriceMeetsMap($sku, $priceMinor);
        } catch (MapPriceViolationException $e) {
            $seller->increment('map_violation_count');
            throw $e;
        }

        $mapPriceMinor = $this->mapPriceLookup->getMapPrice($sku);

        return DB::transaction(function () use ($seller, $channel, $sku, $title, $priceMinor, $mapPriceMinor, $idempotencyKey) {
            $listing = RetListing::updateOrCreate(
                [
                    'seller_id' => $seller->id,
                    'channel_id' => $channel->id,
                    'sku' => $sku,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'title' => $title,
                    'price_minor' => $priceMinor,
                    'map_price_minor' => $mapPriceMinor,
                    'is_active' => true,
                    'idempotency_key' => $idempotencyKey,
                ]
            );

            $this->audit(
                action: 'ret_listing_saved',
                auditable: $listing,
                newValues: ['sku' => $sku, 'price_minor' => $priceMinor, 'map_price_minor' => $mapPriceMinor],
                impactType: 'catalog_management',
            );

            return $listing;
        });
    }
}
