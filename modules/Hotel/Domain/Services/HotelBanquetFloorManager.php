<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;

class HotelBanquetFloorManager
{
    /**
     * Mengatur alokasi meja jamuan VIP dan layout panggung berdasarkan protokol keprotokolan negara atau korporat.
     *
     * @return array{contract_id: string, total_tables: int, vip_tables_assigned: int, vvip_stage_seats: int, protocol_level: string, layout_status: string}
     */
    public function configureBanquetProtocolSeating(
        string $contractId,
        int $totalPax,
        bool $isVvipStateProtocol = false
    ): array {
        if ($totalPax <= 0) {
            throw new InvalidArgumentException('Total pax harus lebih besar dari nol.');
        }

        $tablesCount = (int) ceil($totalPax / 10);
        $vipTables = $isVvipStateProtocol ? (int) max(2, ceil($tablesCount * 0.20)) : 1;
        $vvipSeats = $isVvipStateProtocol ? 12 : 6;

        $protocolLevel = $isVvipStateProtocol ? 'STATE_PRESIDENTIAL_PROTOCOL' : 'STANDARD_CORPORATE_VIP';

        return [
            'contract_id' => $contractId,
            'total_tables' => $tablesCount,
            'vip_tables_assigned' => $vipTables,
            'vvip_stage_seats' => $vvipSeats,
            'protocol_level' => $protocolLevel,
            'layout_status' => 'FLOOR_LAYOUT_LOCKED',
        ];
    }
}
