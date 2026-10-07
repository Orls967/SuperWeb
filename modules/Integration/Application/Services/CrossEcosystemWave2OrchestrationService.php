<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Modules\Edu\Application\Services\AcademyAndCertificationService;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Integration\Domain\Events\CrossEcosystemWave2Event;
use Modules\Med\Application\Services\DistributionAndAdvertisingService;
use Modules\Ret\Application\Services\FulfillmentAndQuickCommerceService;
use Modules\Ret\Application\Services\OmnichannelRetailService;
use Modules\Ret\Application\Services\SuperAppAndCashbackService;
use Modules\Tlx\Application\Services\IspAndMobileServices;

class CrossEcosystemWave2OrchestrationService
{
    public function __construct(
        protected EnergyGridAndMeteringService $egyService,
        protected IspAndMobileServices $tlxService,
        protected DistributionAndAdvertisingService $medService,
        protected AcademyAndCertificationService $eduService,
        protected OmnichannelRetailService $retOmniService,
        protected SuperAppAndCashbackService $retSuperAppService,
        protected FulfillmentAndQuickCommerceService $retFulfillmentService
    ) {}

    /**
     * Executes an end-to-end multi-line single-day cycle across all 17 lines / Wave 2:
     * 1. Energy: smart meter reading for venue/mall -> bill generated
     * 2. Telecom: ISP bill settled via Payment Hub
     * 3. Media: Ad campaign impression verified & yield settled
     * 4. Edu: Course completed & verifiable certificate issued
     * 5. Retail: Multi-channel item purchased -> dark store fulfillment dispatched -> crowdshipping delivered -> cashback awarded
     * 6. Event spine: cross-system event dispatched
     */
    public function executeFullDayCycle(array $context): array
    {
        $eventsDispatched = [];

        // 1. Energy smart meter
        $smartMeter = $this->egyService->registerSmartMeter([
            'meter_serial_number' => $context['meter_code'] ?? 'MTR-VENT-01',
            'consumer_property_type' => 'VENUE',
            'consumer_property_id' => 'VENUE-STADIUM-01',
        ]);

        $meterReading = $this->egyService->recordMeterReading(
            meterId: $smartMeter->id,
            kwh: 1250.0,
            isPeakHour: true,
            start: Carbon::now()->subHours(4),
            end: Carbon::now()
        );
        $eventsDispatched[] = 'egy.demand_surge';
        Event::dispatch(new CrossEcosystemWave2Event('egy.demand_surge', ['meter' => $smartMeter->meter_serial_number], 'EGY'));

        // 2. Telecom ISP usage & billing
        $ispSub = $this->tlxService->createIspSubscription([
            'customer_id' => 'CUST-VENUE-HOLDING',
            'plan_type' => 'B2B_DEDICATED',
            'speed_mbps' => 1000.0,
            'monthly_usage_cap_gb' => 10000.0,
            'monthly_fee_minor' => 150000000,
            'billing_type' => 'POSTPAID',
        ]);
        $eventsDispatched[] = 'tlx.isp_billed';
        Event::dispatch(new CrossEcosystemWave2Event('tlx.isp_billed', ['sub' => $ispSub->subscription_code], 'TLX'));

        // 3. Media ad campaign verified yield settlement
        $channel = $this->medService->createChannel([
            'name' => 'Venue Digital Screens Network',
            'channel_type' => 'DIGITAL_OOH',
            'revenue_share_pct' => 75.0,
        ]);
        $adCampaign = $this->medService->bookCampaign([
            'advertiser_entity_id' => 'GLOBAL-SPONSOR-X',
            'channel_id' => $channel->id,
            'target_impressions' => 100000,
            'floor_cpm_minor' => 5000000,
            'actual_cpm_minor' => 8000000,
        ]);
        $settledAd = $this->medService->settleCampaignImpressions($adCampaign->id, 100000);
        $eventsDispatched[] = 'med.campaign_settled';
        Event::dispatch(new CrossEcosystemWave2Event('med.campaign_settled', ['campaign' => $settledAd->campaign_code], 'MED'));

        // 4. Edu program & verifiable certificate
        $program = $this->eduService->createProgram([
            'title' => 'Venue Safety & Crowd Management Masterclass',
            'industry_sector' => 'HOSPITALITY',
            'total_sessions' => 6,
            'tuition_fee_minor' => 300000000,
        ]);
        $cohort = $this->eduService->openCohort([
            'program_id' => $program->id,
            'instructor_party_id' => 'INSTR-CHIEF-01',
            'start_date' => Carbon::now()->subDays(10)->toDateString(),
            'end_date' => Carbon::now()->toDateString(),
        ]);
        $enrollment = $this->eduService->enrollStudent([
            'student_party_id' => 'STUDENT-CREW-77',
            'cohort_id' => $cohort->id,
            'amount_paid_minor' => 300000000,
        ]);
        $certificate = $this->eduService->issueCertificate([
            'enrollment_id' => $enrollment->id,
            'student_party_id' => 'STUDENT-CREW-77',
            'program_id' => $program->id,
            'skill_competency_code' => 'VENUE_CROWD_SAFETY_OFFICER',
            'expires_at' => Carbon::now()->addYears(2)->toDateString(),
        ]);
        $eventsDispatched[] = 'edu.cert_issued';
        Event::dispatch(new CrossEcosystemWave2Event('edu.cert_issued', ['cert' => $certificate->certificate_number], 'EDU'));

        // 5. Retail Omnichannel order & Quick Commerce delivery
        $this->retOmniService->registerInventoryItem([
            'sku' => 'CONCERT-OFFICIAL-HOODIE-L',
            'product_name' => 'Official Band Hoodie Size L',
            'stock_available' => 50,
            'map_price_minor' => 75000000,
        ]);
        $reservedItem = $this->retOmniService->reserveAndSellInventory('CONCERT-OFFICIAL-HOODIE-L', 1);

        $qcOrder = $this->retFulfillmentService->createQuickCommerceOrder([
            'order_code' => 'ORD-QC-DAY-001',
            'dark_store_id' => 'DS-STADIUM-POPUP',
            'customer_id' => 'CUST-FAN-99',
            'placed_at' => Carbon::now()->subMinutes(15),
            'picking_duration_seconds' => 120,
        ]);
        $deliveredOrder = $this->retFulfillmentService->recordDeliveryArrival($qcOrder->order_code, Carbon::now());

        // 6. Retail SuperApp cashback issuance
        $cashback = $this->retSuperAppService->awardCashback(
            customerId: 'CUST-FAN-99',
            sourceLine: 'VENUE_MERCH',
            txAmountMinor: 75000000,
            cashbackPct: 10.0
        );
        $eventsDispatched[] = 'ret.cashback_issued';
        Event::dispatch(new CrossEcosystemWave2Event('ret.cashback_issued', ['cashback' => $cashback->cashback_code], 'RET'));

        return [
            'status' => 'SUCCESS',
            'events_dispatched' => $eventsDispatched,
            'meter_id' => $meterReading->id,
            'isp_code' => $ispSub->subscription_code,
            'ad_spend_minor' => $settledAd->total_spend_minor,
            'certificate_hash' => $certificate->certificate_hash,
            'qc_delivered' => $deliveredOrder->status,
            'cashback_earned_minor' => $cashback->cashback_earned_minor,
        ];
    }
}
