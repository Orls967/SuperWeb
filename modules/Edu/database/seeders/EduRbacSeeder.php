<?php

declare(strict_types=1);

namespace Modules\Edu\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Services\RbacService;

/**
 * Idempotently seeds RBAC roles and permissions for Lini 16 (Pendidikan & Talent).
 */
class EduRbacSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(RbacService::class);

        // 1. ROLES
        $roles = [
            [
                'name' => 'academy_admin',
                'label' => 'Academy Administrator',
                'description' => 'Pengelolaan kurikulum, prasyarat DAG, rombongan belajar (cohort), dan kuota B2B korporat.',
            ],
            [
                'name' => 'trainer',
                'label' => 'Academy Instructor & Trainer',
                'description' => 'Instruktur pelatihan, pengawas ujian, dan penilai asesmen proctored.',
            ],
            [
                'name' => 'student',
                'label' => 'Academy Student & Learner',
                'description' => 'Siswa pelatihan, peserta asesmen proctored, dan pemilik sertifikat kompetensi.',
            ],
            [
                'name' => 'recruiter',
                'label' => 'Corporate Talent Recruiter',
                'description' => 'Perekrut tenaga kerja industri, pemasang lowongan berbobot kompetensi, dan mitra headhunter.',
            ],
        ];

        $roleModels = [];
        foreach ($roles as $r) {
            $roleModels[$r['name']] = $svc->ensureRole($r['name'], $r['label'], $r['description']);
        }

        // 2. PERMISSIONS
        $permissions = [
            ['name' => 'edu.program.manage', 'label' => 'Kelola Program Pelatihan'],
            ['name' => 'edu.prerequisite.manage', 'label' => 'Kelola Graf Prasyarat Kurikulum'],
            ['name' => 'edu.cohort.manage', 'label' => 'Kelola Rombongan Belajar (Cohort)'],
            ['name' => 'edu.quota.manage', 'label' => 'Kelola Alokasi Kuota B2B Korporat'],
            ['name' => 'edu.quota.settle', 'label' => 'Settle Tagihan Pelatihan Korporat'],
            ['name' => 'edu.exam.grade', 'label' => 'Beri Nilai & Review Asesmen Proctored'],
            ['name' => 'edu.certificate.issue', 'label' => 'Terbitkan Sertifikat Kompetensi Digital'],
            ['name' => 'edu.certificate.verify', 'label' => 'Verifikasi Sertifikat Kompetensi Publik'],
            ['name' => 'edu.talent.match', 'label' => 'Jalankan Matching Talenta Gale-Shapley'],
            ['name' => 'edu.headhunter.settle', 'label' => 'Kelola Fee Headhunter & Garansi Escrow'],
            ['name' => 'edu.audit.view', 'label' => 'Akses & Pantau Audit Integritas Edu'],
        ];

        foreach ($permissions as $p) {
            $svc->ensurePermission($p['name'], $p['label'], 'edu');
        }

        // 3. ASSIGN PERMISSIONS TO ROLES
        $svc->grantPermissionsToRole($roleModels['academy_admin'], [
            'edu.program.manage',
            'edu.prerequisite.manage',
            'edu.cohort.manage',
            'edu.quota.manage',
            'edu.quota.settle',
            'edu.exam.grade',
            'edu.certificate.issue',
            'edu.certificate.verify',
            'edu.talent.match',
            'edu.headhunter.settle',
            'edu.audit.view',
        ]);

        $svc->grantPermissionsToRole($roleModels['trainer'], [
            'edu.cohort.manage',
            'edu.exam.grade',
            'edu.certificate.verify',
        ]);

        $svc->grantPermissionsToRole($roleModels['recruiter'], [
            'edu.certificate.verify',
            'edu.talent.match',
            'edu.headhunter.settle',
        ]);

        $svc->grantPermissionsToRole($roleModels['student'], [
            'edu.certificate.verify',
        ]);
    }
}
