<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Models\AuditLog;

class SystemHealthService
{
    /**
     * Jalankan diagnosa kesehatan sistem menyeluruh di 7 pilar utama platform.
     *
     * @return array{
     *     status: string,
     *     timestamp: string,
     *     duration_ms: float,
     *     checks: array<string, array{name: string, ok: bool, status: string, message: string, latency_ms?: float}>,
     *     audit_log_id: int|null
     * }
     */
    public function check(?User $user = null): array
    {
        $startTime = microtime(true);
        $checks = [];

        // 1. Database Connection
        try {
            $dbStart = microtime(true);
            $pdo = DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);

            $checks['database'] = [
                'name' => 'Koneksi Database Primary',
                'ok' => true,
                'status' => 'HEALTHY',
                'message' => "Driver: {$driver}, terhubung aktif ({$dbLatency} ms)",
                'latency_ms' => $dbLatency,
            ];
        } catch (\Throwable $e) {
            $checks['database'] = [
                'name' => 'Koneksi Database Primary',
                'ok' => false,
                'status' => 'UNHEALTHY',
                'message' => 'Gagal terhubung database: '.$e->getMessage(),
            ];
        }

        // 2. Cache System
        try {
            $cacheStart = microtime(true);
            $testKey = 'health_ping_'.uniqid();
            Cache::put($testKey, 'health_pong', 10);
            $cachedVal = Cache::get($testKey);
            Cache::forget($testKey);
            $cacheLatency = round((microtime(true) - $cacheStart) * 1000, 2);

            $isCacheOk = ($cachedVal === 'health_pong');
            $checks['cache'] = [
                'name' => 'Cache & In-Memory Store',
                'ok' => $isCacheOk,
                'status' => $isCacheOk ? 'HEALTHY' : 'UNHEALTHY',
                'message' => $isCacheOk ? "Read/Write cache sukses ({$cacheLatency} ms)" : 'Data cache tidak sesuai',
                'latency_ms' => $cacheLatency,
            ];
        } catch (\Throwable $e) {
            $checks['cache'] = [
                'name' => 'Cache & In-Memory Store',
                'ok' => false,
                'status' => 'UNHEALTHY',
                'message' => 'Cache error: '.$e->getMessage(),
            ];
        }

        // 3. Storage Permissions & Filesystem
        try {
            $paths = [
                storage_path(),
                storage_path('framework/views'),
                storage_path('logs'),
            ];

            $allWritable = true;
            foreach ($paths as $path) {
                if (! is_dir($path) || ! is_writable($path)) {
                    $allWritable = false;
                    break;
                }
            }

            $checks['storage'] = [
                'name' => 'Izin Akses Direktori Storage',
                'ok' => $allWritable,
                'status' => $allWritable ? 'HEALTHY' : 'UNHEALTHY',
                'message' => $allWritable ? 'Seluruh direktori storage writable' : 'Direktori storage tidak memiliki izin tulis',
            ];
        } catch (\Throwable $e) {
            $checks['storage'] = [
                'name' => 'Izin Akses Direktori Storage',
                'ok' => false,
                'status' => 'UNHEALTHY',
                'message' => 'Pemeriksaan storage gagal: '.$e->getMessage(),
            ];
        }

        // 4. Double-Entry Ledger Reconcile (bank:reconcile)
        try {
            $exitCode = Artisan::call('bank:reconcile');
            $isOk = ($exitCode === 0);

            $checks['ledger'] = [
                'name' => 'Konsistensi Double-Entry Ledger',
                'ok' => $isOk,
                'status' => $isOk ? 'HEALTHY' : 'UNHEALTHY',
                'message' => $isOk ? 'Buku besar seimbang (0 diskrepansi saldo di seluruh akun)' : 'Terdeteksi selisih saldo pada double-entry ledger',
            ];
        } catch (\Throwable $e) {
            $checks['ledger'] = [
                'name' => 'Konsistensi Double-Entry Ledger',
                'ok' => false,
                'status' => 'UNHEALTHY',
                'message' => 'Gagal menjalankan audit ledger: '.$e->getMessage(),
            ];
        }

