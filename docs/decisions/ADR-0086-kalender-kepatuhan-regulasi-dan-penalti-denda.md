# ADR-0086: Kalender Kepatuhan Regulasi Terpadu dan Estimasi Sanksi Finansial Keterlambatan Pelaporan

**Status**: DITERIMA  
**Tanggal**: 2026-11-11  
**Pencetus**: Arsitek Solusi Keuangan Grup [W8-P5.3]  
**Konteks**: Modul `EnterpriseFinance` (Fase 54)

---

## 1. Konteks Bisnis & Masalah
Perusahaan konglomerasi tunduk pada ratusan tenggat waktu pelaporan regulasi per tahun (Direktorat Jenderal Pajak, Otoritas Jasa Keuangan, Bursa Efek Indonesia, Bank Indonesia, dan BKPM). Keterlambatan satu pelaporan dapat menimbulkan denda finansial, sanksi administratif, suspensi saham, hingga pencabutan izin operasional.

## 2. Keputusan yang Diambil
1. **Pusat Registrasi Kepatuhan Regulasi (`ef_compliance_deadlines`)**:
   - Menata jadwal tenggat waktu berkala (bulanan, kuartalan, tahunan) beserta otoritas pengawas, kategori kepatuhan, penanggung jawab (*assigned role*), dan status pemenuhan (`pending`, `fulfilled`, `overdue`).
2. **Kalkulasi Otomatis Sanksi Denda Keterlambatan**:
   - Denda keterlambatan dihitung berdasar peraturan perundang-undangan (misalnya denda administrasi UU KUP untuk SPT Masa/Tahunan, atau denda per hari keterlambatan OJK).
   - Nilai estimasi penalti dihitung dalam integer rupiah dan diposting ke provisi beban kepatuhan jika status berubah menjadi `overdue`.
3. **Eskalasi Otomatis H-7 dan H-1**:
   - Notifikasi terjadwal dikirim ke pimpinan unit bisnis dan auditor kepatuhan korporat sebelum jatuh tempo.

## 3. Konsekuensi
- Menghilangkan risiko denda akibat kelalaian administratif; memastikan reputasi tata kelola kepatuhan (*regulatory compliance*) konglomerasi tetap terjaga.
