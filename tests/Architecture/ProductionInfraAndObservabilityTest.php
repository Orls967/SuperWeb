<?php

declare(strict_types=1);

use App\Quality\Observability\PrometheusMetricsCollector;
use App\Quality\OpenApi\OpenApiSpecGenerator;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

it('executes php artisan metrics:prometheus successfully and outputs valid metrics for all 46 modules and 0 arch violations', function (): void {
    $exitCode = Artisan::call('metrics:prometheus');
    expect($exitCode)->toBe(0);

    $output = Artisan::output();

    // 1. Module registration invariant: exactly 46 canonical modules
    expect($output)->toContain('superweb_modules_registered_total 46');

    // 2. Arch scan violations per rule: all A1 to A13 present
    foreach (PrometheusMetricsCollector::ARCH_RULES as $rule) {
        expect($output)->toMatch("/superweb_arch_scan_violations_total\{rule=\"{$rule}\"\} \d+/");
    }

    // 3. Operational counters & gauges
    expect($output)->toMatch('/superweb_audit_commands_registered_total \d+/')
        ->and($output)->toMatch('/superweb_routes_registered_total \d+/')
        ->and($output)->toMatch('/superweb_ledger_accounts_total \d+/')
        ->and($output)->toMatch('/superweb_ledger_entries_total \d+/');

    // 4. All 46 canonical modules table counts must be present
    foreach (OpenApiSpecGenerator::CANONICAL_46_MODULES as $module) {
        expect($output)->toContain("superweb_module_tables_count{module=\"{$module}\"}");
    }
});

it('verifies /ops/metrics HTTP endpoint returns standard Prometheus exposition content type and payload', function (): void {
    $response = $this->withoutMiddleware()->get('/ops/metrics');

    $response->assertStatus(200);
    expect($response->headers->get('Content-Type'))->toContain('text/plain; version=0.0.4');

    $content = $response->getContent();
    expect($content)->toContain('superweb_modules_registered_total 46')
        ->and($content)->toMatch('/superweb_arch_scan_violations_total\{rule="A1"\} \d+/')
        ->and($content)->toContain('superweb_module_tables_count{module="AutoServe"}');
});

it('verifies multi-stage production Dockerfile exists with PHP 8.4 and production extensions', function (): void {
    $dockerfilePath = base_path('Dockerfile');
    expect(file_exists($dockerfilePath))->toBeTrue();

    $content = (string) file_get_contents($dockerfilePath);

    expect($content)->toContain('php:8.4')
        ->and($content)->toContain('pdo_mysql')
        ->and($content)->toContain('bcmath')
        ->and($content)->toContain('intl')
        ->and($content)->toContain('pcntl')
        ->and($content)->toContain('redis')
        ->and($content)->toContain('opcache')
        ->and($content)->toContain('USER www-data')
        ->and($content)->toContain('HEALTHCHECK');
});

it('verifies docker-compose.prod.yml orchestrates app, worker, scheduler, mysql 8.4, redis 7, prometheus, and grafana', function (): void {
    $composePath = base_path('docker-compose.prod.yml');
    expect(file_exists($composePath))->toBeTrue();

    $content = (string) file_get_contents($composePath);
    $parsed = Yaml::parse($content);

    expect($parsed)->toBeArray()
        ->and(isset($parsed['services']))->toBeTrue();

    $services = $parsed['services'];
    expect($services)->toHaveKeys(['app', 'worker', 'scheduler', 'mysql', 'redis', 'prometheus', 'grafana']);

    // MySQL 8.4 flags verification
    $mysqlService = $services['mysql'];
    expect($mysqlService['image'])->toBe('mysql:8.4')
        ->and($mysqlService['command'])->toContain('--innodb-lock-wait-timeout=50')
        ->and($mysqlService['command'])->toContain('--transaction-isolation=READ-COMMITTED');

    // Redis verification
    expect($services['redis']['image'])->toContain('redis:7');

    // Prometheus & Grafana verification
    expect($services['prometheus']['image'])->toContain('prom/prometheus')
        ->and($services['grafana']['image'])->toContain('grafana/grafana');
});

it('verifies GitHub Actions composer-gate.yml workflow contains static gate, sharded matrix, and chaos verification', function (): void {
    $workflowPath = base_path('.github/workflows/composer-gate.yml');
    expect(file_exists($workflowPath))->toBeTrue();

    $content = (string) file_get_contents($workflowPath);
    $parsed = Yaml::parse($content);

    expect($parsed)->toBeArray()
        ->and(isset($parsed['jobs']))->toBeTrue();

    $jobs = $parsed['jobs'];
    expect($jobs)->toHaveKeys(['static-and-architecture-gate', 'pest-sharded-matrix', 'chaos-and-concurrency-verification']);

    // Check triggers on branches
    $triggers = $parsed['on'];
    expect($triggers)->toHaveKeys(['push', 'pull_request']);
    expect($triggers['push']['branches'])->toContain('master')
        ->and($triggers['push']['branches'])->toContain('main');

    // Job 1 tasks
    expect($content)->toContain('pint --test')
        ->and($content)->toContain('php artisan arch:scan')
        ->and($content)->toContain('php artisan openapi:validate')
        ->and($content)->toContain('php artisan docs:inventory --check')
        ->and($content)->toContain('vendor/bin/pest tests/Architecture');

    // Job 3 chaos tasks
    expect($content)->toContain('platform:stress-concurrency --iterations=10')
        ->and($content)->toContain('platform:chaos-verify');
});

it('verifies ops Prometheus and Grafana configuration files exist and are valid', function (): void {
    $promPath = base_path('ops/prometheus/prometheus.yml');
    $grafanaPath = base_path('ops/grafana/dashboards/superweb-46-modules-overview.json');

    expect(file_exists($promPath))->toBeTrue()
        ->and(file_exists($grafanaPath))->toBeTrue();

    $promYaml = Yaml::parse((string) file_get_contents($promPath));
    expect($promYaml)->toBeArray()
        ->and(isset($promYaml['scrape_configs']))->toBeTrue();

    $promContent = (string) file_get_contents($promPath);
    expect($promContent)->toContain('/ops/metrics')
        ->and($promContent)->toContain('app:8000');

    $grafanaJson = json_decode((string) file_get_contents($grafanaPath), true, 512, JSON_THROW_ON_ERROR);
    expect($grafanaJson)->toBeArray()
        ->and($grafanaJson['uid'])->toBe('superweb-46-modules-overview')
        ->and(count($grafanaJson['panels']))->toBeGreaterThan(5);
});
