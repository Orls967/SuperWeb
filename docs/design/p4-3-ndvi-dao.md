# Desain Arsitektur — [G6-P4.3] NDVI Satelit & DAO Tata Kelola (Fase 86)

## 1. Arsitektur & Lingkup Modul
- **Agri (`agri_`)**: Menangani feed scan NDVI satelit, rasional eksak per petak plasma, dan ratchet pencairan pinjaman petani (`agri_financing_installments`) terintegrasi dengan ledger.
- **Governance (`gov_`)**: Modul baru berdedikasi untuk proposal, snapshot hak suara voter, voting berbobot dengan rantai hash & verifikasi Merkle tree, serta eksekusi event hasil persetujuan (`gov.proposal.passed.v1`).
- **Shared Algorithms**: `Modules\Shared\Domain\Algorithms\Hash\MerkleTree` + `MerkleTreeResult` + `MerkleCertificateVerifier`.
- **Rwa (`rwa_`) Kontrak K9**: `Modules\Rwa\Contracts\VotingWeightReader` (implementasi aditif membaca `RwaHolding`).
- **Epc (`epc_`) Kontrak K10**: `Modules\Epc\Contracts\ProjectDraftFactory` (implementasi aditif membuat `EpcProject` berstatus `draft_pending_approval`).

## 2. Struktur Tabel & Migrasi
- Tabel `gov_proposals`, `gov_voter_weights`, `gov_votes`:
  - Migrasi `2026_10_30_235955_rename_agri_dao_tables_to_agri.php` sebelumnya mengubah nama menjadi `agri_*`.
  - Migrasi baru `2026_11_05_180000_migrate_governance_tables_to_gov_prefix.php`:
    - Mengembalikan/memindahkan tabel ke `gov_proposals`, `gov_voter_weights`, `gov_votes`.
    - Menambahkan kolom `merkle_root` (varchar 64) dan `snapshot_at` (timestamp) pada `gov_proposals` bila belum ada.
    - Foreign key & unique constraints:
      - `gov_votes` unique `['proposal_id', 'voter_user_id']`.
      - Foreign key `proposal_id` on `gov_proposals` cascade delete.

## 3. Algoritma MerkleTree (D86-4)
- Daun = `hash('sha256', "voter_id:weight")`.
- Induk = `hash('sha256', $leftRawBytes . $rightRawBytes, false)` (binary concatenation raw bytes).
- Simpul ganjil diduplikasi: `$right = ($i + 1 < count($currentLayer)) ? $currentLayer[$i + 1] : $left`.
- Vektor wajib prompt:
  - Daun: `['1:500', '2:300', '3:200', '4:1000']`
  - Daun0 = `717fe51487c76cc00d488b8732176a4cde5af558fa448d43456e0cef035597c4`
  - Root 4 daun = `b275384498573111f4ae43c74765a6b2b3edbe3be4fc87f14ae49f797bf5eacb`
  - Tiga daun pertama (`['1:500', '2:300', '3:200']`) = `0a1f0ee26b0d17d73015540eca2c076c67bf266d288b96bd79f35f995ed568da`.

## 4. Perhitungan NDVI Rasional Eksak (D86-1 & D86-2)
- Piksel `(NIR, RED)` integer reflektansi.
- Formula per piksel: `(NIR - RED) / (NIR + RED)`. Mask awan diabaikan dari pembagi/rata-rata.
- Rata-rata per petak dihitung secara rasional eksak dengan pecahan `[numerator, denominator]`.
- Ambang batas dibandingkan tanpa konversi ke float yang tidak presisi:
  - Ambang `0.45` = `45/100 = 9/20`.
  - Petak 1: `2/5 = 8/20 < 9/20` -> tidak cair, status `HELD_CORRECTIVE_PLAN`.
  - Petak 2: `53/90 > 9/20` (karena `53*20 = 1060 > 9*90 = 810`) -> cair, status `DISBURSED`.
- Ratchet kontrak: 2x gagal beruntun memicu eskalasi `RESTRUCTURED_PENDING_APPROVAL`.
- Posting pencairan:
  - Debit: `agri:farmer_loan_ar:IDR` (atau `agri:farmer_loan_receivable:{farmer}:IDR` jika subjek spesifik)
  - Kredit: `agri:escrow_disbursement:IDR`

## 5. DAO Governance & Kontrak Lintas Modul (D86-3, D86-4, D86-5)
- Hak suara bersumber dari:
  1. Karyawan aktif HCM (`Modules\Hcm\Domain\Models\HcmEmployee` aktif).
  2. Pemegang token RWA via K9 `Modules\Rwa\Contracts\VotingWeightReader`.
  3. Franchisee/partner snapshot.
- Snapshot bobot membentuk Merkle Tree, disimpan di `gov_proposals.merkle_root`.
- Setiap vote memverifikasi Merkle Proof voter terhadap `merkle_root`.
- Proposal lolos voting berbobot (YES > NO dan total suara >= kuorum) memancarkan event `gov.proposal.passed.v1` dan otomatis memanggil K10 `ProjectDraftFactory::draftFromProposal(...)` untuk menghasilkan draf proyek EPC status `draft_pending_approval` (tidak langsung mengeksekusi final).
- Audit dua sumber:
  - `governance:audit`: memverifikasi total bobot suara voter vs snapshot, rekalkulasi tallies, dan integritas hash-chain / Merkle root.
  - Audit fixture `GovernanceAuditCorruptionFixture` memicu deteksi selisih.
