# Panduan Pengembang Tata Kelola Akses & Multi-tenansi (Fase 188)

Dokumen ini merupakan panduan teknis bagi pengembang internal SuperWeb untuk memanfaatkan modul tata kelola akses, kebijakan ABAC *Deny-Overrides*, grafik identitas multi-pihak, agregasi risiko eksposur vendor lintas-lini, dan pembuktian isolasi multi-tenansi harian.

---

## 1. Arsitektur ABAC & Resolusi Deny-Overrides (W8-D53)

Sistem otorisasi SuperWeb menggunakan paradigma **Attribute-Based Access Control (ABAC)** yang dievaluasi dengan algoritma kombinasi **Deny-Overrides** (XACML 3.0 compliant).

### Prinsip Utama
1. **Deny-Overrides**: Jika terdapat minimal satu aturan atau kebijakan yang mengevaluasi kondisi menjadi `DENY`, maka keputusan akhir MUTLAK `DENY`, mengabaikan berapa pun aturan yang memberikan `PERMIT`.
2. **Default Deny**: Jika tidak ada kebijakan yang cocok (`NOT_APPLICABLE`), akses ditolak secara aman (*safe default deny*).
3. **In-Memory Bitwise Cache**: Hasil evaluasi disimpan dalam `AbacMemoryCache` dengan partisi per tenant dan per subjek untuk mencapai latensi evaluasi sub-milidetik (< 0.5 ms).

### Contoh Penggunaan Layanan Otorisasi

```php
use Modules\Core\Application\Access\AbacPolicyService;

class InvoiceApprovalController extends Controller
{
    public function __construct(
        private readonly AbacPolicyService $abacService
    ) {}

    public function approve(Request $request, Invoice $invoice)
    {
        $context = [
            'subject' => [
                'user_id' => $request->user()->id,
                'tenant_id' => $request->user()->tenant_id,
                'clearance_level' => $request->user()->clearance_level ?? 2,
                'roles' => $request->user()->roles->pluck('name')->all(),
                'is_mfa_verified' => $request->session()->get('mfa_verified', false),
            ],
            'resource' => [
                'type' => 'invoice',
                'id' => (string) $invoice->id,
                'tenant_id' => $invoice->tenant_id,
                'amount_idr' => $invoice->total_amount_idr,
                'classification' => $invoice->total_amount_idr > 500_000_000 ? 'CONFIDENTIAL' : 'INTERNAL',
            ],
            'action' => [
                'code' => 'invoice.approve',
                'category' => 'WRITE',
                'risk_tier' => $invoice->total_amount_idr > 500_000_000 ? 'HIGH' : 'LOW',
            ],
            'environment' => [
                'ip' => $request->ip(),
                'is_business_hours' => now()->isWeekday() && now()->hour >= 8 && now()->hour < 18,
                'threat_level' => 'GREEN',
            ],
        ];

        $decision = $this->abacService->evaluate(
            tenantId: $request->user()->tenant_id,
            subjectId: (string) $request->user()->id,
            resourceType: 'invoice',
            resourceId: (string) $invoice->id,
            actionCode: 'invoice.approve',
            attributes: $context
        );

        if ($decision->isDenied()) {
            abort(403, 'Akses ditolak oleh kebijakan tata kelola: ' . $decision->decisionReason);
        }

        // Lanjutkan proses persetujuan
    }
}
```

---

## 2. Siklus Hidup Access Grant & Pencabutan Sub-Detik (W8-D55)

Tiap hak akses khusus atau peran istimewa dimodelkan sebagai entitas `CoreAccessGrant` dengan mesin status 8-fase:

```
[REQUESTED] ---> [PENDING_APPROVAL] ---> [APPROVED] ---> [ACTIVE]
                       |                    |               |
                       v                    v               v
                   [REJECTED]          [CANCELED]      [SUSPENDED]
                                                            |
                                                            v
                                                        [REVOKED]
                                                            |
                                                            v
                                                        [EXPIRED] ---> [ARCHIVED]
```

### Invarian Pencatatan Transisi Append-Only
Setiap perubahan status grant WAJIB mencatat entri baru ke tabel `core_access_grant_transitions`.
- Tidak boleh melakukan mutasi atau penghapusan pada tabel transisi (`DELETE` / `UPDATE` diblokir oleh trigger atau konvensi domain).
- Kolom `payload_hash` mengikat secara kriptografis status asal, status tujuan, pelaku, alasan, dan stempel waktu.

