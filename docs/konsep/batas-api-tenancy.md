# Konsep Fondasi — Batas Modul, Kepemilikan Data, API v2 & Multi-Tenancy

Dokumen ini mendefinisikan prinsip konseptual, landasan teoritis, dan invarian arsitektur untuk Gelombang 8 Paket 4 (W8-P4), yang mencakup:
- **Fase R4**: Batas Modul & Kepemilikan Data (`Modules\Core\Domain\Boundaries`)
- **Fase 55**: API v2, EDI & Multi-tenancy (`Modules\Core\Application\Api`, `Modules\Core\Application\Tenancy`)
- **Fase 188**: Identitas, Akses & Tenancy 30 Lini (`Modules\Core\Application\Identity`, `Modules\Core\Domain\Access`)

---

## 1. Bagian 1: Batas Modul & Kepemilikan Data (Fase R4)

### 1.0 Masalah Bisnis, Aktor/Role, Alur Nilai & Batas Simulasi (Fase R4)
- **Masalah Bisnis**: Dalam monolit modular dengan 60+ modul, ketergantungan lintas domain secara langsung (`use Modules\X\Domain\...`) dan pembuatan tabel berprefiks milik modul lain menyebabkan efek riak tak terkendali, inkonsistensi kepemilikan data, dan pelanggaran isolasi transaksi. Fase R4 mengubah aturan arsitektur dari dokumentasi pasif menjadi penegakan aktif berbasis AST, state machine lifecycle, kontrak lintasan nyata (`VehicleDirectory`, `VehicleSnapshot`, `VehicleModelResolver`), serta audit rekonsiliasi dua sumber (`boundary:audit`).
- **Aktor & Role**:
  - `platform_architect` (`admin`/`superadmin`): Memetakan dependensi modul (`/admin/architecture/modules`), melakukan triase pelanggaran (`/admin/architecture/violations`), menyetujui/memperpanjang waiver (`/admin/architecture/waivers`), dan mengekspor laporan CSV.
  - `auditor`: Membaca daftar waiver aktif serta hasil audit dua sumber (`boundary:audit`) tanpa hak mengubah state mutasi terlarang (`403` pada `/admin/architecture/modules`).
- **Alur Nilai**: Pindai AST (`arch:boundaries`) $\to$ Deteksi pelanggaran A1/A2/A3 (`boundary:triage`) $\to$ Rancangan kontrak di modul pemilik atau waiver ber-anchor $\le 90$ hari (`WaiveViolationAction`) $\to$ Refaktor pemanggil ke kontrak $\to$ Verifikasi turun baseline & test hijau (`VerifyViolationAction`) $\to$ Tutup (`closed`) & sweep kedaluwarsa (`boundary:expire-waivers`).
- **Batas Simulasi (Tier T1 & Tingkat Simulasi S2)**: Penganalisis membaca kode statis aktual di `modules/*` dan memvalidasi kontrak/graf menggunakan `BoundariesT1Seeder` deterministik (38 rekaman pelanggaran mencakup seluruh 7 status SM, $\ge 5$ waiver ber-anchor DECISIONS termasuk 2 skenario kedaluwarsa serentak, tanpa menyentuh data produksi eksternal).

### 1.1 Prinsip Ketergantungan Asiklik (*Acyclic Dependencies Principle / ADP*)
Menurut Robert C. Martin (1996), graf dependensi antar paket atau modul dalam suatu sistem tidak boleh memuat siklus:
$$\forall v \in V, \quad v \notin \text{Reach}(v, G \setminus \{ (v, v) \})$$
Jika modul $A$ bergantung pada modul $B$, dan modul $B$ secara langsung atau transitif bergantung kembali pada modul $A$, maka perubahan pada salah satu modul dapat memicu kegagalan tak terduga (*ripple effect*) pada modul lainnya. Pada SuperWeb, aturan A1 (*CrossModuleDomainImportRule*) dan A9 (*KernelDependsOnBusinessModuleRule*) menegakkan batas ini secara statis.

Struktur modul yang bersih dapat direpresentasikan sebagai Directed Acyclic Graph (DAG) di mana relasi dependensi selalu mengalir dari tingkat abstraksi yang lebih tinggi atau modul spesifik ke modul kernel/fondasi (`Shared`, `Core`), atau horizontal antar modul bisnis melalui antarmuka kontrak resmi (`Contracts`) tanpa mengimpor internal domain modul lain.

