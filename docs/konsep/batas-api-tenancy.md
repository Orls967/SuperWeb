# Konsep Fondasi — Batas Modul, Kepemilikan Data, API v2 & Multi-Tenancy

Dokumen ini mendefinisikan prinsip konseptual, landasan teoritis, dan invarian arsitektur untuk Gelombang 8 Paket 4 (W8-P4), yang mencakup:
- **Fase R4**: Batas Modul & Kepemilikan Data (`Modules\Core\Domain\Boundaries`)
- **Fase 55**: API v2, EDI & Multi-tenancy (`Modules\Core\Application\Api`, `Modules\Core\Application\Tenancy`)
- **Fase 188**: Identitas, Akses & Tenancy 30 Lini (`Modules\Core\Application\Identity`, `Modules\Core\Domain\Access`)

---

## 1. Bagian 1: Batas Modul & Kepemilikan Data (Fase R4)

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

## 4. Invarian Sistem & Verifikasi Dual-Source
Setiap komponen dalam W8-P4 diverifikasi oleh perintah audit dua sumber (`boundary:audit`, `api:audit`, `access:audit`):
1. **Integritas Graf vs Basis Data**: Setiap node dan edge pada graf dependensi wajib memiliki representasi yang sinkron pada basis data relasional.
2. **Kesesuaian Baseline Ratchet**: Jumlah pelanggaran yang dilaporkan oleh pemindai tidak boleh melebihi ambang batas ratchet `tests/Architecture/baselines/arch-scan.json`.
3. **Determinisme Output**: Perintah CLI menghasilkan byte keluaran yang identik secara mutlak pada dua kali pengeksekusian berturut-turut terhadap kondisi data yang sama.
4. **Konservasi Busur Kopling**: Total kopling aferen wajib setara dengan total kopling eferen pada seluruh modul ($\sum C_a = \sum C_e$).
5. **Kekebalan Invarian Siklus**: Komponen Tarjan SCC berukuran $|SCC| > 1$ dilarang keras berada di cabang produksi (`hasCycles = false`).
6. **Batas Kedaluwarsa Waiver Terjamin**: Seluruh dispensasi teknis memiliki tanggal jatuh tempo $\le 90$ hari dengan pengawasan audit berkelanjutan.
