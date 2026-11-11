<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Core\Contracts\OutboxBusInterface;
use Modules\EnterpriseFinance\Domain\Enums\SodSeverity;
use Modules\EnterpriseFinance\Domain\Events\EnterpriseSodViolationDetected;
use Modules\EnterpriseFinance\Domain\Exceptions\SodViolationException;
use Modules\EnterpriseFinance\Domain\Models\SodException;
use Psr\Clock\ClockInterface;

class SodGovernanceService
{
    /**
     * Matriks 20 Aturan Konflik Pemisahan Tugas (Segregation of Duties).
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
            'description' => 'Auditor kepatuhan dilarang memiliki hak administratif pengubahan konfigurasi sistem.',
        ],
        'SOD-13' => [
            'id' => 'SOD-13',
            'title' => 'Petugas Timbangan Tambang vs Dispatcher Angkut',
            'first_role' => 'weighbridge_operator',
            'second_role' => 'dispatcher_mine',
            'severity' => SodSeverity::HIGH,
            'description' => 'Operator jembatan timbang tambang dilarang mengatur rute dan penugasan armada angkut.',
        ],
        'SOD-14' => [
            'id' => 'SOD-14',
            'title' => 'Penginput Rekam Medis vs Penagih Klaim Asuransi',
            'first_role' => 'clinical_coder',
            'second_role' => 'insurance_billing_officer',
            'severity' => SodSeverity::HIGH,
            'description' => 'Koder diagnosis medis RS dilarang menyetujui klaim penggantian biaya asuransi.',
        ],
        'SOD-15' => [
            'id' => 'SOD-15',
            'title' => 'Petugas Front Office Hotel vs Pembuat Tarif Diskon',
            'first_role' => 'front_desk',
            'second_role' => 'revenue_manager',
            'severity' => SodSeverity::MEDIUM,
            'description' => 'Resepsionis hotel dilarang mengubah matriks tarif kamar tanpa persetujuan revenue manager.',
        ],
        'SOD-16' => [
            'id' => 'SOD-16',
            'title' => 'Operator Ticketing Venue vs Pintu Masuk Gate',
            'first_role' => 'box_office_cashier',
            'second_role' => 'turnstile_gate_scanner',
            'severity' => SodSeverity::HIGH,
            'description' => 'Penjual tiket konser/venue dilarang mengoperasikan pemindai tiket di pintu masuk.',
        ],
        'SOD-17' => [
            'id' => 'SOD-17',
            'title' => 'Pengusul Penghapusan Aset vs Pengelola Register Aset',
            'first_role' => 'asset_disposal_sponsor',
            'second_role' => 'fixed_asset_accountant',
            'severity' => SodSeverity::HIGH,
            'description' => 'Pengusul lelang aset dilarang mencatat nilai sisa buku pelepasan aset.',
        ],
        'SOD-18' => [
            'id' => 'SOD-18',
            'title' => 'Penyusun Formula Pakan/Resep vs Pembeli Bahan Baku',
            'first_role' => 'quality_formulator',
            'second_role' => 'raw_material_buyer',
            'severity' => SodSeverity::MEDIUM,
            'description' => 'Formulator resep F&B dilarang menentukan vendor rekanan penyedia bahan.',
        ],
        'SOD-19' => [
            'id' => 'SOD-19',
            'title' => 'Petugas Kliring Valas vs Trader Pasar Uang',
            'first_role' => 'treasury_settlement_officer',
            'second_role' => 'fx_trader',
            'severity' => SodSeverity::CRITICAL,
            'description' => 'Dealer valas dilarang mengonfirmasi dan mengeksekusi instruksi settlement transaksi valuta asing.',
        ],
        'SOD-20' => [
            'id' => 'SOD-20',
            'title' => 'Pengelola Log Audit Keamanan vs Pengembang Aplikasi',
            'first_role' => 'security_audit_officer',
            'second_role' => 'application_developer',
            'severity' => SodSeverity::CRITICAL,
            'description' => 'Pengembang dilarang memiliki wewenang memodifikasi atau menghapus jejak log audit forensik.',
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
     * Mengambil daftar aturan konflik pemisahan tugas yang terdaftar.
     */
    public function getRules(): array
    {
        return array_values($this->rules);
    }

    /**
     * Memeriksa apakah penugasan peran baru pada pengguna memicu benturan wewenang SoD.
     */
    public function checkAssignmentViolation(int $userId, array $currentRoles, string $newRole): ?array
    {
        foreach ($this->rules as $rule) {
            $r1 = $rule['first_role'];
            $r2 = $rule['second_role'];

            $isDirectConflict = ($newRole === $r1 && in_array($r2, $currentRoles, true));
            $isReverseConflict = ($newRole === $r2 && in_array($r1, $currentRoles, true));

            if ($isDirectConflict || $isReverseConflict) {
                // Periksa apakah ada pengecualian aktif yang sah
                $hasActiveException = SodException::query()
                    ->where('sod_rule_id', $rule['id'])
                    ->where('user_id', $userId)
                    ->where('status', 'ACTIVE')
                    ->where('expires_at', '>', $this->now())
                    ->exists();

                if (! $hasActiveException) {
                    return [
                        'rule' => $rule,
                        'conflicting_role' => $isDirectConflict ? $r2 : $r1,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Mencegah penetapan peran jika terdapat pelanggaran SoD tanpa dispensasi aktif.
     */
    public function assertCanAssignRole(int $userId, array $currentRoles, string $newRole): void
    {
        $violation = $this->checkAssignmentViolation($userId, $currentRoles, $newRole);

        if ($violation !== null) {
            $rule = $violation['rule'];
            $conflictingRole = $violation['conflicting_role'];

            $event = new EnterpriseSodViolationDetected(
                userId: $userId,
                ruleId: $rule['id'],
                firstRole: (string) $newRole,
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

    /**
     * Menerbitkan dispensasi pengecualian berbatas waktu untuk skenario darurat operasional.
     */
    public function grantException(
        string $sodRuleId,
        int $userId,
        int $grantedBy,
        string $justification,
        int $expiresDays = 14
    ): SodException {
        if (! isset($this->rules[$sodRuleId])) {
            throw new InvalidArgumentException("Aturan SoD [{$sodRuleId}] tidak terdaftar.");
        }

        if ($expiresDays < 1 || $expiresDays > 90) {
            throw new InvalidArgumentException("Masa berlaku dispensasi harus antara 1 s.d. 90 hari.");
        }

        $now = $this->now();
        $expiresAt = $now->addDays($expiresDays);

        return SodException::create([
            'sod_rule_id' => $sodRuleId,
            'user_id' => $userId,
            'granted_by' => $grantedBy,
            'justification' => $justification,
            'status' => 'ACTIVE',
            'granted_at' => $now,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Memindai dan menandai seluruh dispensasi yang telah melewati batas waktu kedaluwarsa.
     */
    public function auditExceptionExpirations(): array
    {
        $now = $this->now();
        $expiredCount = SodException::query()
            ->where('status', 'ACTIVE')
            ->where('expires_at', '<=', $now)
            ->update(['status' => 'EXPIRED']);

        return [
            'audited_at' => $now->toIso8601String(),
            'expired_exceptions_revoked' => $expiredCount,
        ];
    }
}
