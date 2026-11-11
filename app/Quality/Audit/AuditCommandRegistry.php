<?php

declare(strict_types=1);

namespace App\Quality\Audit;

use App\Quality\Audit\Fixtures\AgriAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\AgyAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\AiAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ApiAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\AstAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\AstVerifyChainCorruptionFixture;
use App\Quality\Audit\Fixtures\AviationAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\B2bAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\BankReconcileCorruptionFixture;
use App\Quality\Audit\Fixtures\BiddingAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\BoundaryAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ChainAuditAllCorruptionFixture;
use App\Quality\Audit\Fixtures\CirAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\CircularAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\CkAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ClearingAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ContractsVerifyChainCorruptionFixture;
use App\Quality\Audit\Fixtures\CoreVerifyPassportsCorruptionFixture;
use App\Quality\Audit\Fixtures\CtrAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\DistAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\DrAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\DsAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EcosystemAudit12LinesCorruptionFixture;
use App\Quality\Audit\Fixtures\EduAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EgyAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EmbeddedAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EnterpriseAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EpcAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EsgAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EvAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\FashionAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\FltAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\FoodAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ForestAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\GovernanceAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\GroupAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\HcmAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\HspAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\HtlAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\InsAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\IntlAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\LgxAuditBillingCorruptionFixture;
use App\Quality\Audit\Fixtures\LgxVerifyCustodyCorruptionFixture;
use App\Quality\Audit\Fixtures\LifeHealthAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\LogisticsCircularCorruptionFixture;
use App\Quality\Audit\Fixtures\LogisticsColdChainCorruptionFixture;
use App\Quality\Audit\Fixtures\MallAuditBillingCorruptionFixture;
use App\Quality\Audit\Fixtures\MarineAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\MedAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\MfgAuditCostingCorruptionFixture;
use App\Quality\Audit\Fixtures\MfgQmsAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\MinAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\MiningMetalsAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\MobAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\MobileAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PlatformAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PlmAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PqAuditChainsCorruptionFixture;
use App\Quality\Audit\Fixtures\PricingAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ProcAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ProptechBimCorruptionFixture;
use App\Quality\Audit\Fixtures\ProptechFlexCorruptionFixture;
use App\Quality\Audit\Fixtures\ProptechSmartBuildingCorruptionFixture;
use App\Quality\Audit\Fixtures\ProServicesAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PrpAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PrtAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PrtFleetAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PtnAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ReinsAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\RetAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\RwaAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\SearchAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\SecurityAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\SimAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\SpineAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\SybAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TakafulAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TaskSlaAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TfAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TlmAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TlxAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TowerAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TradeAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TreasuryAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\VenAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\VenCrowdAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\VenEconomyAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\VenSecondaryAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\VmiAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\VndAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\WmAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\WmsAuditCorruptionFixture;
use Illuminate\Support\Facades\Artisan;

