<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\EnterpriseFinance\Domain\Enums\SodSeverity;
use Modules\EnterpriseFinance\Domain\Events\EnterpriseSodViolationDetected;
use Modules\EnterpriseFinance\Domain\Exceptions\SodViolationException;
use Modules\EnterpriseFinance\Domain\Models\SodException;
use Psr\Clock\ClockInterface;

class SodGovernanceService
{
    /**
     * Matriks 12 Aturan Konflik Pemisahan Tugas (Segregation of Duties).
     *
     * @var array<string, array{
     *     id: string,
     *     title: string,
     *     first_role: string,
     *     second_role: string,
     *     severity: SodSeverity,
     *     description: string
     * }>
     */
    protected array $rules = [
        'SOD-01' => [
            'id' => 'SOD-01',
            'title' => 'Pembuat PO vs Penyetuju PO',
            'first_role' => 'procurement_buyer',
            'second_role' => 'procurement_manager',
            'severity' => SodSeverity::CRITICAL,
            'description' => 'Operator dilarang merangkap sebagai pembuat pesanan pembelian dan penyetuju pesanan pembelian.',
        ],
        'SOD-02' => [
            'id' => 'SOD-02',
            'title' => 'Penginput Faktur Vendor vs Eksekutor Pembayaran',
            'first_role' => 'ap_clerk',
            'second_role' => 'treasury_disburser',
            'severity' => SodSeverity::CRITICAL,
            'description' => 'Staf hutang dagang dilarang mengeksekusi pengeluaran dana kas/bank.',
        ],
        'SOD-03' => [
            'id' => 'SOD-03',
            'title' => 'Pembuat Pengeluaran Kas vs Rekonsiliator Bank',
            'first_role' => 'cashier',
            'second_role' => 'bank_reconciler',
            'severity' => SodSeverity::HIGH,
            'description' => 'Pemegang kas kecil dilarang melakukan rekonsiliasi mutasi rekening koran.',
        ],
        'SOD-04' => [
            'id' => 'SOD-04',
            'title' => 'Pengelola Master Vendor vs Pembayar Tagihan',
            'first_role' => 'master_data_admin',
            'second_role' => 'ap_officer',
            'severity' => SodSeverity::CRITICAL,
            'description' => 'Pendaftar rekening vendor dilarang mengotorisasi pembayaran tagihan.',
        ],
        'SOD-05' => [
            'id' => 'SOD-05',
            'title' => 'Kasir POS vs Void Supervisor',
            'first_role' => 'pos_cashier',
            'second_role' => 'pos_void_admin',
            'severity' => SodSeverity::HIGH,
            'description' => 'Kasir kas toko dilarang membatalkan struk penjualan tanpa otorisasi supervisor.',
        ],
        'SOD-06' => [
            'id' => 'SOD-06',
            'title' => 'Pembuat Pagu Anggaran vs Penyetuju Revisi',
            'first_role' => 'budget_planner',
            'second_role' => 'budget_approver',
            'severity' => SodSeverity::HIGH,
            'description' => 'Penyusun draf pagu departemen dilarang mengesahkan pagu atau revisi anggarannya.',
        ],
        'SOD-07' => [
            'id' => 'SOD-07',
            'title' => 'Penerima Fisik Gudang vs Petugas Cycle Count',
            'first_role' => 'warehouse_receiver',
            'second_role' => 'inventory_controller',
            'severity' => SodSeverity::MEDIUM,
            'description' => 'Penerima barang masuk dilarang melakukan penyesuaian selisih stock opname.',
        ],
        'SOD-08' => [
            'id' => 'SOD-08',
            'title' => 'Penginput Gaji vs Otorisator Payroll',
            'first_role' => 'payroll_clerk',
            'second_role' => 'hr_finance_director',
            'severity' => SodSeverity::CRITICAL,
            'description' => 'Penginput jam lembur/upah dilarang mengeksekusi transfer batch penggajian.',
        ],
        'SOD-09' => [
            'id' => 'SOD-09',
            'title' => 'Pengaju CAPEX vs Komite Penilai',
            'first_role' => 'project_sponsor',
            'second_role' => 'capex_evaluator',
            'severity' => SodSeverity::HIGH,
            'description' => 'Pengusul proyek investasi dilarang merangkap anggota komite kelayakan finansial.',
        ],
        'SOD-10' => [
            'id' => 'SOD-10',
            'title' => 'Penagih Piutang vs Petugas Write-off',
            'first_role' => 'ar_collector',
            'second_role' => 'credit_risk_manager',
            'severity' => SodSeverity::HIGH,
            'description' => 'Kolektor piutang pelanggan dilarang menghapusbukukan piutang macet.',
        ],
        'SOD-11' => [
            'id' => 'SOD-11',
            'title' => 'Penghitung Pajak vs Penyetuju SPT',
            'first_role' => 'tax_preparer',
            'second_role' => 'tax_approver',
            'severity' => SodSeverity::MEDIUM,
            'description' => 'Staf rekonsiliasi fiskal dilarang menandatangani surat setoran pajak secara mandiri.',
        ],
        'SOD-12' => [
            'id' => 'SOD-12',
            'title' => 'Auditor Internal vs Administrator Sistem Operasional',
            'first_role' => 'internal_auditor',
            'second_role' => 'system_security_admin',
            'severity' => SodSeverity::CRITICAL,
            'description' => 'Auditor internal wajib independen dan dilarang memegang hak akses operasional produksi.',
        ],
    ];

