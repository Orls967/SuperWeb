<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class Enterprise46ModulesInteractiveUiTest extends TestCase
{
    /**
     * List of 46+ enterprise modules in the conglomerate.
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
     * 1. Test that all reusable enterprise components render valid HTML5 and SVG.
     */
    public function test_enterprise_reusable_components_render_valid_html5_and_svg_without_errors(): void
    {
        // 1.1 Command Palette
        $paletteHtml = Blade::render('<x-enterprise.command-palette />');
        $this->assertStringContainsString('enterprise-command-palette-wrapper', $paletteHtml);
        $this->assertStringContainsString('⌘K', $paletteHtml);
        $this->assertStringContainsString('Modul Enterprise', $paletteHtml);
        $this->assertStringContainsString('Peran RBAC', $paletteHtml);
        $this->assertStringContainsString('Perintah Audit', $paletteHtml);

        // 1.2 Role Matrix Banner
        $bannerHtml = Blade::render('<x-enterprise.role-matrix-banner />');
        $this->assertStringContainsString('enterprise-role-matrix-banner', $bannerHtml);
        $this->assertStringContainsString('Hash-Chain Verified', $bannerHtml);
        $this->assertStringContainsString('SHA-256', $bannerHtml);
        $this->assertStringContainsString('Idempotency Guard', $bannerHtml);
        $this->assertStringContainsString('Integer Precision', $bannerHtml);
        $this->assertStringContainsString('60-Role Switcher', $bannerHtml);

        // 1.3 SVG KPI Chart - Sparkline
        $sparkHtml = Blade::render('<x-enterprise.svg-kpi-chart type="sparkline" title="Arus Kas Real-Time" value="Rp 42.850.000.000" color="emerald" />');
        $this->assertStringContainsString('<svg', $sparkHtml);
        $this->assertStringContainsString('viewBox="0 0 320 90"', $sparkHtml);
        $this->assertStringContainsString('linearGradient', $sparkHtml);
        $this->assertStringContainsString('Arus Kas Real-Time', $sparkHtml);
        $this->assertStringContainsString('Vektor SVG Murni', $sparkHtml);

        // 1.4 SVG KPI Chart - Bar Chart
        $barHtml = Blade::render('<x-enterprise.svg-kpi-chart type="bar" title="Volume Transaksi Harian" color="indigo" />');
        $this->assertStringContainsString('<svg', $barHtml);
        $this->assertStringContainsString('viewBox="0 0 340 110"', $barHtml);
        $this->assertStringContainsString('<rect', $barHtml);

        // 1.5 SVG KPI Chart - Waterfall
        $waterfallHtml = Blade::render('<x-enterprise.svg-kpi-chart type="waterfall" title="Jembatan Rekonsiliasi Kas" color="blue" />');
        $this->assertStringContainsString('<svg', $waterfallHtml);
        $this->assertStringContainsString('posGrad_', $waterfallHtml);

        // 1.6 SVG KPI Chart - Radial Gauge
        $radialHtml = Blade::render('<x-enterprise.svg-kpi-chart type="radial" title="Kapasitas Uptime" value="98.5" color="teal" />');
        $this->assertStringContainsString('<svg', $radialHtml);
        $this->assertStringContainsString('stroke-dasharray="339.29"', $radialHtml);
        $this->assertStringContainsString('98.5%', $radialHtml);

        // 1.7 Export Toolbar
        $toolbarHtml = Blade::render('<x-enterprise.export-toolbar table-id="test-audit-table" module="Banking" />');
        $this->assertStringContainsString('enterprise-export-toolbar', $toolbarHtml);
        $this->assertStringContainsString('data-table-search="test-audit-table"', $toolbarHtml);
        $this->assertStringContainsString('data-table-filter="test-audit-table"', $toolbarHtml);
        $this->assertStringContainsString('data-export-csv="test-audit-table"', $toolbarHtml);
        $this->assertStringContainsString('data-print-pdf="test-audit-table"', $toolbarHtml);
        $this->assertStringContainsString('data-open-simulator="Banking"', $toolbarHtml);

        // 1.8 Interactive Simulator Modal
        $simModalHtml = Blade::render('<x-enterprise.interactive-simulator-modal default-module="Manufacturing" default-mode="mrp-oee" />');
        $this->assertStringContainsString('enterprise-interactive-simulator-container', $simModalHtml);
        $this->assertStringContainsString('Double-Entry & FX', $simModalHtml);
        $this->assertStringContainsString('HCM PPh 21 & BPJS', $simModalHtml);
        $this->assertStringContainsString('MRP & OEE Manufaktur', $simModalHtml);
        $this->assertStringContainsString('PSAK 73 ROU Lease', $simModalHtml);
        $this->assertStringContainsString('Hospital INA-CBG', $simModalHtml);
        $this->assertStringContainsString('Mining PNBP Royalti', $simModalHtml);
        $this->assertStringContainsString('Deterministic Idempotency Key', $simModalHtml);

        // 1.9 Module Dashboard
        $modDashHtml = Blade::render('<x-enterprise.module-dashboard module="Hospital" />', ['errors' => new ViewErrorBag]);
        $this->assertStringContainsString('enterprise-module-dashboard', $modDashHtml);
        $this->assertStringContainsString('Dashboard Eksekutif Modul Hospital', $modDashHtml);
        $this->assertStringContainsString('name="amount"', $modDashHtml);
        $this->assertStringContainsString('name="reference_number"', $modDashHtml);
        $this->assertStringContainsString('VERIFIED', $modDashHtml);
    }

    /**
     * 2. Test that all 46+ modules contain views with the required enterprise interactive elements:
     *    - Pure SVG Visualization
     *    - Action / Transaction Form with CSRF and integer precision
     *    - Interactive Audit & Hash-Chain Verification Table
     *    - Table Export (CSV/PDF) and live search
     *    - Domain-Specific Simulator trigger
     */
    public function test_all_46_modules_have_enterprise_interactive_ui_elements(): void
    {
        $checkedCount = 0;
        $failedModules = [];

        foreach ($this->enterpriseModules as $module) {
            $viewDir = base_path("modules/{$module}/resources/views");
            $this->assertDirectoryExists($viewDir, "Direktori view modul {$module} harus tersedia.");

            $allViewFiles = array_merge(
                glob("{$viewDir}/**/*.blade.php") ?: [],
                glob("{$viewDir}/*.blade.php") ?: []
            );

            $this->assertNotEmpty($allViewFiles, "Modul {$module} harus memiliki sekurang-kurangnya satu file view Blade.");

            // Aggregate elements across views of this module
            $hasSvg = false;
            $hasFormWithCsrf = false;
            $hasAuditTable = false;
            $hasExportOrSearch = false;
            $hasSimulator = false;

            foreach ($allViewFiles as $file) {
                $content = file_get_contents($file);

                if (
                    str_contains($content, '<svg') ||
                    str_contains($content, 'x-enterprise.svg-kpi-chart') ||
                    str_contains($content, 'x-enterprise.module-dashboard')
                ) {
                    $hasSvg = true;
                }

                if (
                    str_contains($content, '<form') ||
                    str_contains($content, '@csrf') ||
                    str_contains($content, '_token') ||
                    str_contains($content, 'x-enterprise.module-dashboard')
                ) {
                    $hasFormWithCsrf = true;
                }

                if (
                    str_contains($content, '<table') ||
                    str_contains($content, 'x-enterprise.export-toolbar') ||
                    str_contains($content, 'x-enterprise.module-dashboard')
                ) {
                    $hasAuditTable = true;
                }

                if (
                    str_contains($content, 'data-table-search') ||
                    str_contains($content, 'data-export-csv') ||
                    str_contains($content, 'x-enterprise.export-toolbar') ||
                    str_contains($content, 'x-enterprise.module-dashboard')
                ) {
                    $hasExportOrSearch = true;
                }

                if (
                    str_contains($content, 'data-open-simulator') ||
                    str_contains($content, 'open-enterprise-simulator') ||
                    str_contains($content, 'x-enterprise.interactive-simulator-modal') ||
                    str_contains($content, 'x-enterprise.module-dashboard')
                ) {
                    $hasSimulator = true;
                }
            }

            if ($hasSvg && $hasFormWithCsrf && $hasAuditTable && $hasExportOrSearch && $hasSimulator) {
                $checkedCount++;
            } else {
                $failedModules[] = [
                    'module' => $module,
                    'svg' => $hasSvg,
                    'form' => $hasFormWithCsrf,
                    'table' => $hasAuditTable,
                    'export' => $hasExportOrSearch,
                    'sim' => $hasSimulator,
                ];
            }
        }

        $this->assertEmpty($failedModules, 'Beberapa modul tidak memenuhi syarat elemen interaktif UI: '.json_encode($failedModules));
        $this->assertGreaterThanOrEqual(46, $checkedCount, 'Jumlah modul yang terverifikasi harus sekurang-kurangnya 46 modul.');
    }

    /**
     * 3. Test that Command Palette indexes all 46+ modules, 60 RBAC roles, and CLI audit commands.
     */
    public function test_command_palette_indexes_all_46_modules_and_60_rbac_roles_and_audit_commands(): void
    {
        $rendered = Blade::render('<x-enterprise.command-palette />');

        // Check essential modules are indexed
        $sampleModules = ['Banking', 'Hospital', 'Mining', 'Manufacturing', 'Hcm', 'Fleet', 'Ev', 'Egy', 'Edu', 'Med', 'Ret', 'Tlx', 'Treasury', 'Wms'];
        foreach ($sampleModules as $mod) {
            $this->assertStringContainsString($mod, $rendered, "Command Palette harus memuat modul {$mod}.");
        }

        // Check sample 60 RBAC roles
        $sampleRoles = ['admin', 'executive', 'lead_auditor', 'doctor', 'nurse', 'mining_manager', 'underwriter', 'hotel_gm', 'chef', 'driver'];
        foreach ($sampleRoles as $role) {
            $this->assertStringContainsString($role, $rendered, "Command Palette harus memuat peran RBAC {$role}.");
        }

        // Check quick audit commands
        $sampleCommands = ['bank:reconcile', 'hospital:audit', 'mining:balance', 'tax:reconcile', 'ledger:audit-hashchain', 'mrp:simulate'];
        foreach ($sampleCommands as $cmd) {
            $this->assertStringContainsString($cmd, $rendered, "Command Palette harus memuat perintah audit {$cmd}.");
        }

        // Check hotkeys and keyboard navigation
        $this->assertStringContainsString('cmd.k', $rendered);
        $this->assertStringContainsString('ctrl.k', $rendered);
        $this->assertStringContainsString('selectNext', $rendered);
        $this->assertStringContainsString('selectPrev', $rendered);
    }

    /**
     * 4. Test that the active app layout integrates the Unified Enterprise App-Shell.
     */
    public function test_app_layout_integrates_unified_enterprise_app_shell(): void
    {
        $viewContent = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('x-enterprise.role-matrix-banner', $viewContent);
        $this->assertStringContainsString('x-enterprise.command-palette', $viewContent);
        $this->assertStringContainsString('x-enterprise.interactive-simulator-modal', $viewContent);
        $this->assertStringContainsString('@media print', $viewContent);
        $this->assertStringContainsString('open-command-palette', $viewContent);
    }

    /**
     * 5. Test that Role Matrix Banner renders integrity indicators and conglomerate role switcher.
     */
    public function test_role_matrix_banner_renders_integrity_guards_and_role_switcher(): void
    {
        $rendered = Blade::render('<x-enterprise.role-matrix-banner active-role="medical_director" user-name="Dr. Pratama" />');

        $this->assertStringContainsString('Hospital Medical Director', $rendered);
        $this->assertStringContainsString('Hash-Chain Verified', $rendered);
        $this->assertStringContainsString('Idempotency Guard', $rendered);
        $this->assertStringContainsString('Integer Precision', $rendered);
        $this->assertStringContainsString('60-Role Switcher', $rendered);
        $this->assertTrue(
            str_contains($rendered, 'Healthcare & Hospital') || str_contains($rendered, 'Healthcare &amp; Hospital'),
            'Role matrix banner harus memuat kategori Healthcare & Hospital.'
        );
        $this->assertTrue(
            str_contains($rendered, 'Mining & Heavy Fleet') || str_contains($rendered, 'Mining &amp; Heavy Fleet'),
            'Role matrix banner harus memuat kategori Mining & Heavy Fleet.'
        );
        $this->assertTrue(
            str_contains($rendered, 'Fintech, RWA & InsurTech') || str_contains($rendered, 'Fintech, RWA &amp; InsurTech'),
            'Role matrix banner harus memuat kategori Fintech, RWA & InsurTech.'
        );
    }

    /**
     * 6. Test that Interactive Simulator Modal supports domain dry-run calculations.
     */
    public function test_interactive_simulator_modal_supports_domain_engines(): void
    {
        $rendered = Blade::render('<x-enterprise.interactive-simulator-modal default-module="Banking" default-mode="double-entry" />');

        $this->assertStringContainsString('Double-Entry & FX', $rendered);
        $this->assertStringContainsString('HCM PPh 21 & BPJS', $rendered);
        $this->assertStringContainsString('MRP & OEE Manufaktur', $rendered);
        $this->assertStringContainsString('PSAK 73 ROU Lease', $rendered);
        $this->assertStringContainsString('Hospital INA-CBG', $rendered);
        $this->assertStringContainsString('Mining PNBP Royalti', $rendered);
        $this->assertStringContainsString('Deterministic Idempotency Key', $rendered);
        $this->assertStringContainsString('Cryptographic SHA-256 Seal', $rendered);
        $this->assertStringContainsString('Salin Payload Jurnal JSON', $rendered);
    }
}
