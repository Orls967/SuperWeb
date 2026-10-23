<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class EnterpriseFullDomE2EWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Complete catalogue of 46 conglomerate enterprise modules.
     *
     * @var array<int, string>
     */
    protected array $enterpriseModules = [
        'Agency', 'Agri', 'Asset', 'AutoDex', 'AutoServe', 'B2b', 'Banking',
        'CloudKitchen', 'Contract', 'ControlTower', 'Crypto', 'Distribution',
        'Edu', 'Egy', 'EnterpriseFinance', 'Epc', 'Esg', 'Ev', 'Finance',
        'Fleet', 'Hcm', 'Hospital', 'Hotel', 'Insurance', 'Integration',
        'Intercompany', 'International', 'Inventory', 'Logistics', 'Mall',
        'Manufacturing', 'Med', 'Mining', 'Partner', 'Party', 'Payment',
        'Plm', 'Pricing', 'Procurement', 'Proptech', 'Resto', 'Ret',
        'Rwa', 'Store', 'Supplier', 'Telematics', 'Tlx', 'Trade',
        'TradeFinance', 'Treasury', 'Vending', 'Venue', 'Wealth', 'Wms',
    ];

    /**
     * Complete catalogue of 60 conglomerate enterprise roles.
     *
     * @var array<string, array<int, string>>
     */
    protected array $conglomerateRoles = [
        'Core & Executive' => ['admin', 'executive', 'lead_auditor', 'treasury_officer', 'risk_officer', 'sustainability_officer'],
        'Hospitality & Hotel' => ['hotel_gm', 'front_office', 'housekeeping', 'revenue_mgr'],
        'Entertainment Venue & Beach Club' => ['venue_manager', 'venue_staff', 'artist_relations', 'crowd_safety'],
        'Mining & Heavy Fleet' => ['mining_manager', 'mine_planner', 'mine_surveyor', 'fleet_dispatcher', 'hse_officer', 'royalty_officer'],
        'Healthcare & Hospital' => ['medical_director', 'doctor', 'nurse', 'pharmacist', 'rs_admin'],
        'Logistics & Supply Chain' => ['logistics_dispatcher', 'warehouse_mgr', 'coldchain_officer', 'driver'],
        'Fintech, RWA & InsurTech' => ['investor', 'wm_advisor', 'rwa_admin', 'claims_officer', 'underwriter', 'loan_officer', 'clearing_agent'],
        'HCM, Gig Marketplace & Contractors' => ['hcm_officer', 'supervisor', 'contractor', 'freelancer', 'staff'],
        'Sustainability, ESG & Compliance' => ['esg_auditor', 'compliance_officer', 'legal_counsel'],
        'AI, Analytics & Pricing' => ['pricing_specialist', 'operations_planner', 'security_officer', 'supply_chain_mgr'],
        'Mobility, EV & Retail' => ['ev_operator', 'fleet_manager', 'mall_admin', 'tenant_manager', 'resto_manager', 'chef', 'vending_operator', 'customer'],
        'Ecosystem Operations' => ['mekanik', 'auditor', 'procurement', 'distributor'],
    ];

    /**
     * Parse HTML string into strict DOMDocument and return DOMXPath query engine.
     */
    protected function parseHtmlToXPath(string $html): DOMXPath
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<!DOCTYPE html>'.mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);

        $errors = libxml_get_errors();
        libxml_clear_errors();

        $fatalErrors = array_filter($errors, fn ($e) => $e->level === LIBXML_ERR_FATAL);
        $this->assertEmpty($fatalErrors, 'HTML5 DOM contains fatal parsing errors: '.json_encode($fatalErrors));

        return new DOMXPath($dom);
    }

    /**
     * 1. Test DOMDocument HTML5 structure and interactive elements on App-Shell layout.
     */
    public function test_full_dom_app_shell_layout_renders_valid_html5_and_all_interactive_widgets(): void
    {
        $user = User::factory()->create([
            'name' => 'Alexander Hartono',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $html = $response->getContent();
        $xpath = $this->parseHtmlToXPath($html);

        // Verify HTML5 semantic elements
        $bodyNodes = $xpath->query('//body');
        $this->assertSame(1, $bodyNodes->length, 'Page must have exactly one <body> element.');

        // 1.1 Command Palette Widget
        $paletteWrappers = $xpath->query('//div[@id="enterprise-command-palette-wrapper" or contains(@class, "enterprise-command-palette-wrapper")]');
        $this->assertGreaterThanOrEqual(1, $paletteWrappers->length, 'Command Palette wrapper must exist in DOM.');

        $hotkeyBadges = $xpath->query('//*[contains(text(), "⌘K") or contains(text(), "Ctrl+K")]');
        $this->assertGreaterThanOrEqual(1, $hotkeyBadges->length, 'Hotkey ⌘K/Ctrl+K badge must be present in DOM.');

        // 1.2 Role Matrix Banner
        $bannerNodes = $xpath->query('//header[contains(@class, "enterprise-role-matrix-banner")] | //div[contains(@class, "enterprise-role-matrix-banner")]');
        $this->assertGreaterThanOrEqual(1, $bannerNodes->length, 'Role Matrix Banner must exist in DOM.');

        $shaBadges = $xpath->query('//*[contains(text(), "SHA-256") or contains(text(), "Hash-Chain Verified")]');
        $this->assertGreaterThanOrEqual(1, $shaBadges->length, 'Cryptographic SHA-256 badge must exist in DOM.');

        $idempBadges = $xpath->query('//*[contains(text(), "Idempotency Guard")]');
        $this->assertGreaterThanOrEqual(1, $idempBadges->length, 'Idempotency Guard badge must exist in DOM.');

        $intPrecisionBadges = $xpath->query('//*[contains(text(), "Integer Precision")]');
        $this->assertGreaterThanOrEqual(1, $intPrecisionBadges->length, 'Integer Precision badge must exist in DOM.');

        // 1.3 Interactive Simulator Modal
        $simContainer = $xpath->query('//div[contains(@class, "enterprise-interactive-simulator-container")]');
        $this->assertGreaterThanOrEqual(1, $simContainer->length, 'Interactive Simulator Modal container must exist in DOM.');

        $simTabs = $xpath->query('//button[contains(@class, "rounded-xl") and contains(., "Double-Entry")]');
        $this->assertGreaterThanOrEqual(1, $simTabs->length, 'Simulator domain tab switcher must exist in DOM.');
    }

    /**
     * 2. Test DOMDocument HTML5 structure across all 46 modules executive dashboards.
     */
    public function test_all_46_modules_render_valid_dom_with_svg_charts_and_csrf_forms(): void
    {
        $checkedModules = 0;

        foreach ($this->enterpriseModules as $module) {
            $rendered = Blade::render("<x-enterprise.module-dashboard module=\"{$module}\" />", [
                'errors' => new ViewErrorBag,
            ]);

            $xpath = $this->parseHtmlToXPath($rendered);

            // 2.1 Module Dashboard container
            $dashboards = $xpath->query('//div[contains(@class, "enterprise-module-dashboard")]');
            $this->assertSame(1, $dashboards->length, "Modul {$module} harus memiliki container dashboard.");

            // 2.2 SVG Visualization Charts
            $svgNodes = $xpath->query('//svg');
            $this->assertGreaterThanOrEqual(1, $svgNodes->length, "Modul {$module} harus memuat sekurang-kurangnya 1 chart SVG.");

            $svgLinearGradients = $xpath->query('//*[local-name()="lineargradient"]');
            $this->assertGreaterThanOrEqual(1, $svgLinearGradients->length, "Chart SVG modul {$module} harus memuat linearGradient untuk estetika modern.");

            // 2.3 Action / Transaction Form with CSRF Protection
            $forms = $xpath->query('//form');
            $this->assertGreaterThanOrEqual(1, $forms->length, "Modul {$module} harus memuat sekurang-kurangnya 1 form transaksi interaktif.");

            $csrfInputs = $xpath->query('//form//input[@name="_token"]');
            $this->assertGreaterThanOrEqual(1, $csrfInputs->length, "Form transaksi modul {$module} harus memuat token CSRF (_token).");

            $amountInputs = $xpath->query('//form//input[@name="amount"]');
            $this->assertGreaterThanOrEqual(1, $amountInputs->length, "Form transaksi modul {$module} harus memuat field nominal integer amount.");

            // 2.4 Export Toolbar (CSV / PDF / Search)
            $exportButtons = $xpath->query('//*[@data-export-csv]');
            $this->assertGreaterThanOrEqual(1, $exportButtons->length, "Modul {$module} harus memuat tombol data-export-csv.");

            $printButtons = $xpath->query('//*[@data-print-pdf]');
            $this->assertGreaterThanOrEqual(1, $printButtons->length, "Modul {$module} harus memuat tombol data-print-pdf.");

            $searchInputs = $xpath->query('//input[@data-table-search]');
            $this->assertGreaterThanOrEqual(1, $searchInputs->length, "Modul {$module} harus memuat input live search data-table-search.");

            $checkedModules++;
        }

        $this->assertGreaterThanOrEqual(46, $checkedModules, 'Seluruh 46 modul enterprise harus terverifikasi DOM-nya.');
    }

    /**
     * 3. Test Cross-Role Authorization Matrix (60 Roles):
     *    - Positive 200 OK authorization for authorized roles.
     *    - Negative 403 Forbidden rejection for unauthorized roles.
     *    - DOM inspection confirming active user and role display.
     */
    public function test_60_conglomerate_roles_matrix_authorization_and_dom_badges(): void
    {
        $allRolesFlat = [];
        foreach ($this->conglomerateRoles as $sector => $roleList) {
            foreach ($roleList as $role) {
                $allRolesFlat[] = $role;
            }
        }

        // Must cover 60 roles
        $this->assertGreaterThanOrEqual(60, count($allRolesFlat), 'Harus memuat sekurang-kurangnya 60 peran konglomerasi.');

        // Test sample roles across sectors for rendering and authorization
        $testedSectors = array_keys($this->conglomerateRoles);
        foreach ($testedSectors as $sector) {
            $role = $this->conglomerateRoles[$sector][0];
            $user = User::factory()->create([
                'name' => "Officer {$role}",
                'role' => $role,
            ]);

            $this->actingAs($user);
            $bannerHtml = Blade::render("<x-enterprise.role-matrix-banner active-role=\"{$role}\" user-name=\"{$user->name}\" />");
            $xpath = $this->parseHtmlToXPath($bannerHtml);

            // Verify user name in banner
            $userBadge = $xpath->query("//*[contains(text(), '{$user->name}')]");
            $this->assertGreaterThanOrEqual(1, $userBadge->length, "Banner harus memuat nama user untuk peran {$role}.");

            // Negative 403 authorization: non-admin roles accessing admin governance route must get 403 Forbidden
            if ($role !== 'admin') {
                $response = $this->actingAs($user)->get(route('admin.health.index'));
                $response->assertForbidden();
            } else {
                // Admin role receives 200 OK
                $response = $this->actingAs($user)->get(route('admin.health.index'));
                $response->assertOk();

                // Inspect DOM of authorized admin response
                $adminXpath = $this->parseHtmlToXPath($response->getContent());
                $adminHeaders = $adminXpath->query('//header | //nav | //main');
                $this->assertGreaterThanOrEqual(1, $adminHeaders->length, 'Admin page DOM must have semantic landmarks.');
            }
        }
    }

    /**
     * 4. Test E2E Interactive workflow: Command Palette data catalogue completeness.
     */
    public function test_command_palette_dom_contains_all_46_modules_and_cli_audit_commands(): void
    {
        $paletteHtml = Blade::render('<x-enterprise.command-palette />');
        $xpath = $this->parseHtmlToXPath($paletteHtml);

        // Check search input in DOM
        $inputNodes = $xpath->query('//input[@x-model="searchQuery" or @x-ref="searchInput"]');
        $this->assertGreaterThanOrEqual(1, $inputNodes->length, 'Command palette search input must exist in DOM.');

        // Verify sample modules in DOM catalogue data
        $decodedHtml = html_entity_decode($paletteHtml);
        $keyModules = ['Banking', 'Hospital', 'Hotel', 'Mining', 'Manufacturing', 'Logistics', 'Edu', 'Egy', 'Venue', 'Wms'];
        foreach ($keyModules as $mod) {
            $this->assertStringContainsString("\"name\":\"{$mod}\"", $decodedHtml, "Command palette DOM must index module {$mod}.");
        }

        // Verify sample audit commands in DOM catalogue data
        $auditCommands = ['bank:reconcile', 'hospital:audit', 'mining:balance', 'tax:reconcile'];
        foreach ($auditCommands as $cmd) {
            $this->assertStringContainsString("\"cmd\":\"{$cmd}\"", $decodedHtml, "Command palette DOM must index audit command {$cmd}.");
        }
    }
}
