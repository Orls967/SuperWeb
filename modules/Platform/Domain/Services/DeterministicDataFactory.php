<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

/**
 * Factory penghasil data deterministik untuk pengujian multi-domain pilar.
 */
class DeterministicDataFactory
{
    /**
     * Menghasilkan record transaksi bisnis sintetis deterministik
     *
     * @return array<string, mixed>
     */
    public function generateTransaction(string $domain, int $sequence, string $seedKey = 'default_seed'): array
    {
        $hash = hash('sha256', "{$domain}:{$sequence}:{$seedKey}");
        $amount = (hexdec(substr($hash, 0, 6)) % 500000) + 10000;

        return match ($domain) {
            'banking' => [
                'tx_uuid' => substr($hash, 0, 32),
                'account_from' => 'ACC-' . substr($hash, 32, 8),
                'account_to' => 'ACC-' . substr($hash, 40, 8),
                'amount' => $amount,
                'currency' => 'IDR',
                'type' => 'transfer',
            ],
            'inventory' => [
                'movement_uuid' => substr($hash, 0, 32),
                'sku' => 'SKU-' . strtoupper(substr($hash, 32, 6)),
                'quantity' => (hexdec(substr($hash, 38, 2)) % 50) + 1,
                'location_code' => 'WH-' . strtoupper(substr($hash, 40, 4)),
                'reason' => 'inbound_shipment',
            ],
            'order' => [
                'order_code' => 'ORD-' . strtoupper(substr($hash, 0, 8)),
                'total_price' => $amount * 2,
                'items_count' => (hexdec(substr($hash, 10, 2)) % 5) + 1,
                'status' => 'confirmed',
            ],
            default => [
                'record_uuid' => substr($hash, 0, 32),
                'domain' => $domain,
                'sequence' => $sequence,
                'payload_hash' => $hash,
            ],
        };
    }

    /**
     * Menghasilkan kumpulan record batch deterministik
     *
     * @return list<array<string, mixed>>
     */
    public function generateBatch(string $domain, int $count, string $seedKey = 'default_seed'): array
    {
        $batch = [];
        for ($i = 1; $i <= $count; $i++) {
            $batch[] = $this->generateTransaction($domain, $i, $seedKey);
        }

        return $batch;
    }
}