### 1.2 Prinsip Kepemilikan Tunggal Tabel Basis Data (*Single Table Ownership*)
Dalam monolit modular berkinerja tinggi, setiap tabel basis data fisik dimiliki secara eksklusif oleh satu modul bisnis:
1. **Pencocokan Prefiks Terpanjang (*Longest Prefix Match*)**:
   Nama tabel dicocokkan dengan prefiks modul terdaftar di `config/modules.php`. Prefiks dengan panjang karakter terbesar memiliki preseden tertinggi.
   Contoh: Tabel `hcm_pay_structures` dicocokkan ke prefiks `hcm_pay_` (panjang 8 karakter) yang mengarah ke modul spesifik, bukan sekadar `hcm_` (panjang 4 karakter).
2. **Pencegahan Konflik Prefiks (*PREFIX_CONFLICT*)**:
   Sistem menolak konfigurasi apabila dua modul mengklaim prefiks yang sama tanpa pembagian kepemilikan yang sah.
3. **Akses Data Terisolasi**:
   Modul dilarang keras melakukan kueri SQL langsung (`DB::table(...)`) atau membuat model Eloquent pada tabel milik modul lain. Seluruh pertukaran data harus melewati:
   - Kontrak Layanan (*Service Contracts*) pada namespace `Modules\{Owner}\Contracts` atau `Modules\Core\Contracts`.
   - Penerbitan event pada Universal Event Spine (`Modules\Core\Contracts\EventSpineInterface`).
   - Pencatatan buku besar (*Ledger posting*).

### 1.3 State Machine Siklus Hidup Pelanggaran Batas (*Boundary Violation Lifecycle*)
Pembersihan utang arsitektur dikelola melalui State Machine 7 status:
- `detected`: Pelanggaran ditemukan oleh pemindai AST statis.
- `triaged`: Kategori pelanggaran (A1, A2, A3) dan penanggung jawab modul telah ditetapkan.
- `contract_designed`: Kontrak pengganti telah dirancang dan disepakati melalui ADR.
- `migrating`: Proses pengalihan kode sedang berlangsung aktif.
- `verified`: Tanda tangan pelanggaran pada baseline `arch-scan.json` telah dihapus dan seluruh test modul hijau.
- `closed`: Status akhir terminal; pemindaian statis `arch:scan` bersih tanpa utang.
- `waived`: Pengecualian sementara dengan dispensasi terikat waktu ($\le 90$ hari) dan anchor keputusan tertulis di `docs/DECISIONS.md`.

Matriks Validitas Transisi Status:

| Dari Status | Ke Status | Guard Rules & Syarat Wajib | Pemicu (Action / Event) |
|---|---|---|---|
| `detected` | `triaged` | Kategori (A1/A2/A3), client_module, dan owner_module terisi valid | `TriageViolationAction` |
| `triaged` | `contract_designed` | Spesifikasi kontrak tercatat: nama kontrak, modul pemilik, dan ADR anchor | Pembaruan rancangan arsitektur |
| `contract_designed` | `migrating` | Branch kerja refaktor aktif dan ditugaskan ke engineer | Memulai migrasi kode |
| `migrating` | `verified` | Fingerprint pelanggaran dihapus dari baseline JSON & test modul hijau | `VerifyViolationAction` |
| `verified` | `closed` | Pemindaian `arch:scan` pada commit bersih dari pelanggaran ini | `arch:scan --update-baseline` |
| `triaged` | `waived` | Anchor DECISIONS.md valid, justifikasi teknis lengkap, tenggat $\le 90$ hari | `WaiveViolationAction` |
| `waived` | `triaged` | Tanggal kedaluwarsa terlewati (`now() > expires_at`) atau dicabut manual | `ExpireWaiversAction` |

Waiver kedaluwarsa secara otomatis melalui command terjadwal harian `boundary:expire-waivers`, mengembalikan status pelanggaran ke `triaged` dan menerbitkan notifikasi ke Kotak Masuk Tugas Saya (`TaskInboxService::createTask()`).

### 1.4 Algoritma Penegakan Batas Modular
Tiga algoritma inti menjamin kepatuhan batas:
1. **Strongly Connected Components (Tarjan SCC)**:
   Mendeteksi siklus dependensi dalam waktu linier $\mathcal{O}(V + E)$ dengan penelusuran DFS deterministik terurut alfabet. Menghasilkan Directed Acyclic Graph (DAG) kondensasi yang diverifikasi oleh `SccCertificateVerifier`.