### Pencabutan Sub-Detik (*Sub-Second Revocation*)
Ketika metode `revokeGrant(int $grantId, string $reason)` dipanggil:
1. Status di `core_access_grants` diubah menjadi `REVOKED`.
2. Baris transisi baru dicatat di `core_access_grant_transitions`.
3. `SubSecondEvictionManager` segera menghapus entri dari memory cache lokal (`AbacMemoryCache::evictSubject()`).
4. Tag Redis `tenant:{tenant_id}` dan `user:{user_id}` di-flush secara instan.
5. Token grant dimasukkan ke daftar hitam (*blacklist*) dengan TTL 24 jam.

Hasilnya, panggilan API berikutnya dari token yang dicabut akan ditolak dalam hitungan fraksi detik (< 1.0 detik).

---

## 3. Menghubungkan Profil Lintas-Modul via Identity Graph (W8-D56)

Untuk mendeteksi entitas yang sama (individu, pasien, pemasok) di berbagai modul tanpa menciptakan ketergantungan domain langsung:

```php
use Modules\Core\Application\Access\IdentityGraphService;

$linker = app(IdentityGraphService::class);

$link = $linker->linkProfiles(
    tenantId: '0193188a-7800-7000-8000-000000000001',
    subjectType: 'SUPPLIER',
    sourceEntityId: 101, // ID di modul Procurement
    targetEntityId: 405, // ID di modul Logistics
    sourceAttributes: [
        'tax_id' => '01.234.567.8-901.000',
        'legal_name' => 'PT Sumber Logistik Sejahtera',
        'phone' => '+62811223344',
    ],
    targetAttributes: [
        'tax_id' => '01.234.567.8-901.000',
        'legal_name' => 'PT Sumber Logistik Sejahtera Tbk',
        'phone' => '+62811223344',
    ],
    sourceConsent: true,
    targetConsent: true
);

// Jika match_score >= 0.85 dan kedua consent bernilai TRUE, status menjadi LINKED
```

---

## 4. Mengimplementasikan Provider Eksposur Vendor Lintas-Lini (W8-D57)

Setiap modul bisnis yang menciptakan komitmen finansial (Purchase Order, Kontrak Pengangkutan, Letter of Credit, Saldo Hutang) WAJIB mendaftarkan implementasi `VendorExposureProviderInterface`.

### Antarmuka Kontrak Bersama
File kontrak berada di `Modules\Shared\Contracts\VendorExposureProviderInterface.php`:

```php
namespace Modules\Shared\Contracts;

interface VendorExposureProviderInterface
{
    public function getModuleIdentifier(): string;

    public function collectExposureTranches(string $vendorClusterCode, ?string $tenantId = null): array;
}
```

### Langkah Registrasi di ServiceProvider Modul
Di dalam method `register()` pada ServiceProvider modul bisnis Anda:

```php
$this->app->tag(
    \Modules\YourModule\Infrastructure\Access\YourVendorExposureProvider::class,
    'core.vendor_exposure_provider'
);
```

Dengan menandai (*tagging*) provider ini, `VendorExposureService` di modul Core dapat secara dinamis mengumpulkan tranche liabilitas lintas-modul tanpa perlu mengetahui model internal modul Anda (mempertahankan aturan arsitektur A1 dan A9).

---

## 5. Pemeriksaan Isolasi Multi-tenansi Harian (W8-D58)

Untuk memverifikasi bahwa query database tidak mengalami kebocoran data antar-tenant (*cross-tenant leakage*):

```bash
# Jalankan probe sintetis harian terhadap model terdaftar
php artisan access:tenant-probe --samples=50

# Keluaran akan mencakup:
# - Total model yang diperiksa
# - Tingkat kebocoran data (harus 0)
# - Skor kepatuhan isolasi (harus 100.0%)
```

---

## 6. Audit Akses Dua-Sumber & Rekonsiliasi (W8-D60)

Perintah audit `access:audit` membandingkan tabel keadaan saat ini (`core_access_grants`) dengan buku besar transisi append-only (`core_access_grant_transitions`):

```bash
# Jalankan audit kepatuhan
php artisan access:audit

# Mode perbaikan (opsional bila diizinkan)
php artisan access:audit --fix
```

Perintah ini terdaftar dalam `AuditCommandRegistry` dan diverifikasi dengan fixture korupsi sintetis `AccessAuditCorruptionFixture`.

