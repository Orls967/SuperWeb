<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Security\BlindIndex;
use Illuminate\Console\Command;
use Modules\Hcm\Domain\Models\Employee;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Party\Domain\Models\LegalEntity;
use Modules\Party\Domain\Models\Party;

class RotateBlindIndexCommand extends Command
{
    protected $signature = 'app:rotate-blind-index
        {--old-pepper= : Nilai pepper lama untuk verifikasi transisi}
        {--dry-run : Simulasi tanpa menyimpan ke database}
        {--force : Jalankan rotasi tanpa konfirmasi interaktif}';

    protected $description = 'Rotasi pepper blind index HMAC untuk seluruh record PII terenkripsi secara idempoten';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $oldPepper = $this->option('old-pepper') ? (string) $this->option('old-pepper') : null;

        $activePepper = BlindIndex::pepper();
        if ($activePepper === null || $activePepper === '') {
            $this->error('BLIND_INDEX_PEPPER tidak terkonfigurasi pada environment.');

            return self::FAILURE;
        }

        $this->info('Memulai prosedur rotasi blind index HMAC'.($dryRun ? ' (MODE DRY-RUN)' : '').'...');
        if ($oldPepper !== null && $oldPepper !== '') {
            $this->line('Pepper lama terdeteksi untuk transisi: '.substr($oldPepper, 0, 4).'****');
        }

        if (! $dryRun && ! $force && ! $this->confirm('Apakah Anda yakin ingin memperbarui blind index seluruh entitas sensitif?', true)) {
            $this->warn('Prosedur rotasi dibatalkan.');

            return self::SUCCESS;
        }

        $summary = [];
        $grandTotalUpdated = 0;

        // 1. HCM Employees (NIK)
        $empTotal = 0;
        $empUpdated = 0;
        if (class_exists(Employee::class)) {
            $employees = Employee::all();
            $empTotal = $employees->count();
            foreach ($employees as $employee) {
                $nik = $employee->nik;
                if ($nik !== null && $nik !== '') {
                    $newBidx = BlindIndex::forNik($nik);
                    if ($employee->nik_bidx !== $newBidx) {
                        $empUpdated++;
                        if (! $dryRun) {
                            $employee->nik_bidx = $newBidx;
                            $employee->save();
                        }
                    }
                }
            }
        }
        $summary[] = ['Entitas' => 'HCM Employees (NIK)', 'Total Record' => $empTotal, 'Diperbarui' => $empUpdated];
        $grandTotalUpdated += $empUpdated;

        // 2. Mall Tenants (NPWP)
        $tenantTotal = 0;
        $tenantUpdated = 0;
        if (class_exists(Tenant::class)) {
            $tenants = Tenant::all();
            $tenantTotal = $tenants->count();
            foreach ($tenants as $tenant) {
                $npwp = $tenant->npwp;
                if ($npwp !== null && $npwp !== '') {
                    $newBidx = BlindIndex::forNpwp($npwp);
                    if ($tenant->npwp_bidx !== $newBidx) {
                        $tenantUpdated++;
                        if (! $dryRun) {
                            $tenant->npwp_bidx = $newBidx;
                            $tenant->save();
                        }
                    }
                }
            }
        }
        $summary[] = ['Entitas' => 'Mall Tenants (NPWP)', 'Total Record' => $tenantTotal, 'Diperbarui' => $tenantUpdated];
        $grandTotalUpdated += $tenantUpdated;

        // 3. Party Legal Entities (NPWP)
        $legalTotal = 0;
        $legalUpdated = 0;
        if (class_exists(LegalEntity::class)) {
            $legalEntities = LegalEntity::all();
            $legalTotal = $legalEntities->count();
            foreach ($legalEntities as $entity) {
                $npwp = $entity->npwp;
                if ($npwp !== null && $npwp !== '') {
                    $newBidx = BlindIndex::forNpwp($npwp);
                    if ($entity->npwp_bidx !== $newBidx) {
                        $legalUpdated++;
                        if (! $dryRun) {
                            $entity->npwp_bidx = $newBidx;
                            $entity->save();
                        }
                    }
                }
            }
        }
        $summary[] = ['Entitas' => 'Legal Entities (NPWP)', 'Total Record' => $legalTotal, 'Diperbarui' => $legalUpdated];
        $grandTotalUpdated += $legalUpdated;

        // 4. Party Master (NIK & NPWP)
        $partyTotal = 0;
        $partyUpdated = 0;
        if (class_exists(Party::class)) {
            $parties = Party::all();
            $partyTotal = $parties->count();
            foreach ($parties as $party) {
                $nik = $party->nik;
                $npwp = $party->npwp;
                $changed = false;

                if ($nik !== null && $nik !== '') {
                    $newNikBidx = BlindIndex::forNik($nik);
                    if ($party->nik_bidx !== $newNikBidx) {
                        $party->nik_bidx = $newNikBidx;
                        $changed = true;
                    }
                }

                if ($npwp !== null && $npwp !== '') {
                    $newNpwpBidx = BlindIndex::forNpwp($npwp);
                    if ($party->npwp_bidx !== $newNpwpBidx) {
                        $party->npwp_bidx = $newNpwpBidx;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $partyUpdated++;
                    if (! $dryRun) {
                        $party->save();
                    }
                }
            }
        }
        $summary[] = ['Entitas' => 'Party Master (NIK & NPWP)', 'Total Record' => $partyTotal, 'Diperbarui' => $partyUpdated];
        $grandTotalUpdated += $partyUpdated;

        $this->table(['Entitas', 'Total Record', 'Diperbarui'], $summary);
        $this->info("Rotasi selesai. Total record terdampak: {$grandTotalUpdated}".($dryRun ? ' (dry-run, tidak disimpan)' : ''));

        return self::SUCCESS;
    }
}