2. **Table Prefix Ownership Resolver**:
   Menyelesaikan pemetaan tabel ke modul pemilik secara deterministik menggunakan pengurutan prefiks menurun berdasarkan panjang string. Diverifikasi oleh `PrefixCertificateVerifier`.
3. **Contract Coverage Scorer**:
   Menghitung rasio pemenuhan kontrak antar modul menggunakan integer permille bulat (0–1.000) tanpa tipe floating point untuk menghindari ketidakpastian representasi biner. Diverifikasi oleh `CoverageCertificateVerifier`.

### 1.5 Model Ancaman Batas Modular (STRIDE)
Pemisahan batas modular menangkal ancaman keamanan perangkat lunak:
- **Spoofing**: Modul bisnis menyamar sebagai modul lain untuk mengeksekusi DDL atau query tabel. Dicegah melalui verifikasi namespace pemanggil dan static prefix scoping.
- **Tampering**: Modul bisnis memodifikasi baris data modul lain tanpa melewati validasi domain bisnis. Dicegah melalui larangan direct table access.
- **Repudiation**: Perubahan arsitektur dan waiver tanpa pencatatan pertanggungjawaban. Dicegah melalui tabel fakta append-only `core_boundary_violation_transitions`.
- **Information Disclosure**: Pembocoran data sensitif antar modul internal. Dicegah melalui penggunaan Snapshot DTO dengan bidang ter-filter.
- **Denial of Service**: Siklus ketergantungan melingkar menyebabkan rekursi tanpa henti saat resolusi service provider. Dicegah melalui penegakan DAG Tarjan SCC.
- **Elevation of Privilege**: Modul non-otoritas memanggil method privat domain modul lain. Dicegah melalui pemisahan kontrak tipis dan interface public.

---

## 2. Bagian 2: API v2, Idempotensi & Multi-tenancy (Fase 55)

### 2.1 Amplop Standar API v2 & Spesifikasi RFC 7807
Seluruh endpoint API v2 menyajikan respons dalam format terstandarisasi:
- **Respon Sukses**: `{"data": ..., "meta": {"next_cursor": ..., "limit": ...}}`
- **Respon Galat**: Format `application/problem+json` sesuai RFC 7807 (`type`, `title`, `status`, `detail`, `instance`, `errors[]`).
- **Kursor Deterministik**: Kursor berbasis base64url dari JSON terurut `{"k": <sort_key>, "id": <id>}` untuk menjamin stabilitas paginasi saat data bertambah di latar belakang.

### 2.2 Penegakan Idempotensi Mutasi State (W8-D46)
Setiap mutasi bermakna state (`POST`, `PUT`, `PATCH`, `DELETE`) mewajibkan header `Idempotency-Key`:
- Permintaan tanpa header ditolak dengan galat HTTP 422 Problem JSON.
- Eksekusi pertama merekam kunci dan payload hash SHA-256.
- Permintaan berulang dengan kunci dan payload yang sama mengembalikan respons identik dari penyimpanan persisten tanpa memicu efek samping ganda.
- Permintaan berulang dengan kunci sama tetapi payload berbeda ditolak dengan galat HTTP 409 Conflict.

### 2.3 Pembatas Laju Token Bucket Bilangan Bulat (W8-D47)
Rate limiting dihitung per klien API dan tier langganan menggunakan algoritma Token Bucket:
- Kapasitas dan laju pengisian dihitung dalam satuan integer milli-token ($1 \text{ token} = 1.000 \text{ milli-tokens}$).
- Bebas pembulatan float, menjamin determinisme penuh pada lingkungan konkuren tinggi.
- Saat kuota habis, respons HTTP 429 Too Many Requests menyertakan header `Retry-After` yang akurat dalam detik.

### 2.4 Tanda Tangan Webhook HMAC-SHA256 (W8-D48)
Notifikasi webhook diamankan dengan header `X-Signature: t=<unix_timestamp>,v1=<hmac_hex>`:
- Nilai HMAC dihitung dari konkatenasi `t.<payload>` menggunakan kunci rahasia per endpoint.
- Mendukung rotasi kunci dengan toleransi dua tanda tangan simultan (`v1=<sig1>,v1=<sig2>`).
- Mencegah serangan replay dengan memvalidasi selisih waktu toleransi maksimal 300 detik.

