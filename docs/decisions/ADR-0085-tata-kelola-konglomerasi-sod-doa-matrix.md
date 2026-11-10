# ADR-0085: Penegakan Pemisahan Tugas (Segregation of Duties) dan Delegasi Otoritas Belanja Berjenjang (Delegation of Authority)

**Status**: DITERIMA  
**Tanggal**: 2026-11-11  
**Pencetus**: Arsitek Solusi Keuangan Grup [W8-P5.3]  
**Konteks**: Modul `EnterpriseFinance` (Fase 54)

---

## 1. Konteks Bisnis & Masalah
Tata kelola korporat yang buruk sering kali dipicu oleh penggabungan wewenang yang tidak pantas (misalnya orang yang sama membuat dan menyetujui anggaran atau pencairan kas), serta belanja modal besar yang disetujui di bawah level manajemen yang semestinya.

## 2. Keputusan yang Diambil
1. **Matriks Segregation of Duties (SoD)**:
   - Didefinisikan aturan pasangan peran terlarang (*conflicting roles*) dengan tiga tingkat keparahan risiko: `CRITICAL`, `HIGH`, `MEDIUM`.
   - Pasangan peran seperti `maker` & `checker`, `treasury_operator` & `treasury_approver`, serta `procurement` & `payment_disburser` dilarang dimiliki oleh pengguna yang sama.
   - Pengecekan otomatis dijalankan setiap kali penugasan peran atau pemrosesan transaksi belanja berlangsung.
2. **Matriks Delegasi Otoritas (DoA - Delegation of Authority)**:
   - Ditetapkan tier limit persetujuan belanja modal (CAPEX) dan operasional (OPEX):
     - Tier 1: Manajer Departemen (hingga Rp 100.000.000)
     - Tier 2: General Manager / Direktur Anak Perusahaan (hingga Rp 1.000.000.000)
     - Tier 3: CFO Holding (hingga Rp 10.000.000.000)
     - Tier 4: CEO & Dewan Komisaris Holding (> Rp 10.000.000.000)
3. **Pemberian Sanksi Sistemik**:
   - Transaksi yang diajukan tanpa mematuhi jenjang persetujuan DoA atau melanggar aturan SoD CRITICAL ditolak secara instan dengan melempar `SodViolationException` atau `UnauthorizedApprovalTierException`.

## 3. Konsekuensi
- Memenuhi standar pengendalian internal SOX / COSO dan meminimalkan risiko kecurangan (*fraud*) internal korporat.
