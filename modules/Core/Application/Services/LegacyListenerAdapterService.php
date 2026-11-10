<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Facades\Log;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Service LegacyListenerAdapterService (DR8-6)
 *
 * Mengadaptasi listener event warisan (legacy listeners) di lintas modul
 * agar dapat dikonsumsi melalui Universal Event Spine tanpa memodifikasi kelas listener asli.
 * Mematuhi aturan KernelZeroBusinessDependency (A9): modul kernel tidak mengimpor namespace modul bisnis.
 */
class LegacyListenerAdapterService
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $registeredAdapters = [];

    public function __construct()
    {
        $this->bootstrapLegacyListeners();
    }

    /**
     * Daftarkan seluruh listener warisan lintas modul yang diinventarisasi pada L.0.
     * Menggunakan string FQCN untuk menjaga decoupling arsitektur kernel A9 = 0.
     */
    protected function bootstrapLegacyListeners(): void
    {
        $legacyMappings = [
            'store.order.paid.v1' => [
                'Modules\\Logistics\\Application\\Listeners\\CreateShipmentOnOrderPaid',
                'Modules\\Manufacturing\\Application\\Listeners\\PostSaleCogsListener',
                'Modules\\Pricing\\Application\\Listeners\\PostSalePriceLockListener',
            ],
            'lgx.shipment.delivered.v1' => [
                'Modules\\Logistics\\Application\\Listeners\\RecognizeFreightRevenueOnDelivery',
            ],
            'auto.telematics.anomaly_detected.v1' => [
                'Modules\\AutoServe\\Application\\Listeners\\CreatePredictiveMaintenanceBooking',
            ],
            'auto.booking.completed.v1' => [
                'Modules\\AutoServe\\Application\\Listeners\\RecordBookingCompletedPassportEvent',
                'Modules\\AutoServe\\Application\\Listeners\\NotifyBookingCompleted',
            ],
            'hospital.code_blue.activated.v1' => [
                'Modules\\Hospital\\Application\\Listeners\\NotifyRapidResponseTeamListener',
                'Modules\\Hospital\\Application\\Listeners\\RecordPassportAuditLogListener',
                'Modules\\Hospital\\Application\\Listeners\\UpdateBedCapacityMetricListener',
            ],
            'hotel.banquet.beo_finalized.v1' => [
                'Modules\\Hotel\\Domain\\Listeners\\NotifyBanquetKitchenOnBeoFinalizedListener',
                'Modules\\Hotel\\Domain\\Listeners\\RecordMiceLedgerJournalEntryListener',
                'Modules\\Hotel\\Domain\\Listeners\\SyncAllotmentToCrsInventoryListener',
                'Modules\\Hotel\\Domain\\Listeners\\TriggerAttritionPenaltyCheckListener',
                'Modules\\Hotel\\Domain\\Listeners\\UpdateWeddingMilestoneStatusListener',
            ],
            'payment.captured.v1' => [
                'Modules\\Payment\\Application\\Listeners\\NotifyPaymentCaptured',
            ],
            'payment.refunded.v1' => [
                'Modules\\Payment\\Application\\Listeners\\NotifyPaymentRefunded',
            ],
            'venue.ticket.resold.v1' => [
                'Modules\\Venue\\Application\\Listeners\\NotifyResaleSellerSettlementListener',
                'Modules\\Venue\\Application\\Listeners\\NotifyNextWaitlistUserListener',
                'Modules\\Venue\\Application\\Listeners\\RecordSecondaryLedgerListener',
                'Modules\\Venue\\Application\\Listeners\\HandleScalperBlacklistAuditListener',
            ],
            'esg.meter.read.v1' => [
                'Modules\\Esg\\Application\\Listeners\\RecordScopeTwoElectricityEmissionsListener',
            ],
            'banking.user.registered.v1' => [
                'Modules\\Banking\\Listeners\\CreateUserWalletListener',
            ],
            'finance.prices.ticked.v1' => [
                'Modules\\Finance\\Listeners\\MonitorLoanRisk',
            ],
            'mall.parking.cancelled.v1' => [
                'Modules\\Mall\\Listeners\\CancelParkingMembershipOnVehicleTransfer',
            ],
            'core.search.index_stale.v1' => [
                'Modules\\Core\\Application\\Listeners\\SearchIndexEventSubscriber',
            ],
            'core.vehicle.acquired.v1' => [
                'Modules\\Core\\Application\\Listeners\\RecordVehicleAcquiredPassportEvent',
            ],
        ];

        foreach ($legacyMappings as $eventName => $listenerClasses) {
            foreach ($listenerClasses as $listenerClass) {
                if (class_exists($listenerClass)) {
                    $this->registeredAdapters[$eventName][] = $listenerClass;
                    SpineConsumerRegistry::register('legacy_adapter_group', $eventName, function (SimEventSpine $spineEvent) use ($listenerClass) {
                        $this->dispatchToLegacyListener($listenerClass, $spineEvent);
                    });
                }
            }
        }
    }

    /**
     * Dispatch event spine ke listener warisan.
     */
    public function dispatchToLegacyListener(string $listenerClass, SimEventSpine $spineEvent): bool
    {
        try {
            $listener = app($listenerClass);

            if (method_exists($listener, 'handle')) {
                $listener->handle($spineEvent);

                return true;
            } elseif (is_callable($listener)) {
                $listener($spineEvent);

                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning("Gagal mendispatch ke legacy listener {$listenerClass}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Jumlah listener warisan yang berhasil didaftarkan.
     */
    public function getRegisteredListenersCount(): int
    {
        $total = 0;
        foreach ($this->registeredAdapters as $listeners) {
            $total += count($listeners);
        }

        return $total;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getAllAdapters(): array
    {
        return $this->registeredAdapters;
    }
}