### 2.5 Standar EDI Deterministic Translation (W8-D49)
Dukungan subset format Electronic Data Interchange (EDI) mencakup:
- **EDIFACT**: ORDERS D96A (Purchase Order), INVOIC (Faktur Komersial), DESADV (Despatch Advice).
- **ANSI X12**: Transaction Set 850 (Purchase Order) dan 997 (Functional Acknowledgment).
- Parser dan Generator bekerja secara byte-identik: pesan EDI yang diurai ke model kanonikal internal dan digenerasi kembali menghasilkan urutan segmen dan terminator yang sama persis tanpa distorsi spasi atau karakter pemisah.

### 2.6 Streaming Impor/Ekspor Massal Hemat Memori (W8-D50)
Pengolahan data skala besar ($\ge 20.000$ baris) menggunakan teknik streaming berbasis chunk:
- Baris diproses secara streaming tanpa memuat seluruh file ke memori RAM ($\mathcal{O}(1)$ memori).
- Transaksi database dieksekusi per batch 500 baris.
- Validasi awal (*dry run / error preview*) memvalidasi integritas sebelum eksekusi mutasi.
- Mekanisme checkpointing mencatat nomor baris terakhir yang sukses; proses impor yang terhenti dapat dilanjutkan kembali (`--resume`) secara idempoten tanpa duplikasi data.

---

## 3. Bagian 3: Identitas, Akses & Multi-tenancy Lintas Lini (Fase 188)

### 3.1 Model Otorisasi ABAC Murni Tanpa I/O (W8-D53 & W8-D54)
Evaluator kebijakan akses berbasis atribut (*Attribute-Based Access Control / ABAC*) berjalan murni di memori tanpa pemanggilan I/O atau query basis data:
- Kebijakan dimodelkan sebagai data terstruktur pada tabel `core_access_policies`.
- Atribut permintaan mencakup dimensi: `entity_id`, `region_id`, `site_id`, `project_id`, dan jendela waktu (`valid_from` s.d. `valid_to`).
- Penegakan strategi *Deny-Overrides*: Jika terdapat setidaknya satu aturan eksplisit yang bernilai `DENY`, maka akses secara mutlak ditolak meskipun terdapat aturan `ALLOW`.

### 3.2 Siklus Hidup Access Grant & Pencabutan Sub-Detik (W8-D55)
- Izin akses pengguna dikelola melalui entitas `AccessGrant` dengan State Machine 8 status terawasi.
- Seluruh pengajuan melewati verifikasi pemisahan tugas (*Separation of Duties / SoD*) pada `ApprovalEngine`.
- Pencabutan hak akses (*revocation*) berlaku efektif seketika ($\le 1$ detik) melalui mekanisme invalidasi versi grant cache global.

### 3.3 Graf Klaster Identitas Multi-Pihak (W8-D56)
Penautan identitas lintas modul menggunakan pembobotan kecocokan atribut berkunci:
- NIK Hash: bobot 100
- Email Hash: bobot 60
- Phone Hash: bobot 50
- Name + Tanggal Lahir Hash: bobot 40
- Ambang batas penautan (*linkage threshold*) adalah skor total $\ge 90$.
- Penautan mewajibkan izin konsensus (*consent*) pada kedua belah pihak entitas. Tanpa persetujuan ganda, tautan berstatus `BLOCKED_NO_CONSENT`.
- Nilai data sensitif tidak pernah disimpan dalam bentuk teks polos, melainkan menggunakan HMAC ber-garam rahasia.

### 3.4 Agregasi Eksposur Vendor Terpadu (W8-D57)
Sistem mengagregasi total liabilitas dan eksposur keuangan terbuka ke grup vendor di seluruh lini bisnis:
$$\text{Total Exposure}(V) = \sum_{m \in \text{Modules}} \sum_{t \in \text{OpenTranches}(m, V)} \text{AmountIDR}(t)$$
- Meliputi PO terbuka di Procurement, penerimaan barang belum difakturkan di Logistics, saldo titipan di CloudKitchen, dan klaim garansi di AutoServe.
- Klaster identitas vendor memastikan entitas dengan anak perusahaan berbeda yang terafiliasi diperlakukan sebagai satu grup risiko kredit terpadu.

