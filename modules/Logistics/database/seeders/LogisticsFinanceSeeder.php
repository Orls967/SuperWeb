<?php

declare(strict_types=1);

namespace Modules\Logistics\database\seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Logistics\Application\Actions\AccrueDemurrageDetentionAction;
use Modules\Logistics\Application\Actions\AssignCarrierToLegAction;
use Modules\Logistics\Application\Actions\AssignScheduleResourcesAction;
use Modules\Logistics\Application\Actions\BookPostpaidShipmentAction;
use Modules\Logistics\Application\Actions\BookShipmentAction;
use Modules\Logistics\Application\Actions\ClearCustomsAction;
use Modules\Logistics\Application\Actions\CompleteShipmentLegAction;
use Modules\Logistics\Application\Actions\CreateClaimAction;
use Modules\Logistics\Application\Actions\DecideClaimAction;
use Modules\Logistics\Application\Actions\DepositCodCashAction;
use Modules\Logistics\Application\Actions\EndContainerDwellAction;
use Modules\Logistics\Application\Actions\GenerateDdInvoicesAction;
use Modules\Logistics\Application\Actions\GenerateMonthlyInvoicesAction;
use Modules\Logistics\Application\Actions\PayCarriersAction;
use Modules\Logistics\Application\Actions\PayClaimAction;
use Modules\Logistics\Application\Actions\PayCustomsDutyAction;
use Modules\Logistics\Application\Actions\PayLogisticsInvoiceAction;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Application\Actions\RecordCodCollectionAction;
use Modules\Logistics\Application\Actions\RecordFuelLogAction;
use Modules\Logistics\Application\Actions\RecordTrackingEventAction;
use Modules\Logistics\Application\Actions\ReserveCapacityAction;
use Modules\Logistics\Application\Actions\SettleCodAction;
use Modules\Logistics\Application\Actions\StartContainerDwellAction;
use Modules\Logistics\Application\Actions\SubmitClaimAction;
use Modules\Logistics\Application\Actions\SubmitCustomsDeclarationAction;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Enums\TransportMode;
use Modules\Logistics\Domain\Events\ShipmentDelivered;
use Modules\Logistics\Domain\Models\Carrier;
use Modules\Logistics\Domain\Models\CodCollection;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\DdTariff;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\HsTariff;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\LogisticsInvoice;
use Modules\Logistics\Domain\Models\RateBracket;
use Modules\Logistics\Domain\Models\RateCard;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentLeg;
use Modules\Logistics\Domain\Models\ShipperAccount;
use Modules\Logistics\Domain\Models\Surcharge;
use Modules\Logistics\Domain\Models\Truck;

/**
 * Data demo alur uang logistik (Fase 23). Seluruhnya dibuat lewat action produksi sehingga
 * `bank:reconcile` dan `lgx:audit-billing` bernilai non-nol dan seimbang pada seed default.
 */
class LogisticsFinanceSeeder extends Seeder
{
    private Location $hubBdj;

    private Location $hubBjb;

