<?php

declare(strict_types=1);

namespace Modules\Supplier\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Party\Domain\Models\Party;
use Modules\Supplier\Application\Services\SupplierService;
use Modules\Supplier\Domain\Models\Supplier;
use Modules\Supplier\Domain\Models\SupplierCertification;
use Modules\Supplier\Domain\Models\SupplierItem;
use Modules\Supplier\Domain\Models\SupplierPriceTier;

class SupplierSeeder extends Seeder
{
    public function run(SupplierService $service): void
    {
        if (Supplier::count() > 0) {
            return; // idempoten
        }

        $party = Party::where('is_active', true)->first();

        $supplier = $service->register([
            'party_id' => $party?->id,
            'name' => 'PT Pangan Nusantara Sejahtera',
            'kind' => 'producer',
            'lead_time_days' => 5,
            'payment_terms_days' => 45,
            'capabilities' => ['Pangan beku', 'Bumbu dapur', 'Minuman kemasan'],
            'factory_locations' => ['Gudang Bekasi', 'Pabrik Tangerang'],
            'notes' => 'Pemasok contoh seed Fase 32.',
        ]);

        // Sertifikasi ber-masa berlaku (simulasi).
        SupplierCertification::create([
            'supplier_id' => $supplier->id,
            'type' => 'iso9001',
            'number' => 'ISO-9001-2026-001',
            'issuer' => 'Lembaga Sertifikasi Nasional (simulasi)',
            'issued_at' => now()->subYear(),
            'expires_at' => now()->addMonths(14),
            'is_active' => true,
        ]);

        SupplierCertification::create([
            'supplier_id' => $supplier->id,
            'type' => 'halal',
            'number' => 'HAL-2026-088',
            'issuer' => 'BPJPH (simulasi)',
            'issued_at' => now()->subMonths(20),
            'expires_at' => now()->addMonths(4),
            'is_active' => true,
        ]);

        // Katalog + harga bertingkat.
        $item = SupplierItem::create([
            'supplier_id' => $supplier->id,
            'supplier_sku' => 'PNS-0001',
            'name' => 'Daging sapi beku (komersial)',
            'unit' => 'kg',
            'moq' => 10,
            'lead_time_days' => 5,
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        SupplierPriceTier::create([
            'item_id' => $item->id,
            'min_qty' => 10,
            'max_qty' => 49,
            'unit_price' => '125000.0000',
            'currency' => 'IDR',
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => now()->addYear()->toDateString(),
            'is_active' => true,
        ]);

        SupplierPriceTier::create([
            'item_id' => $item->id,
            'min_qty' => 50,
            'unit_price' => '118500.0000',
            'currency' => 'IDR',
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => now()->addYear()->toDateString(),
            'is_active' => true,
        ]);

        // Skor periodik (32.6) — sedikit di bawah ambang untuk memicu risk flag contoh.
        $service->saveScorecard($supplier, now()->format('Y-m'), [
            'otd_percent' => 92.0,
            'reject_percent' => 2.5,
            'price_index' => 1.02,
            'response_days' => 1.5,
        ], 'Seed skor periodik contoh.');
    }
}
