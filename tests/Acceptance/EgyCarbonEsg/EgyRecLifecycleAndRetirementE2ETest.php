<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyCarbonEsg;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Egy\Application\Actions\MintRenewableEnergyCertificateAction;
use Modules\Egy\Application\Actions\RetireRecCertificateAction;
use Modules\Egy\Domain\Models\RecCertificate;
use Modules\Egy\Domain\StateMachines\RecCertificateStateMachine;
use Tests\TestCase;

final class EgyRecLifecycleAndRetirementE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_rec_minting_and_permanent_retirement_workflow(): void
    {
        $this->seed();

        $mintAction = app(MintRenewableEnergyCertificateAction::class);
        $retireAction = app(RetireRecCertificateAction::class);

        // 1. Minting REC baru: 50 MWh (50.000.000.000 Wh) dari solar farm
        $certificate = $mintAction->execute(
            generationAssetId: 101,
            sourceType: 'solar_pv',
            periodMonth: '2026-11',
            vintageYear: '2026',
            energyWh: 50_000_000_000,
            ownerPartyId: 1,
            initialTradingPriceMinor: 45_000
        );

        $this->assertSame(RecCertificateStateMachine::STATUS_MINTED, $certificate->status);
        $this->assertNotEmpty($certificate->certificate_hash);
        $this->assertNull($certificate->retired_at);

        // 2. Idempoten minting: panggil ulang dengan parameter sama mengembalikan instans yang sama
        $sameCertificate = $mintAction->execute(
            generationAssetId: 101,
            sourceType: 'solar_pv',
            periodMonth: '2026-11',
            vintageYear: '2026',
            energyWh: 50_000_000_000,
            ownerPartyId: 1
        );
        $this->assertSame($certificate->id, $sameCertificate->id);
        $this->assertSame(1, RecCertificate::count());

        // 3. Klaim pensiun (retirement) oleh tenant mall green lease
        $retired = $retireAction->execute(
            recCertificateId: (int) $certificate->id,
            beneficiaryName: 'Tenant Mall Podomoro Blok C',
            retirementReason: 'Pemenuhan Klausul Green Lease Penggunaan Listrik Surya 100%',
            claimantPartyId: 55
        );

        $this->assertSame(RecCertificateStateMachine::STATUS_RETIRED, $retired->status);
        $this->assertNotNull($retired->retired_at);
        $this->assertSame('Tenant Mall Podomoro Blok C', $retired->retirement_beneficiary);

        // 4. Pencegahan double-claim: mencoba pensiunkan ulang harus gagal
        $this->expectException(InvalidArgumentException::class);
        $retireAction->execute(
            recCertificateId: (int) $certificate->id,
            beneficiaryName: 'Pihak Lain',
            retirementReason: 'Klaim ulang yang dilarang',
            claimantPartyId: 99
        );
    }
}