    public function __construct(
        protected readonly ?OutboxBusInterface $outboxBus = null,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Memeriksa potensi konflik SoD saat penugasan peran baru ke pengguna.
     *
     * @param list<string> $currentRoles
     * @throws SodViolationException
     */
    public function checkRoleAssignment(int $userId, string $newRole, array $currentRoles): void
    {
        foreach ($this->rules as $rule) {
            $isConflicting = false;
            $conflictingRole = null;

            if ($newRole === $rule['first_role'] && in_array($rule['second_role'], $currentRoles, true)) {
                $isConflicting = true;
                $conflictingRole = $rule['second_role'];
            } elseif ($newRole === $rule['second_role'] && in_array($rule['first_role'], $currentRoles, true)) {
                $isConflicting = true;
                $conflictingRole = $rule['first_role'];
            }

            if ($isConflicting) {
                // Periksa apakah terdapat dispensasi/pengecualian aktif
                $hasActiveException = SodException::query()
                    ->where('user_id', $userId)
                    ->where('sod_rule_id', $rule['id'])
                    ->where('is_active', true)
                    ->where('expires_at', '>', $this->now())
                    ->exists();

                if (! $hasActiveException) {
                    // Pancarkan event deteksi konflik SoD
                    $event = new EnterpriseSodViolationDetected(
                        userId: $userId,
                        sodRuleId: $rule['id'],
                        firstRole: $newRole,
                        secondRole: (string) $conflictingRole,
                        severity: $rule['severity']->value
                    );

                    if ($this->outboxBus !== null) {
                        $this->outboxBus->record(
                            EnterpriseSodViolationDetected::EVENT_NAME,
                            $event->toPayload(),
                            "SOD:VIOLATION:{$userId}:{$newRole}"
                        );
                    }

                    throw new SodViolationException(
                        "Pelanggaran Pemisahan Tugas ({$rule['id']} - {$rule['title']}): Pengguna #{$userId} telah memiliki peran '{$conflictingRole}' dan dilarang ditugaskan peran berkonflik '{$newRole}' tanpa otorisasi pengecualian aktif."
                    );
                }
            }
        }
    }

    /**
     * Menerbitkan pengecualian/dispensasi SoD berbatas waktu dengan persetujuan manajemen.
     */
    public function grantException(
        string $ruleId,
        int $userId,
        int $grantedBy,
        string $justification,
        CarbonImmutable $expiresAt
    ): SodException {
        return SodException::create([
            'sod_rule_id' => $ruleId,
            'user_id' => $userId,
            'granted_by' => $grantedBy,
            'justification' => $justification,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
    }
}