        // 5. Vehicle Passport Cryptographic Hash-Chain Integrity (core:verify-passports)
        try {
            $exitCode = Artisan::call('core:verify-passports');
            $isOk = ($exitCode === 0);

            $checks['passports'] = [
                'name' => 'Integritas Hash-Chain Paspor Kendaraan',
                'ok' => $isOk,
                'status' => $isOk ? 'HEALTHY' : 'UNHEALTHY',
                'message' => $isOk ? 'Seluruh rantai kriptografis valid dan bebas manipulasi' : 'Ditemukan kerusakan hash-chain pada paspor kendaraan',
            ];
        } catch (\Throwable $e) {
            $checks['passports'] = [
                'name' => 'Integritas Hash-Chain Paspor Kendaraan',
                'ok' => false,
                'status' => 'UNHEALTHY',
                'message' => 'Gagal memverifikasi rantai paspor: '.$e->getMessage(),
            ];
        }

        // 6. Mall Billing vs Double-Entry Ledger Audit (mall:audit-billing)
        try {
            $exitCode = Artisan::call('mall:audit-billing');
            $isOk = ($exitCode === 0);

            $checks['mall_billing'] = [
                'name' => 'Audit Tagihan & Revenue Mall',
                'ok' => $isOk,
                'status' => $isOk ? 'HEALTHY' : 'UNHEALTHY',
                'message' => $isOk ? 'Penagihan invoice mall sinkron dengan pendapatan ledger' : 'Ditemukan diskrepansi pembayaran invoice mall vs ledger',
            ];
        } catch (\Throwable $e) {
            $checks['mall_billing'] = [
                'name' => 'Audit Tagihan & Revenue Mall',
                'ok' => false,
                'status' => 'UNHEALTHY',
                'message' => 'Gagal audit invoice mall: '.$e->getMessage(),
            ];
        }

        // 7. Resto Shift & Daily Balance Check (resto:close-day --check)
        try {
            $exitCode = Artisan::call('resto:close-day', ['--check' => true]);
            $isOk = ($exitCode === 0);

            $checks['resto_shifts'] = [
                'name' => 'Operasional & Shift Kasir Resto',
                'ok' => $isOk,
                'status' => $isOk ? 'HEALTHY' : 'UNHEALTHY',
                'message' => $isOk ? 'Validasi rekonsiliasi kasir dan pergeseran shift konsisten' : 'Ditemukan ketidakcocokan pergeseran kasir resto',
            ];
        } catch (\Throwable $e) {
            $checks['resto_shifts'] = [
                'name' => 'Operasional & Shift Kasir Resto',
                'ok' => false,
                'status' => 'UNHEALTHY',
                'message' => 'Gagal memeriksa shift resto: '.$e->getMessage(),
            ];
        }

        // 8. Logistics Billing & Chain of Custody (lgx:audit-billing + lgx:verify-custody)
        try {
            $auditExit = Artisan::call('lgx:audit-billing');
            $custodyExit = Artisan::call('lgx:verify-custody');
            $isOk = ($auditExit === 0 && $custodyExit === 0);

            $checks['logistics'] = [
                'name' => 'Logistik: Billing & Rantai Kustodi',
                'ok' => $isOk,
                'status' => $isOk ? 'HEALTHY' : 'UNHEALTHY',
                'message' => $isOk
                    ? 'Audit billing logistik dan integritas chain-of-custody terverifikasi'
                    : 'Ditemukan diskrepansi pada billing logistik atau chain-of-custody',
            ];
        } catch (\Throwable $e) {
            $checks['logistics'] = [
                'name' => 'Logistik: Billing & Rantai Kustodi',
                'ok' => false,
                'status' => 'UNHEALTHY',
                'message' => 'Gagal memeriksa logistik: '.$e->getMessage(),
            ];
        }

        // Overall status
        $allPassed = ! in_array(false, array_column($checks, 'ok'), true);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Record Audit Log
        $auditLog = null;
        try {
            $auditLog = AuditLog::record(
                action: 'system.health_check',
                auditable: null,
                context: [
                    'overall_status' => $allPassed ? 'HEALTHY' : 'UNHEALTHY',
                    'duration_ms' => $duration,
                    'checks_summary' => array_map(fn ($c) => [
                        'status' => $c['status'],
                        'message' => $c['message'],
                    ], $checks),
                ],
                user: $user
            );
        } catch (\Throwable) {
            // Ignore audit log record failure to avoid cascade error
        }

        return [
            'status' => $allPassed ? 'HEALTHY' : 'UNHEALTHY',
            'timestamp' => now()->toIso8601String(),
            'duration_ms' => $duration,
            'checks' => $checks,
            'audit_log_id' => $auditLog?->id,
        ];
    }
}
