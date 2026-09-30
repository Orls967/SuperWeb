<?php

declare(strict_types=1);

namespace Modules\Crypto\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Crypto\Domain\Models\CryptoPriceTick;

class CryptoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(12345);

        $assets = [
            [
                'symbol' => 'BTC',
                'name' => 'Bitcoin',
                'decimals' => 8,
                'volatility' => 0.025,
                'is_active' => true,
                'icon' => 'btc',
                'base_price' => 1550000000.0,
            ],
            [
                'symbol' => 'ETH',
                'name' => 'Ethereum',
                'decimals' => 8,
                'volatility' => 0.03,
                'is_active' => true,
                'icon' => 'eth',
                'base_price' => 52000000.0,
            ],
            [
                'symbol' => 'SOL',
                'name' => 'Solana',
                'decimals' => 8,
                'volatility' => 0.045,
                'is_active' => true,
                'icon' => 'sol',
                'base_price' => 2800000.0,
            ],
            [
                'symbol' => 'BNB',
                'name' => 'BNB Chain',
                'decimals' => 8,
                'volatility' => 0.025,
                'is_active' => true,
                'icon' => 'bnb',
                'base_price' => 9200000.0,
            ],
            [
                'symbol' => 'USDT',
                'name' => 'Tether USD',
                'decimals' => 6,
                'volatility' => 0.001,
                'is_active' => true,
                'icon' => 'usdt',
                'base_price' => 16200.0,
            ],
        ];

        $now = now();
        $totalHours = 30 * 24; // 720 hours = 30 days

        foreach ($assets as $assetData) {
            $basePrice = $assetData['base_price'];
            unset($assetData['base_price']);

            $asset = CryptoAsset::updateOrCreate(
                ['symbol' => $assetData['symbol']],
                $assetData
            );

            // If ticks already exist, skip historical generation
            if (CryptoPriceTick::where('asset_id', $asset->id)->exists()) {
                continue;
            }

            $currentPrice = $basePrice * (1.0 + (mt_rand(-5, 5) / 100.0));
            $ticksToInsert = [];

            for ($i = $totalHours; $i >= 0; $i--) {
                $recordedAt = $now->copy()->subHours($i);

                if ($asset->symbol === 'USDT') {
                    $price = 16200.0 + (mt_rand(-15, 15));
                } else {
                    $drift = (mt_rand(-10, 12) / 1000.0);
                    $step = ($drift * ($asset->volatility / 0.025));
                    $currentPrice = $currentPrice * (1.0 + $step);

                    // Bound price between 0.3x and 2.5x base price
                    if ($currentPrice < $basePrice * 0.3) {
                        $currentPrice = $basePrice * 0.3;
                    } elseif ($currentPrice > $basePrice * 2.5) {
                        $currentPrice = $basePrice * 2.5;
                    }

                    $price = round($currentPrice, 2);
                }

                $ticksToInsert[] = [
                    'asset_id' => $asset->id,
                    'price_idr' => (string) $price,
                    'recorded_at' => $recordedAt->toDateTimeString(),
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ];

                if (count($ticksToInsert) >= 500) {
                    DB::table('crypto_price_ticks')->insert($ticksToInsert);
                    $ticksToInsert = [];
                }
            }

            if (! empty($ticksToInsert)) {
                DB::table('crypto_price_ticks')->insert($ticksToInsert);
            }
        }
    }
}
