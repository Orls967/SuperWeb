# Spesifikasi Arsitektur: Analitik & Telemetri Kueri Pencarian Enterprise

**Fase:** 194 — Platform: Search Telemetry, Latency Auditing & Zero-Result Analytics  
**Modul:** `Core` & `Shared`  
**Model Log:** `CoreSearchQueryLog` (`core_search_query_logs`)

---

## 1. Tujuan & Manfaat Bisnis

Analitik pencarian memberikan visibilitas mendalam terhadap perilaku pencarian pengguna, kualitas penemuan informasi (*discoverability*), dan efisiensi operasional platform di 30 lini bisnis:
1. **Identifikasi Kebutuhan Pengguna yang Belum Terpenuhi:** Mengidentifikasi kata kunci yang dicari ratusan kali namun menghasilkan 0 hasil (*zero-result queries*), menandakan perlunya penambahan modul, dokumen, atau alias leksikal baru.
2. **Pemantauan Latensi Respons:** Memastikan kueri pencarian instan (Ctrl+K) merespons dalam waktu $< 50$ milidetik agar pengalaman pengguna tetap fluida dan responsif.
3. **Deteksi Anomali & Pola Eksfiltrasi:** Mencatat lonjakan kueri yang tidak biasa dari pengguna tertentu untuk mencegah otomatisasi *scraping* atau *probing* dokumen rahasia.

---

## 2. Struktur Tabel Telemetri `core_search_query_logs`

Setiap permintaan pencarian dicatat secara asinkron ke tabel log audit:

```sql
CREATE TABLE core_search_query_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    query_text VARCHAR(255) NOT NULL,
    normalized_query VARCHAR(255) NOT NULL,
    user_id BIGINT NULL,
    user_role VARCHAR(64) NOT NULL,
    tenant_id VARCHAR(64) NOT NULL,
    results_count INT NOT NULL DEFAULT 0,
    latency_ms DECIMAL(8, 2) NOT NULL,
    suggested_correction VARCHAR(255) NULL,
    clicked_entity_type VARCHAR(64) NULL,
    clicked_entity_id VARCHAR(128) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_norm_query (normalized_query),
    INDEX idx_user_role (user_role),
    INDEX idx_created_at (created_at)
);
```

---

## 3. Metrik-Metrik Kunci Kualitas Pencarian

Dasbor Analitik Pencarian (`/admin/search/analytics`) menampilkan indikator kinerja utama:
- **Tingkat Kueri Berhasil (Search Success Rate):** Persentase kueri yang menghasilkan setidaknya 1 dokumen relevan ($> 90\%$).
- **Tingkat Nol Hasil (Zero-Result Rate):** Persentase kueri yang tidak menemukan dokumen sama sekali ($< 8\%$).
- **Latensi Rata-Rata (Mean Search Latency):** Rata-rata waktu eksekusi penelusuran leksikal ($< 35$ ms).
- **Rasio Koreksi Saltik Diterima (Correction Acceptance Ratio):** Persentase pengguna yang mengklik saran "mungkin maksud Anda" ketika kueri awal salah ketik ($> 65\%$).

---

## 4. Mekanisme Pembersihan Log Otomatis (Retention Policy)

Untuk mencegah pembengkakan ukuran basis data:
- Log kueri mentah disimpan selama 90 hari.
- Perintah terjadwal `search:audit` melakukan agregasi statistik mingguan ke dalam tabel ringkasan dan mengarsipkan log kueri yang berusia $> 90$ hari.
