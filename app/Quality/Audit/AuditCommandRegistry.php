<?php

declare(strict_types=1);

namespace App\Quality\Audit;

use App\Quality\Audit\Fixtures\AgriAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\AgyAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\AiAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ApiAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\AstAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\AstVerifyChainCorruptionFixture;
use App\Quality\Audit\Fixtures\B2bAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\BankReconcileCorruptionFixture;
use App\Quality\Audit\Fixtures\ChainAuditAllCorruptionFixture;
use App\Quality\Audit\Fixtures\CkAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ContractsVerifyChainCorruptionFixture;
use App\Quality\Audit\Fixtures\CoreVerifyPassportsCorruptionFixture;
use App\Quality\Audit\Fixtures\CtrAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\DistAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\DrAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EcosystemAudit12LinesCorruptionFixture;
use App\Quality\Audit\Fixtures\EnterpriseAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EpcAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EsgAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\EvAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\FltAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\GroupAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\HcmAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\HspAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\HtlAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\InsAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\IntlAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\LgxAuditBillingCorruptionFixture;
use App\Quality\Audit\Fixtures\LgxVerifyCustodyCorruptionFixture;
use App\Quality\Audit\Fixtures\MallAuditBillingCorruptionFixture;
use App\Quality\Audit\Fixtures\MfgAuditCostingCorruptionFixture;
use App\Quality\Audit\Fixtures\MfgQmsAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\MinAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\MobileAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PlmAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PricingAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\ProcAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PrpAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\PtnAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\RwaAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\SecurityAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TfAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TlmAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TowerAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TradeAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\TreasuryAuditCorruptionFixture;
use App\Quality\Audit\Fixtures\VenAuditCorruptionFixture;
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
            'ai:audit' => new AiAuditCorruptionFixture,
            'api:audit' => new ApiAuditCorruptionFixture,
            'ast:audit' => new AstAuditCorruptionFixture,
            'ast:verify-chain' => new AstVerifyChainCorruptionFixture,
            'b2b:audit' => new B2bAuditCorruptionFixture,
            'bank:reconcile' => new BankReconcileCorruptionFixture,
            'chain:audit-all' => new ChainAuditAllCorruptionFixture,
            'ck:audit' => new CkAuditCorruptionFixture,
            'contracts:verify-chain' => new ContractsVerifyChainCorruptionFixture,
            'core:verify-passports' => new CoreVerifyPassportsCorruptionFixture,
            'ctr:audit' => new CtrAuditCorruptionFixture,
            'dist:audit' => new DistAuditCorruptionFixture,
            'dr:audit' => new DrAuditCorruptionFixture,
            'ecosystem:audit-12-lines' => new EcosystemAudit12LinesCorruptionFixture,
            'enterprise:audit' => new EnterpriseAuditCorruptionFixture,
            'epc:audit' => new EpcAuditCorruptionFixture,
            'esg:audit' => new EsgAuditCorruptionFixture,
            'ev:audit' => new EvAuditCorruptionFixture,
            'flt:audit' => new FltAuditCorruptionFixture,
            'group:audit' => new GroupAuditCorruptionFixture,
            'hcm:audit' => new HcmAuditCorruptionFixture,
            'hsp:audit' => new HspAuditCorruptionFixture,
            'htl:audit' => new HtlAuditCorruptionFixture,
            'ins:audit' => new InsAuditCorruptionFixture,
            'intl:audit' => new IntlAuditCorruptionFixture,
            'lgx:audit-billing' => new LgxAuditBillingCorruptionFixture,
            'lgx:verify-custody' => new LgxVerifyCustodyCorruptionFixture,
            'mall:audit-billing' => new MallAuditBillingCorruptionFixture,
            'mfg:audit-costing' => new MfgAuditCostingCorruptionFixture,
            'mfg:qms-audit' => new MfgQmsAuditCorruptionFixture,
            'min:audit' => new MinAuditCorruptionFixture,
            'mobile:audit' => new MobileAuditCorruptionFixture,
            'plm:audit' => new PlmAuditCorruptionFixture,
            'pricing:audit' => new PricingAuditCorruptionFixture,
            'proc:audit' => new ProcAuditCorruptionFixture,
            'prp:audit' => new PrpAuditCorruptionFixture,
            'ptn:audit' => new PtnAuditCorruptionFixture,
            'rwa:audit' => new RwaAuditCorruptionFixture,
            'security:audit' => new SecurityAuditCorruptionFixture,
            'tf:audit' => new TfAuditCorruptionFixture,
            'tlm:audit' => new TlmAuditCorruptionFixture,
            'tower:audit' => new TowerAuditCorruptionFixture,
            'trade:audit' => new TradeAuditCorruptionFixture,
            'treasury:audit' => new TreasuryAuditCorruptionFixture,
            'ven:audit' => new VenAuditCorruptionFixture,
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
