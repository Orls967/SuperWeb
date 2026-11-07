<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Services\HotelPersonalizationRecommendationEngine;

final class HotelSyncPersonalizedCampaignsCommand extends Command
{
    protected $signature = 'hotel:sync-personalized-campaigns';

    protected $description = 'Melakukan sinkronisasi rekomendasi perjalanan terpersonalisasi untuk seluruh pemegang Travel Pass aktif';

    public function handle(HotelPersonalizationRecommendationEngine $engine): int
    {
        $this->info('Memulai sinkronisasi rekomendasi personalisasi kampanye loyalitas...');

        $passes = HtlTravelPass::where('is_active', true)->get();
        $this->info("Menemukan {$passes->count()} anggota Travel Pass aktif.");

        $syncedCount = 0;
        foreach ($passes as $pass) {
            $recommendations = $engine->generateMemberRecommendations($pass->id);
            $syncedCount += count($recommendations);
        }

        $this->info("✓ Sinkronisasi selesai: {$syncedCount} kartu rekomendasi personal dibuat untuk {$passes->count()} anggota.");

        return self::SUCCESS;
    }
}
