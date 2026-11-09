<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Billing;

use DomainException;
use Modules\Hospital\Application\DTOs\FolioItemDTO;
use Modules\Hospital\Domain\Models\BillingEpisode;
use Modules\Hospital\Domain\Models\FolioItem;
use Modules\Hospital\Domain\Services\HospitalBillingCalculationService;

final class AddFolioItemAction
{
    public function __construct(
        private readonly HospitalBillingCalculationService $calculationService
    ) {}

    /**
     * @param  array{billing_episode_id: int, item_category: string, description: string, quantity: int, unit_price_idr: int}  $data
     */
    public function execute(array $data): FolioItem
    {
        $episodeId = (int) $data['billing_episode_id'];
        $episode = BillingEpisode::find($episodeId);

        if (! $episode) {
            throw new DomainException("Billing episode #{$episodeId} tidak ditemukan.");
        }

        $dto = FolioItemDTO::fromArray($data);

        return $this->calculationService->addItemToEpisode($episode, $dto);
    }
}
