# ADR-0034: Rantai Fingerprint Hash SHA-256 Harian dan Persistensi Checkpoint (D34)

- **Status:** DITERIMA
- **Tanggal:** 2026-11-10
- **Pengambil Keputusan:** Tim Arsitektur Core Platform & Pelaksana W8-P3
- **Konteks:** Menjamin auditabilitas kriptografis dan pemulihan checkpoint (resume) deterministik.

---

## 1. Konteks & Masalah

Pada simulasi jangka panjang (365 hingga 1.825 hari), kegagalan infrastruktur di tengah proses dapat membuang hasil komputasi berjam-jam jika tidak ada mekanisme penyimpanan keadaan perantara (*checkpointing*). Di sisi lain, pembuktian bahwa hasil simulasi tidak dimanipulasi secara siluman membutuhkan segel kriptografis yang mengikat setiap hari eksekusi.

## 2. Pilihan yang Dipertimbangkan

1. **Opsi A:** Snapshot seluruh basis data biner (*full database dump*) setiap hari. (DITOLAK: Membutuhkan ratusan gigabyte disk dan performa I/O yang sangat lambat).
2. **Opsi B:** Rantai hash kriptografis harian berbasis JSON kanonikal dengan penyimpanan checkpoint periodik pada interval milestone. (DITERIMA).

## 3. Keputusan

Kami memutuskan untuk mengimplementasikan rantai fingerprint hash SHA-256:
- **Genesis (Hari 0):** $h_0 = \text{SHA256}(\text{"sim-run\|seed="} \cdot \text{seed})$
- **Harian ($i \ge 1$):** $h_i = \text{SHA256}(h_{i-1} \cdot \text{"\|"} \cdot \text{day} \cdot \text{"\|"} \cdot \text{canonicalJson}(\text{summary}_i))$

Checkpoint disimpan ke tabel `sim_checkpoints` pada setiap interval kelipatan 7 hari dan pada hari penutupan simulasi. Checkpoint menyimpan hash rantai berjalan, hash per-handler, dan payload ringkasan kanonikal.

## 4. Konsekuensi

- **Positif:** Jejak audit kriptografis terbukti tahan manipulasi. Sesi yang terhenti di hari ke-200 dapat dilanjutkan (*resume*) dengan jaminan hash akhir yang sama persis.
- **Negatif:** Seluruh ringkasan harian wajib mematuhi aturan kanonikalisasi JSON yang ketat untuk mencegah perbedaan representasi string.