### 3.5 Probe Isolasi Tenant Otomatis & Penegakan Global Scope (W8-D58)
Pemisahan multi-tenancy dijamin pada tingkat ORM Eloquent:
- Model transaksional yang memiliki data tenant meregistrasikan `TenantScope` secara deklaratif via `TenantScopeRegistry` (tanpa mengubah kode kelas model warisan secara invasif).
- Konteks aktif `TenantContext` diinjeksi saat autentikasi pengguna.
- Perintah harian `php artisan api:tenancy-probe` menguji setiap model terdaftar: kueri atas nama Tenant A dipastikan menghasilkan tepat 0 baris rekaman milik Tenant B. Kegagalan isolasi memicu alarm insiden keamanan level tinggi.

---

## 4. Invarian Sistem, Katalog Kasus & Sumber Rujukan

### 4.1 Invarian Fase R4 (Bertautan Kode Nyata)
1. **Integritas Graf vs Basis Data**: Setiap pelanggaran A1/A2/A3 dari pemindaian `BoundariesScanner` wajib memiliki rekaman pada `core_boundary_violations`, diverifikasi di [`BoundaryAuditCommand::handle`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/app/Console/Commands/BoundaryAuditCommand.php#L21-L125).
2. **Kesesuaian Baseline Ratchet**: Baseline [`arch-scan.json`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/tests/Architecture/baselines/arch-scan.json) hanya boleh turun (`A1+A2: 39 -> 15`, turun 61,5%). Pelanggaran baru ditolak di [`BoundaryAuditCommand`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/app/Console/Commands/BoundaryAuditCommand.php#L42-L60) dan `ArchScanBaselineTest`.
3. **Guard Transisi SM 7 Status & Verifikasi Baseline**: Status `verified` menolak transisi bila fingerprint masih ada di baseline [`BoundaryViolationStateMachine::evaluateGuards`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/StateMachines/BoundaryViolationStateMachine.php#L165-L265), dan riwayat [`CoreBoundaryViolationTransition::booted`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Models/CoreBoundaryViolationTransition.php#L35-L45) menolak update/delete (`append-only`).
4. **Determinisme Output & Aritmetika Bulat Permil**: `arch:boundaries --format=json|md|svg` bersifat deterministik byte-identik; [`ContractCoverageScorer::calculate`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/Boundaries/ContractCoverageScorer.php#L22-L100) murni memakai integer `intdiv`.
5. **Kekebalan Invarian Siklus & Self-Loop**: [`StronglyConnectedComponents::compute`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/Boundaries/StronglyConnectedComponents.php#L38-L90) melaporkan hanya komponen dengan $|SCC| > 1$; self-loop $(A \to A)$ diabaikan sesuai W8-D41.
6. **Batas Kedaluwarsa Waiver & Anchor DECISIONS**: [`WaiveViolationAction`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Application/Boundaries/WaiveViolationAction.php#L17-L70) mewajibkan anchor sah di `docs/DECISIONS.md` dengan masa berlaku $\le 90$ hari; [`ExpireWaiversAction`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Application/Boundaries/ExpireWaiversAction.php#L14-L82) otomatis mengembalikan waiver kedaluwarsa ke `triaged`.
7. **Longest Prefix Ownership & Bebas Konflik**: [`TablePrefixOwnershipResolver::resolve`](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/modules/Core/Domain/Algorithms/Boundaries/TablePrefixOwnershipResolver.php#L36-L102) memenangkan prefiks terpanjang terurut menurun dan menolak duplikasi prefiks lintas modul.

### 4.2 Katalog Kasus Fase R4 (16 Kasus Unik Bernama)
1. **R4-K01 Siklus Terlarang 3-Simpul (`A->B->C->A`)**: Vektor Tarjan SCC mendeteksi `[['A','B','C'], ['D','E','F']]` (`BoundaryAlgorithmsTest`).
2. **R4-K02 Graf Asiklik & Self-Loop Diabaikan (`A->A`)**: Graf `A->B->C` dan self-loop `A->A` menghasilkan `cycles = []` (`StronglyConnectedComponents`).
3. **R4-K03 Sertifikat SCC Dirusak**: Manipulasi payload sertifikat Tarjan melempar `InvalidArgumentException` (`SccCertificateVerifier::verifyCertificate`).
4. **R4-K04 Longest Prefix Match Bertumpuk (`hcm_pay_runs` vs `hcm_payroll`)**: `hcm_pay_runs` dimiliki `Payroll`, sedangkan `hcm_payroll` jatuh ke `Hcm` (`TablePrefixOwnershipResolver`).
5. **R4-K05 Konflik Prefiks Identik (`PREFIX_CONFLICT`)**: Registrasi ganda `ins_` untuk `Insurance` dan `Health` mengembalikan `['ins_']` (`detectConflicts`).
6. **R4-K06 Tabel Tidak Terdaftar (`foo_bar` -> `UNOWNED`)**: Tabel tanpa prefiks terdaftar dilaporkan `UNOWNED` (`TablePrefixOwnershipResolver`).
7. **R4-K07 Skor Cakupan Kontrak Permil (`Z(0,0)->1000`, `V(5,0)->1000`)**: Pemeringkatan deterministik Y(333), W(500), X(750), V(1000), Z(1000) (`ContractCoverageScorer`).
8. **R4-K08 Penolakan `verified` Sebelum Baseline Turun**: Transisi `migrating->verified` untuk entri A2 `gov_proposals` yang masih di baseline ditolak `DomainException`.
9. **R4-K09 Penolakan Waiver Tanpa Anchor DECISIONS**: `WaiveViolationAction` dengan anchor kosong atau tidak ada di `docs/DECISIONS.md` ditolak `DomainException`.
10. **R4-K10 Sweep Waiver Kedaluwarsa Serentak (Kejutan L.4)**: `boundary:expire-waivers` mengembalikan 2+ waiver kedaluwarsa ke `triaged`, memicu tugas inbox & event outbox `core.boundary.waiver_expired.v1`.
11. **R4-K11 Imutabilitas Riwayat Transisi Append-Only**: `update()` / `delete()` pada `CoreBoundaryViolationTransition` melempar `DomainException`.
12. **R4-K12 Idempotensi Perpanjangan Waiver**: `POST /admin/architecture/waivers/{id}/extend` mengembalikan `422` tanpa header/field `Idempotency-Key`, `200` satu efek dengan key sama, dan `409` dengan payload berbeda.
13. **R4-K13 Interoperabilitas Kontrak Vehicle A1 (AutoServe/Logistics/Mall/Store)**: Seluruh 14 import langsung `Vehicle` digantikan `VehicleDirectory`, `VehicleSnapshot`, `VehicleModelResolver` (`A1: 14 -> 0`).
14. **R4-K14 Audit Dual-Source Bersih vs Fixture Korupsi**: `boundary:audit` keluar kode `0` pada data sinkron dan kode `1` (`[AUDIT_FAIL]`) ketika `BoundaryAuditCorruptionFixture` menyuntikkan waiver korup.
15. **R4-K15 Kontrol Akses Rute Arsitektur (`platform_architect` vs `auditor` vs `customer`)**: `auditor` diizinkan `GET /admin/architecture/waivers` (`200`) namun ditolak (`403`) pada `/modules` dan `/violations`.
16. **R4-K16 Determinisme Byte-Identik CLI**: Dua eksekusi berurutan `php artisan arch:boundaries --format=json` menghasilkan keluaran identik bit-demi-bit.

### 4.3 Sumber Rujukan Konsep (T-8)
- **Algoritma Tarjan (1972)**: Robert Tarjan, *"Depth-First Search and Linear Graph Algorithms"*, SIAM Journal on Computing, Vol. 1, No. 2, pp. 146–160 (1972). Tautan: https://epubs.siam.org/doi/10.1137/0201010 (diakses 2026-11-11; label: `regulasi_teori_standar`).
- **Acyclic Dependencies Principle (ADP)**: Robert C. Martin, *"Granularity / Acyclic Dependencies Principle"* dalam *Agile Software Development, Principles, Patterns, and Practices*. Tautan: https://web.archive.org/web/2026/https://fi.ort.edu.uy/innovaportal/file/2032/1/design_principles.pdf (diakses 2026-11-11; label: `asumsi_simulasi`).
- **Batas Kedaluwarsa Waiver 90 Hari & Penurunan Baseline A1+A2 >= 50%**: Ketetapan arsitektur internal SuperWeb ([ADR-0043](file:///Users/orl123456/Documents/typeshit/sw-w8-p4/docs/decisions/ADR-0043-tata-kelola-waiver-arsitektur.md); diakses 2026-11-11; label: `asumsi_simulasi`).

