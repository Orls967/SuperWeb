# Definition of Ready (DoR) — [G6-P4.3] NDVI Satelit & DAO Tata Kelola (Fase 86)

## 1. Lingkup & Konteks
- **Fase**: 86 (Precision Agri-Tech NDVI & DAO Corporate Governance)
- **Komponen Utama**:
  - D86-1 & D86-2: Adapter satelit NDVI rasional eksak per petak vs ambang; ratchet Contract pencairan pinjaman petani hanya bila NDVI $\ge$ ambang standar (mis. 0.65). Bila di bawah ambang, pencairan tertunda dengan rencana korektif.
  - D86-3: Pindah tabel `gov_*` dari modul Agri ke modul baru berdedikasi `Governance` (`gov_`), atau daftarkan model Governance di bawah namespace `Governance` dengan prefix tabel `gov_`.
  - D86-4: Algoritma `Modules\Shared\Domain\Algorithms\Hash\MerkleTree` di `modules/Shared/Domain/Algorithms/Hash/MerkleTree.php` (vektor deterministik hash SHA-256 daun dan akar pohon Merkle).
  - D86-5: Voting berbasis snapshot bobot RWA via kontrak K9 `VotingWeightReader`, draf Epc via kontrak K10 `ProjectDraftFactory`.

## 2. Kriteria Penerimaan (Acceptance Criteria)
1. **D86-1 & D86-2**: Feed satelit NDVI menghitung skor rasional eksak per poligon lahan. Pencairan pinjaman modal tani terikat ratchet otomatis; kegagalan beruntun memicu eskalasi korektif.
2. **D86-3**: Modul `Governance` mengelola proposal, voting, dan snapshot bobot hak suara secara bersih tanpa pelanggaran A2 (tabel modul lain).
3. **D86-4**: Implementasi `MerkleTree` lulus vektor uji wajib:
   - Daun tunggal `daun0`: `hash('sha256', 'daun0')` = `717fe5803be2c75a401c23f6631ad1845184209930fca566580f55cf52fc4ecc`.
   - 4 daun `['daun0', 'daun1', 'daun2', 'daun3']` menghasilkan root hash deterministik `b27538...`.
   - 3 daun `['daun0', 'daun1', 'daun2']` menghasilkan root hash deterministik `0a1f0e...` (duplikasi daun ganjil / balanced tree).
4. **D86-5**: Proposal lolos voting berbobot snapshot RWA menghasilkan draf proyek EPC melalui K10.
5. **Acceptance Test**: `tests/Acceptance/NdviDao/NdviDaoAcceptanceTest.php` memverifikasi seluruh alur dalam maksimal 6 test komprehensif.