    public function run(): void
    {
        $this->hubBdj = Location::where('code', 'HUB-BDJ')->firstOrFail();
        $this->hubBjb = Location::where('code', 'HUB-BJB')->firstOrFail();

        $shipper1 = User::where('email', 'shipper01@autoserve.test')->firstOrFail();
        $shipper2 = User::where('email', 'shipper02@autoserve.test')->firstOrFail();
        $shipper3 = User::where('email', 'shipper03@autoserve.test')->firstOrFail();
        $admin = User::where('email', 'logistics.admin@autoserve.test')->firstOrFail();
        $dispatcher1 = User::where('email', 'dispatcher01@autoserve.test')->firstOrFail();
        $dispatcher2 = User::where('email', 'dispatcher02@autoserve.test')->firstOrFail();
        $hubOperator = User::where('email', 'hub.bdj@autoserve.test')->firstOrFail();
        $driver = Driver::where('driver_number', 'DRV-BDJ-005')->firstOrFail();

        $this->seedTariffs();
        ShipperAccount::firstOrCreate(['shipper_id' => $shipper2->id], ['credit_limit_idr' => 100_000_000, 'payment_terms_days' => 30, 'is_active' => true]);

        // --- Resi prabayar: (1) biasa, (2) COD, (3) berasuransi, (4) masih berjalan, (5) kepabeanan
        $s1 = $this->book($shipper1);
        $s2 = $this->book($shipper1, cod: 300_000);
        $s3 = $this->book($shipper3, insured: true, declared: 5_000_000);
        $s4 = $this->book($shipper1);
        $s5 = $this->book($shipper3);
        $p1 = $this->bookPostpaid($shipper2);

        foreach ([$s1, $s2, $s3, $p1] as $shipment) {
            $this->deliver($shipment, $driver);
        }

        // --- COD: setor di hub BDJ lalu cairkan (Alur 6-8)
        app(DepositCodCashAction::class)->execute($hubOperator, $this->hubBdj, $driver, 300_000);
        app(SettleCodAction::class)->execute(CodCollection::where('shipment_id', $s2->id)->firstOrFail());

        // --- Carrier subkontrak: leg S1 dibayar, leg S3 menunggu termin (Alur 9-10)
        $carrier = Carrier::firstOrCreate(['code' => 'CRR-SEED'], ['name' => 'PT Barito Trans Subkon', 'mode' => 'road', 'payment_terms_days' => 7, 'is_active' => true]);
        $leg1 = $this->leg($s1, 1);
        app(AssignCarrierToLegAction::class)->execute($leg1, $carrier, 40_000);
        app(CompleteShipmentLegAction::class)->execute($leg1);
        ShipmentLeg::whereKey($leg1->id)->update(['cost_accrued_at' => now()->subDays(10)]);
        app(PayCarriersAction::class)->execute($carrier, $admin->id);
        $leg3 = $this->leg($s3, 1);
        app(AssignCarrierToLegAction::class)->execute($leg3, $carrier, 35_000);
        app(CompleteShipmentLegAction::class)->execute($leg3);

        // --- Klaim S3 dengan aturan 4 mata (Alur 11)
        $claim = app(CreateClaimAction::class)->execute($dispatcher1, $s3->fresh(), 'damage', 750_000, 'Kemasan penyok, isi pecah sebagian.');
        app(SubmitClaimAction::class)->execute($dispatcher2, $claim);
        app(DecideClaimAction::class)->execute($admin, $claim, true, 750_000, 'Bukti foto dan berita acara valid.');
        app(PayClaimAction::class)->execute($admin, $claim->fresh());

        // --- Invoice pascabayar P1 dibayar (Alur 4) lalu Demurrage & Detention (Alur 12)
        $period = now()->format('Y-m');
        app(GenerateMonthlyInvoicesAction::class)->execute($period);
        $freightInvoice = LogisticsInvoice::where('shipper_id', $shipper2->id)->where('kind', 'freight')->firstOrFail();
        app(PayLogisticsInvoiceAction::class)->execute($shipper2, $freightInvoice, '123456');

        $containers = Container::orderBy('id')->limit(2)->get();
        $closed = app(StartContainerDwellAction::class)->execute($containers[0], $p1->fresh(), $this->hubBdj, 'demurrage', now()->subDays(9));
        app(EndContainerDwellAction::class)->execute($closed);
        $ddInvoice = app(GenerateDdInvoicesAction::class)->execute()->first();
        app(PayLogisticsInvoiceAction::class)->execute($shipper2, $ddInvoice, '123456');

        $open = app(StartContainerDwellAction::class)->execute($containers[1], $s4->fresh(), $this->hubBdj, 'detention', now()->subDays(8));
        app(AccrueDemurrageDetentionAction::class)->execute($open);

        // --- Bea cukai PIB untuk S5 (Alur 13)
        $s5->update(['status' => ShipmentStatus::InTransit]);
        $declaration = app(SubmitCustomsDeclarationAction::class)->execute($shipper3, $s5->fresh(), 'PIB', [['hs_code' => '85171300', 'value_idr' => 100_000_000]], true);
        app(PayCustomsDutyAction::class)->execute($shipper3, $declaration, '123456');
        app(ClearCustomsAction::class)->execute($admin, $declaration->fresh());

        // --- BBM dan satu trip terjadwal dengan reservasi kapasitas untuk S4
        $this->seedFuel($dispatcher1);
        $this->seedSchedule($dispatcher1, $s4);
    }

