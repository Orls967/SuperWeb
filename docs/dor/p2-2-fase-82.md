# Definition of Ready (DoR) - G6-P2.2 Fase 82 (VMI & C2M)

**Item:** P2.2 Fase 82 (Vendor-Managed Inventory / VMI & Consumer-to-Manufacturer / C2M)  
**Tautan Rujukan KONSEP:** §6F Slice 2–3 (VMI & C2M), §A0–§A17  
**Tautan Rujukan PROGRESS:** Fase 82 (§P6)  
**Pelaksana:** [G6-P2]  
**Tanggal:** 2026-11-04  

---

## 1. Lingkup Bisnis & Kriteria Keberterimaan

### 1.1 Vendor-Managed Inventory (VMI) - D82-1
- Portal pemasok di `/portal/vmi` (role: `supplier`) hanya menampilkan SKU yang dikontrakkan ke supplier tersebut lewat `ContractLookupInterface` / `prc_vmi_agreements` (Anti-IDOR).
- Kebijakan $(s, S)$ dihitung deterministik menggunakan `Modules\Shared\Domain\Algorithms\Inventory\ReorderPolicy`:
  - $SS = \lceil z \times \sigma \times \sqrt{L} \rceil$
  - $ROP = \bar{d} \times L + SS$
  - $S = ROP + \bar{d} \times \text{review}$
  - $\text{posisi} = \text{on\_hand} + \text{on\_order} - \text{backorder}$
  - $\text{qty} = S - \text{posisi}$, dibulatkan ke atas ke kelipatan pack.
- Sertifikat konsistensi diverifikasi oleh `ReorderPolicyCertificateVerifier`.
- Plafon Kontrak & Approval:
  - Total nilai $\le$ plafon kontrak $\to$ auto-PO diterbitkan langsung (`AUTO_ORDERED`).
  - Total nilai $>$ plafon kontrak $\to$ menunggu approval `procurement_manager` (`PENDING_APPROVAL`).
- Idempotensi: `vmi:scan-reorder` dipanggil ulang pada hari yang sama untuk SKU & supplier yang sama tetap menghasilkan satu PO (`idempotency_key = VMI:{supplier_id}:{sku}:{date}`).
- Audit `vmi:audit` (`AuditVmiCommand`) memastikan:
  - Setiap auto-PO $\le$ plafon kontrak atau telah berstatus disetujui (`APPROVED`).
  - Tidak ada over-order dan tidak ada duplikasi PO per scan.

### 1.2 Consumer-to-Manufacturer (C2M) - D82-2
- Portal konsumen di `/store/c2m` (role: `customer`) untuk konfigurasi parametrik berbasis CSP (Constraint Satisfaction Problem).
- Portal manufaktur di `/manufacturing/c2m` (role: `planner`) untuk memantau pesanan C2M dan menerbitkan SPK pabrik.
- Filter domain dan penyempitan opsi menggunakan `Modules\Shared\Domain\Algorithms\Csp\ArcConsistency` (AC-3).
- Validasi pilihan: jika kombinasi pilihan tidak konsisten, return HTTP 422 Unprocessable Entity dengan penjelasan minimal konflik dari `Modules\Shared\Domain\Algorithms\Csp\QuickXplain`.
- Jika konsisten:
  - Simpan konfigurasi pesanan ke `mfg_c2m_custom_orders`.
  - Hasilkan BOM & routing turunan ke `mfg_c2m_bom_nodes`.
  - Kunci harga final melalui `PriceLocker` (`pric_frozen_quotes`).

---

## 2. Modul, Prefiks & Database
- Modul: `Manufacturing` (`mfg_`), `Procurement` (`prc_`), `Store` (`store_`), `Pricing` (`pric_`).
- Tabel yang digunakan:
  - `mfg_vmi_replenishments`
  - `prc_vmi_agreements`
  - `mfg_c2m_custom_orders`
  - `mfg_c2m_bom_nodes`
  - `pric_frozen_quotes`

---

## 3. Rute & RBAC
- `GET /portal/vmi` $\to$ role: `supplier`, `admin`
- `POST /portal/vmi/trigger` $\to$ role: `supplier`, `admin`
- `POST /portal/vmi/{replenishment}/approve` $\to$ role: `procurement_manager`, `procurement`, `admin`
- `GET /store/c2m` $\to$ role: `customer`, `admin`
- `POST /store/c2m/configure` $\to$ role: `customer`, `admin`
- `GET /manufacturing/c2m` $\to$ role: `planner`, `admin`
- `POST /manufacturing/c2m/orders/{order}/spk` $\to$ role: `planner`, `admin`
- `POST /manufacturing/c2m/orders/{order}/cancel` $\to$ role: `planner`, `admin`

---

## 4. Rencana Acceptance Tests (Maksimal 6 Test v3)
File: `tests/Acceptance/VmiC2m/VmiC2mAcceptanceTest.php`
1. **test_rbac_access_vmi_and_c2m_routes**: Hak akses rute VMI dan C2M sesuai peran (supplier, customer, planner, procurement_manager vs tak berhak 403).
2. **test_vmi_e2e_reorder_policy_and_idempotency**: Vektor VMI prompt ($d=120, \sigma=30, L=4, z=1.645, \text{review}=7 \to SS=99, ROP=579, S=1419$, stok $500 \to 950$ pack 50 @12.000 = 11.4jt), auto-PO $\le$ plafon 15jt, scan-reorder 2x idempoten tetap 1 PO.
3. **test_vmi_contract_plafond_and_procurement_approval**: Total nilai 11.4jt pada plafon 10jt $\to$ PENDING_APPROVAL, disetujui procurement_manager $\to$ APPROVED dan komitmen ledger.
4. **test_c2m_csp_ac3_and_quickxplain_validation_422**: Vektor meja meja prompt: AC-3 valid ($W=80, M=\text{besi} \implies D \in \{40, 60\}, L \in \{4, 6\}$), pilihan inkonsisten $(W=120, D=80, M=\text{kaca}, L=3) \to 422$ dengan konflik minimal $[W=120, M=\text{kaca}]$, dan $(W=60, D=60, M=\text{kayu}, L=4) \to 422$ konflik $[W=60, D=60]$.
5. **test_c2m_valid_order_bom_generation_and_price_locking**: Konfigurasi valid menghasilkan C2M custom order, BOM & routing turunan, dan harga terkunci di PriceLocker.
6. **test_vmi_audit_clean_and_corruption_fixture**: `vmi:audit` exit 0 pada data valid, dan exit non-0 pada `VmiAuditCorruptionFixture`.
