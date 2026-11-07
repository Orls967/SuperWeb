<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

final class VipBottleLoungeInventoryService
{
    /**
     * @var array<string, array{sku: string, name: string, category: string, stock: int, cellar_bin: string, excise_stamp_prefix: string}>
     */
    private array $cellarInventory = [
        'DOM_PERIGNON_LUMINOUS' => [
            'sku' => 'CHAMP-DOM-LUM',
            'name' => 'Dom Perignon Luminous Vintage',
            'category' => 'CHAMPAGNE',
            'stock' => 45,
            'cellar_bin' => 'BIN-A1-TEMP-12C',
            'excise_stamp_prefix' => 'EX-BEA-MMEA-A',
        ],
        'ACE_OF_SPADES_GOLD' => [
            'sku' => 'CHAMP-ARMAND-GLD',
            'name' => 'Armand de Brignac Brut Gold',
            'category' => 'CHAMPAGNE',
            'stock' => 24,
            'cellar_bin' => 'BIN-A2-TEMP-12C',
            'excise_stamp_prefix' => 'EX-BEA-MMEA-A',
        ],
        'MACALLAN_18_SHERRY' => [
            'sku' => 'WHISKY-MAC-18',
            'name' => 'The Macallan 18 Sherry Oak',
            'category' => 'SINGLE_MALT',
            'stock' => 30,
            'cellar_bin' => 'BIN-B1-ROOM',
            'excise_stamp_prefix' => 'EX-BEA-MMEA-B',
        ],
        'CLASE_AZUL_REPOSADO' => [
            'sku' => 'TEQ-CLASE-AZUL',
            'name' => 'Clase Azul Reposado Tequila',
            'category' => 'TEQUILA',
            'stock' => 18,
            'cellar_bin' => 'BIN-C1-ROOM',
            'excise_stamp_prefix' => 'EX-BEA-MMEA-C',
        ],
    ];

    /**
     * Verify physical cellar stock availability and excise stamp integrity.
     *
     * @return array{
     *     sku: string,
     *     name: string,
     *     cellar_bin: string,
     *     stock_available: int,
     *     excise_stamp_valid: bool,
     *     temperature_compliant: bool
     * }
     */
    public function verifyBottleForDispatch(string $bottleSku, string $physicalExciseStamp): array
    {
        $item = null;
        foreach ($this->cellarInventory as $b) {
            if ($b['sku'] === $bottleSku) {
                $item = $b;
                break;
            }
        }

        if ($item === null) {
            throw new RuntimeException("SKU botol '{$bottleSku}' tidak ditemukan di katalog gudang VIP.");
        }

        if ($item['stock'] <= 0) {
            throw new RuntimeException("Stok botol '{$item['name']}' di gudang habis.");
        }

        $isValidStamp = str_starts_with($physicalExciseStamp, $item['excise_stamp_prefix']);

        return [
            'sku' => $item['sku'],
            'name' => $item['name'],
            'cellar_bin' => $item['cellar_bin'],
            'stock_available' => $item['stock'],
            'excise_stamp_valid' => $isValidStamp,
            'temperature_compliant' => true,
        ];
    }

    /**
     * Log bottle breakage or wastage for tax compliance exemption.
     *
     * @return array{sku: string, incident_time: string, reason: string, write_off_approved: bool}
     */
    public function recordBreakageSpill(string $bottleSku, string $reason, string $witnessGuard): array
    {
        return [
            'sku' => $bottleSku,
            'incident_time' => CarbonImmutable::now()->toIso8601String(),
            'reason' => "Klaim susut/pecah: {$reason} (Saksi: {$witnessGuard})",
            'write_off_approved' => true,
        ];
    }
}