    private function seedTariffs(): void
    {
        $card = RateCard::firstOrCreate(
            ['name' => 'Tarif Reguler BDJ-BJB (Demo)'],
            ['origin_location_id' => $this->hubBdj->id, 'destination_location_id' => $this->hubBjb->id, 'service_level' => ServiceLevel::Regular, 'mode' => TransportMode::ROAD, 'min_charge_idr' => 15_000, 'valid_from' => '2026-01-01', 'valid_to' => null, 'is_active' => true]
        );
        RateBracket::firstOrCreate(['rate_card_id' => $card->id, 'min_weight_kg' => 0], ['max_weight_kg' => 100, 'rate_per_kg_idr' => 9_500, 'is_flat' => false]);
        Surcharge::firstOrCreate(['code' => Surcharge::CODE_FUEL], ['name' => 'Fuel Surcharge 5%', 'type' => 'percentage', 'rate' => 0.05, 'is_active' => true]);

        DdTariff::firstOrCreate(['kind' => 'demurrage', 'location_id' => null, 'size_type' => null], ['free_days' => 3, 'rate_per_day_idr' => 150_000, 'escalation_after_days' => 3, 'escalated_rate_per_day_idr' => 250_000, 'is_active' => true]);
        DdTariff::firstOrCreate(['kind' => 'detention', 'location_id' => null, 'size_type' => null], ['free_days' => 5, 'rate_per_day_idr' => 100_000, 'is_active' => true]);

        HsTariff::firstOrCreate(['hs_code' => '85171300'], ['description' => 'Telepon seluler (smartphone)', 'bm_bp' => 500, 'ppn_bp' => 1100, 'pph22_api_bp' => 250, 'pph22_non_api_bp' => 750, 'requires_inspection' => false, 'is_active' => true]);
        HsTariff::firstOrCreate(['hs_code' => '22030000'], ['description' => 'Bir malt (barang larangan terbatas)', 'bm_bp' => 1000, 'ppn_bp' => 1100, 'pph22_api_bp' => 250, 'pph22_non_api_bp' => 750, 'requires_inspection' => true, 'is_active' => true]);
    }

    private function quote(User $shipper, bool $insured, int $declared, int $cod)
    {
        return app(QuoteShipmentAction::class)->execute(
            shipper: $shipper,
            originLocationId: $this->hubBdj->id,
            destinationLocationId: $this->hubBjb->id,
            serviceLevel: ServiceLevel::Regular,
            packages: [['weight_g' => 3000, 'length_mm' => 300, 'width_mm' => 200, 'height_mm' => 150, 'description' => 'Paket demo keuangan']],
            declaredValueIdr: $declared,
            insured: $insured,
            codAmountIdr: $cod,
        );
    }

    private function book(User $shipper, bool $insured = false, int $declared = 0, int $cod = 0): Shipment
    {
        return app(BookShipmentAction::class)->execute(
            shipper: $shipper,
            quote: $this->quote($shipper, $insured, $declared, $cod),
            consigneeName: 'Budi Penerima',
            consigneePhone: '081234500001',
            consigneeAddress: ['street' => 'Jl. A. Yani Km 36', 'city' => 'Banjarbaru', 'province' => 'Kalimantan Selatan'],
            pin: '123456',
        );
    }