final class AuditCommandRegistry
{
    /**
     * Known corruption fixtures for audit commands.
     *
     * @return array<string, CorruptionFixture>
     */
    public static function fixtures(): array
    {
        return [

            'agri:audit' => new AgriAuditCorruptionFixture,
            'agy:audit' => new AgyAuditCorruptionFixture,
            'avi:audit' => new AviationAuditCorruptionFixture,
            'ai:audit' => new AiAuditCorruptionFixture,
            'api:audit' => new ApiAuditCorruptionFixture,
            'ast:audit' => new AstAuditCorruptionFixture,
            'ast:verify-chain' => new AstVerifyChainCorruptionFixture,
            'b2b:audit' => new B2bAuditCorruptionFixture,
            'bank:reconcile' => new BankReconcileCorruptionFixture,
            'bidding:audit' => new BiddingAuditCorruptionFixture,
            'boundary:audit' => new BoundaryAuditCorruptionFixture,
            'chain:audit-all' => new ChainAuditAllCorruptionFixture,
            'cir:audit' => new CirAuditCorruptionFixture,
            'ck:audit' => new CkAuditCorruptionFixture,
            'clearing:audit' => new ClearingAuditCorruptionFixture,
            'contracts:verify-chain' => new ContractsVerifyChainCorruptionFixture,
            'core:verify-passports' => new CoreVerifyPassportsCorruptionFixture,
            'ctr:audit' => new CtrAuditCorruptionFixture,
            'dist:audit' => new DistAuditCorruptionFixture,
            'dr:audit' => new DrAuditCorruptionFixture,
            'ds:audit' => new DsAuditCorruptionFixture,
            'ecosystem:audit-12-lines' => new EcosystemAudit12LinesCorruptionFixture,
            'edu:audit' => new EduAuditCorruptionFixture,
            'egy:audit' => new EgyAuditCorruptionFixture,
            'enterprise:audit' => new EnterpriseAuditCorruptionFixture,
            'epc:audit' => new EpcAuditCorruptionFixture,
            'esg:audit' => new EsgAuditCorruptionFixture,
            'ev:audit' => new EvAuditCorruptionFixture,
            'flt:audit' => new FltAuditCorruptionFixture,
            'food:audit' => new FoodAuditCorruptionFixture,
            'fsh:audit' => new FashionAuditCorruptionFixture,
            'governance:audit' => new GovernanceAuditCorruptionFixture,
            'group:audit' => new GroupAuditCorruptionFixture,
            'hcm:audit' => new HcmAuditCorruptionFixture,
            'hsp:audit' => new HspAuditCorruptionFixture,
            'hosp:audit' => new HspAuditCorruptionFixture,
            'htl:audit' => new HtlAuditCorruptionFixture,
            'hotel:audit' => new HtlAuditCorruptionFixture,
            'ins:audit' => new InsAuditCorruptionFixture,
            'ins:embedded-audit' => new EmbeddedAuditCorruptionFixture,
            'ins:life-health-audit' => new LifeHealthAuditCorruptionFixture,
            'ins:reinsurance-audit' => new ReinsAuditCorruptionFixture,
            'ins:takaful-audit' => new TakafulAuditCorruptionFixture,
            'intl:audit' => new IntlAuditCorruptionFixture,
            'lgx:audit-billing' => new LgxAuditBillingCorruptionFixture,
            'lgx:audit-circular' => new LogisticsCircularCorruptionFixture,
            'lgx:audit-coldchain' => new LogisticsColdChainCorruptionFixture,
            'lgx:verify-custody' => new LgxVerifyCustodyCorruptionFixture,
            'logi:circular-audit' => new CircularAuditCorruptionFixture,
            'mall:audit-billing' => new MallAuditBillingCorruptionFixture,
            'med:audit' => new MedAuditCorruptionFixture,
            'mfg:audit-costing' => new MfgAuditCostingCorruptionFixture,
            'mfg:qms-audit' => new MfgQmsAuditCorruptionFixture,
            'min:audit' => new MinAuditCorruptionFixture,
            'mining:audit' => new MinAuditCorruptionFixture,
            'mining:audit-metals' => new MiningMetalsAuditCorruptionFixture,
            'mob:audit' => new MobAuditCorruptionFixture,
            'mobile:audit' => new MobileAuditCorruptionFixture,
            'platform:audit' => new PlatformAuditCorruptionFixture,
            'plm:audit' => new PlmAuditCorruptionFixture,
            'pq:audit-chains' => new PqAuditChainsCorruptionFixture,
            'pricing:audit' => new PricingAuditCorruptionFixture,
            'proc:audit' => new ProcAuditCorruptionFixture,
            'prp:audit' => new PrpAuditCorruptionFixture,
            'prp:audit-bim' => new ProptechBimCorruptionFixture,
            'prp:audit-flex' => new ProptechFlexCorruptionFixture,
            'mar:audit' => new MarineAuditCorruptionFixture,
            'psv:audit' => new ProServicesAuditCorruptionFixture,
            'for:audit' => new ForestAuditCorruptionFixture,
            'prp:audit-smartbuilding' => new ProptechSmartBuildingCorruptionFixture,
            'prt:audit' => new PrtAuditCorruptionFixture,
            'prt:fleet-audit' => new PrtFleetAuditCorruptionFixture,
            'ptn:audit' => new PtnAuditCorruptionFixture,
            'ret:audit' => new RetAuditCorruptionFixture,
            'rwa:audit' => new RwaAuditCorruptionFixture,
            'search:audit' => new SearchAuditCorruptionFixture,
            'task:sla-audit' => new TaskSlaAuditCorruptionFixture,
            'security:audit' => new SecurityAuditCorruptionFixture,
            'sim:audit' => new SimAuditCorruptionFixture,
            'spine:audit' => new SpineAuditCorruptionFixture,
            'syb:audit' => new SybAuditCorruptionFixture,
            'tf:audit' => new TfAuditCorruptionFixture,
            'tlm:audit' => new TlmAuditCorruptionFixture,
            'tlx:audit' => new TlxAuditCorruptionFixture,
            'tower:audit' => new TowerAuditCorruptionFixture,
            'trade:audit' => new TradeAuditCorruptionFixture,
            'treasury:audit' => new TreasuryAuditCorruptionFixture,
            'ven:audit' => new VenAuditCorruptionFixture,
            'ven:audit-crowd' => new VenCrowdAuditCorruptionFixture('ven:audit-crowd'),
            'ven:audit-economy' => new VenEconomyAuditCorruptionFixture('ven:audit-economy'),
            'ven:audit-secondary' => new VenSecondaryAuditCorruptionFixture('ven:audit-secondary'),
            'venue:audit' => new VenAuditCorruptionFixture,
            'venue:audit-crowd' => new VenCrowdAuditCorruptionFixture('venue:audit-crowd'),
            'venue:audit-economy' => new VenEconomyAuditCorruptionFixture('venue:audit-economy'),
            'venue:audit-secondary' => new VenSecondaryAuditCorruptionFixture('venue:audit-secondary'),
            'vmi:audit' => new VmiAuditCorruptionFixture,
            'vnd:audit' => new VndAuditCorruptionFixture,
            'wm:audit' => new WmAuditCorruptionFixture,
            'wms:audit' => new WmsAuditCorruptionFixture,
        ];
    }

    /**
     * All registered Artisan commands that act as audit, reconciliation, or verification commands.
     *
     * @param  list<string>|null  $commandNames  explicit list of names (default: Artisan::all() keys)
     * @return list<string>
     */
    public static function auditCommands(?array $commandNames = null): array
    {
        $names = $commandNames ?? array_keys(Artisan::all());

        $audits = array_values(array_filter($names, static fn (string $name): bool => self::isAuditCommand($name)));
        sort($audits);

        return $audits;
    }

    public static function isAuditCommand(string $name): bool
    {
        return str_contains($name, ':audit')
            || str_ends_with($name, 'audit')
            || str_contains($name, ':reconcile')
            || str_contains($name, 'verify-')
            || str_contains($name, ':verify');
    }

    /**
     * Returns list of audit commands without a corruption fixture.
     *
     * @param  list<string>|null  $commandNames
     * @return list<string> formatted as "cmd:{name}" for set baseline
     */
    public static function commandsWithoutFixtures(?array $commandNames = null): array
    {
        $audits = self::auditCommands($commandNames);
        $fixtures = self::fixtures();

        $missing = [];
        foreach ($audits as $cmd) {
            if (! isset($fixtures[$cmd])) {
                $missing[] = "cmd:{$cmd}";
            }
        }

        sort($missing);

        return $missing;
    }
}
