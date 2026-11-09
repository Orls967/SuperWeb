# Algoritma CriticalIncidentReplayEngine

## 1. Definisi dan Tujuan
Algoritma `CriticalIncidentReplayEngine` merekonstruksi kronologi kejadian kegawatan henti jantung (*Code Blue*) dan kejadian sentinel secara deterministik. Algoritma ini memvalidasi rantai hash peristiwa (*cryptographic event chain*) untuk membuktikan bahwa tidak ada manipulasi, penyisipan, atau penghapusan data retrospektif dalam catatan medis darurat.

## 2. Struktur Rantai Peristiwa (*Merkle Hash Chain*)
Setiap aksi resusitasi kardiopulmoner dicatat berurutan dengan relasi hash kriptografis:
$$H_0 = \text{SHA-256}(\text{"GENESIS\_CRITICAL\_INCIDENT\_HASH\_2026"})$$
$$H_i = \text{SHA-256}(\text{Sequence}_i \parallel \text{Type}_i \parallel \text{Timestamp}_i \parallel \text{Actor}_i \parallel \text{Payload}_i \parallel H_{i-1})$$

Apabila nilai $H_{i-1}$ yang dicatat tidak identik dengan hash komputasi dari elemen sebelumnya, mesin segera menandai `CHAIN_TAMPERED_AT_SEQUENCE_{i}` dan menggagalkan verifikasi audit.

## 3. Ekstraksi Metrik Klinis Mutu Resusitasi
Algoritma secara otomatis menghitung interval waktu penanganan kritis (*Time-to-Intervention*):
1. **Interval Menuju CPR**: Waktu antara sirine Code Blue aktif hingga penekanan dada pertama dimulai (target AHA: $< 1$ menit).
2. **Interval Menuju Defibrilasi**: Waktu antara sirine aktif hingga kejut listrik pertama dihantarkan pada irama *shockable* (target: $< 2$ menit).
3. **Interval Menuju Epinefrin**: Waktu pemberian vasopresor pertama pada irama *non-shockable* (target: $< 3$ menit).

## 4. Sertifikat Verifikasi Audit
Hasil verifikasi ditandatangani menggunakan HMAC-SHA256 untuk disimpan sebagai lampiran audit mutu komite keselamatan pasien:
```text
SigPayload = "CIRE-V1:{final_merkle_root}:{audit_status}:{total_events}"
Signature = HMAC_SHA256(SigPayload, SECRET_KEY)
```