    private function bookPostpaid(User $shipper): Shipment
    {
        return app(BookPostpaidShipmentAction::class)->execute(
            shipper: $shipper,
            quote: $this->quote($shipper, false, 0, 0),
            consigneeName: 'PT Penerima Kalsel',
            consigneePhone: '081234500002',
            consigneeAddress: ['street' => 'Jl. Trikora 12', 'city' => 'Banjarbaru', 'province' => 'Kalimantan Selatan'],
        );
    }

    /** Selesaikan pengiriman tanpa unggahan berkas: rantai kustodi + pengakuan pendapatan + COD lewat action produksi. */
    private function deliver(Shipment $shipment, Driver $driver): void
    {
        $record = app(RecordTrackingEventAction::class);
        $shipment->update(['driver_id' => $driver->id, 'status' => ShipmentStatus::PickedUp, 'picked_up_at' => now()->subHours(6)]);
        $record->execute($shipment, 'PICKED_UP', $this->hubBdj->id, $driver->user, 'driver', 'Kargo dijemput kurir.', ['driver_id' => $driver->id]);

        $shipment->update(['status' => ShipmentStatus::Delivered, 'delivered_at' => now()->subHours(1)]);
        $record->execute($shipment, 'DELIVERED', $this->hubBjb->id, $driver->user, 'driver', 'Kargo diterima penerima.', ['driver_id' => $driver->id]);

        if ($shipment->cod_amount_idr > 0) {
            app(RecordCodCollectionAction::class)->execute($shipment->fresh(), $driver);
        }

        event(new ShipmentDelivered($shipment->fresh()));
    }

    private function leg(Shipment $shipment, int $seq): ShipmentLeg
    {
        return ShipmentLeg::firstOrCreate(
            ['shipment_id' => $shipment->id, 'leg_sequence' => $seq],
            ['mode' => 'road', 'origin_location_id' => $this->hubBdj->id, 'destination_location_id' => $this->hubBjb->id, 'estimated_departure' => now()->subHours(6), 'estimated_arrival' => now()->subHours(2), 'status' => 'pending']
        );
    }

    private function seedFuel(User $user): void
    {
        $truck = Truck::where('plate_number', 'like', 'DA%')->orderBy('id')->first();
        $base = (int) $truck->odometer_m;
        $fill = app(RecordFuelLogAction::class);

        foreach ([[300, 50], [300, 50], [300, 50], [300, 80], [300, 50]] as $i => [$km, $liters]) {
            $fill->execute($user, $truck, $liters * 1000, 15_800, $base + ($i + 1) * $km * 1000);
        }
    }

    private function seedSchedule(User $dispatcher, Shipment $shipment): void
    {
        $truck = Truck::where('required_license', 'SIM B1 Umum')->where('status', 'available')->orderByDesc('id')->firstOrFail();
        $driver = Driver::where('license_class', 'SIM B1 Umum')->where('status', 'available')->orderBy('id')->firstOrFail();

        $trip = Schedule::firstOrCreate(
            ['schedule_number' => 'TRP-DEMO-0001'],
            ['mode' => TransportMode::ROAD, 'origin_location_id' => $this->hubBdj->id, 'destination_location_id' => $this->hubBjb->id, 'etd' => now()->addDay()->setTime(8, 0), 'eta' => now()->addDay()->setTime(10, 0), 'cutoff_at' => now()->addHours(12), 'status' => ScheduleStatus::Scheduled, 'cap_weight_kg' => '5000.000', 'cap_volume_dm3' => 14000]
        );
        app(AssignScheduleResourcesAction::class)->execute($trip, $truck, $driver, $dispatcher);
        app(ReserveCapacityAction::class)->execute(scheduleId: $trip->id, weightKg: '3.000', volumeDm3: 9, idempotencyKey: 'seed-reserve-'.$shipment->id, shipmentId: $shipment->id);
    }
}
