# ADR-0062: Audit Trail Forensik Kriptografis Kejadian Henti Jantung (Code Blue)

## Status
Diterima (Accepted)

## Konteks
Kejadian henti jantung mendadak (*in-hospital cardiac arrest*) merupakan insiden paling kritis dalam operasional rumah sakit dengan risiko mediko-legal tinggi. Evaluasi mutu resusitasi kardiopulmoner menuntut kepastian waktu respons yang presisi: waktu inisiasi kompresi dada (< 1 menit), waktu defibrilasi kejut pertama (< 2 menit), dan waktu pemberian vasopresor epinefrin (< 3 menit).

Pencatatan konvensional pasca resusitasi rentan terhadap bias ingatan klinisi (*recall bias*) atau potensi perubahan retrospektif catatan waktu tindakan untuk menutupi keterlambatan respons. Diperlukan mekanisme audit trail yang *tamper-evident*, berurutan secara matematis, dan dapat diverifikasi ulang kapan saja oleh komite keselamatan pasien dan auditor independen.

## Keputusan Arsitektur
1. **Rantai Hash Berantai (Merkle Event Chain)**: Setiap tindakan klinis dalam siklus resusitasi (aktivasi, kompresi, kejut, obat, intubasi, evaluasi irama, hasil akhir ROSC/kematian) dicatat berurutan dengan relasi kriptografis:
   $$H_i = \text{SHA-256}(\text{Sequence}_i \parallel \text{Type}_i \parallel \text{Timestamp}_i \parallel \text{Actor}_i \parallel \text{Payload}_i \parallel H_{i-1})$$
2. **Mesin Replay Deterministik**: Komponen `CriticalIncidentReplayEngine` membaca seluruh rangkaian event timeline dan menghitung ulang hash rantai secara independen. Setiap upaya penyuntingan, penyisipan, atau penghapusan record di masa lampau akan seketika menghasilkan galat status `CHAIN_TAMPERED_AT_SEQUENCE_{i}`.
3. **Penerbitan Sertifikat Integritas Resusitasi**: Mesin menghasilkan sertifikat bertanda tangan HMAC-SHA256 (`CIRE-V1`) yang menyertakan merkle root akhir, ringkasan interval waktu intervensi pertama, dan status validitas audit.
4. **Keterikatan dengan Paspor Medis Pasien**: Bukti sertifikat audit insiden ditautkan ke paspor kesehatan pasien (`PatientPassportEvent`) sehingga tidak dapat dipisahkan dari rekam jejak perawatan seumur hidup pasien.

## Konsekuensi
- **Positif**:
  - Memberikan kepastian hukum dan perlindungan mediko-legal yang mutlak bagi tim dokter dan perawat yang bertugas sesuai standar ACLS/AHA.
  - Memungkinkan audit mutu objektif untuk pembelajaran berkelanjutan komite resusitasi rumah sakit tanpa keraguan integritas data.
- **Negatif**:
  - Petugas pencatat waktu resusitasi (*timekeeper*) memerlukan disiplin input waktu yang ketat selama situasi gawat darurat berlangsung.
