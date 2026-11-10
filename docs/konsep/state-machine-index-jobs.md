# Spesifikasi Mesin Status Pekerjaan Indeks: `IndexJobStateMachine`

**Fase:** 194 — Platform: Scope-Aware Global Search & State Machine Tata Kelola Indeks  
**Model:** `CoreSearchIndexJob` (`core_search_index_jobs`)  
**Status Kepatuhan:** 8 Status Deterministik (Audit Trail & Idempotent Guard)

---

## 1. Motivasi & Latar Belakang

Pencarian enterprise mengelola ribuan entitas bisnis yang berubah secara dinamis (work order selesai, resi terbit, kontrak ditandatangani, armada berangkat). Proses pengindeksan data ke dalam tabel terbalik membutuhkan orkestrasi status yang andal untuk mencegah:
1. **Kondisi Balapan (Race Conditions):** Dua pekerja antrean memproses pembaruan entitas yang sama secara bersamaan sehingga menghasilkan data indeks basi atau duplikat.
2. **Kehilangan Jejak Kegagalan (Silent Failures):** Indeksasi yang gagal di tengah jalan tidak terlacak, menyebabkan entitas tidak pernah muncul di hasil pencarian.
3. **Pekerjaan Zombi:** Pekerjaan yang macet tanpa status akhir yang jelas membebani kapasitas antrean worker.

`IndexJobStateMachine` mendefinisikan 8 status siklus hidup yang tegas dengan proteksi kunci idempotensi deterministik.

---

## 2. Definisi 8 Status Siklus Hidup

1. **`pending` (Menunggu):**  
   Pekerjaan indeksasi baru dibuat oleh sistem atau modul sumber, memuat parameter entitas target, rentang batch, dan kunci idempotensi awal. Pekerjaan belum dialokasikan ke pekerja antrean.

2. **`queued` (Dalam Antrean):**  
   Pekerjaan telah didaftarkan ke sistem antrean latar belakang (*background job queue*) dan menunggu ketersediaan pekerja komputasi.

3. **`processing` (Sedang Diproses):**  
   Pekerja antrean telah mengambil pekerjaan secara eksklusif (menggunakan `lockForUpdate`), mengekstrak teks entitas, membersihkan PII, dan memulai tokenisasi leksikal.

4. **`indexed` (Berhasil Terindeks):**  
   Status terminal sukses untuk siklus awal. Seluruh term dokumen telah berhasil dihitung bobot BM25-nya dan tersimpan di tabel `core_search_indices`.

5. **`reindexing` (Pembaruan Indeks):**  
   Status aktif saat entitas yang sudah pernah terindeks mengalami pembaruan data bisnis di modul sumber, memicu pembaruan token parsial atau penuh.

6. **`degraded` (Terdegradasi / Sebagian Gagal):**  
   Pekerjaan batch di mana mayoritas dokumen berhasil diindeks, namun sebagian kecil dokumen mengalami kegagalan validasi atau galat format. Indeks tetap dapat dicari untuk dokumen yang sukses, sementara dokumen yang gagal dicatat untuk perbaikan.

7. **`failed` (Gagal Total):**  
   Pekerjaan mengalami eksepsi fatal (misal putusnya koneksi database atau korupsi skema). Pekerjaan dapat dicoba ulang (*retry*) dengan batas maksimal 3 kali sebelum dialihkan ke status `archived`.

8. **`archived` (Diarsipkan / Terminal):**  
   Status akhir permanen. Entitas telah dihapus dari sistem, atau pekerjaan yang gagal berulang kali dibekukan untuk penyelidikan audit manual (*dead-letter storage*).

---

## 3. Matriks Transisi & Guard Conditions

Setiap transisi status diverifikasi melalui metode guard khusus:
```
+---------------+---------------------------------+---------------------------------------------+
| Status Asal   | Status Tujuan yang Diizinkan    | Syarat Guard & Validasi                     |
+---------------+---------------------------------+---------------------------------------------+
| pending       | queued, archived                | Validasi skema payload & idempotency key    |
| queued        | processing, failed              | Kunci baris eksklusif lockForUpdate         |
| processing    | indexed, degraded, failed       | Hitung tingkat keberhasilan batch           |
| indexed       | reindexing, archived            | Deteksi mutasi versi entitas bisnis         |
| reindexing    | indexed, degraded, failed       | Verifikasi integritas token term baru       |
| degraded      | reindexing, archived            | Penyelesaian backlog dokumen gagal          |
| failed        | queued, archived                | Retry counter < 3, backoff timeout lolos    |
| archived      | (none - status terminal)        | Tidak dapat ditransisikan kembali           |
+---------------+---------------------------------+---------------------------------------------+
```

---

## 4. Mekanisme Kunci Idempotensi Deterministik

Sesuai aturan arsitektur kernel A4, idempotency key dilarang keras menggunakan fungsi waktu (`time()`, `now()`) atau string acak (`Str::random`, `uniqid()`).

Kunci idempotensi untuk mutasi pekerjaan indeks dibangun secara deterministik:
$$\text{IdempotencyKey} = \text{"idx-job:"} \parallel \text{JobUUID} \parallel \text{":"} \parallel \text{TargetStatus}$$

Contoh:
- `idx-job:550e8400-e29b-41d4-a716-446655440000:queued`
- `idx-job:550e8400-e29b-41d4-a716-446655440000:processing`
- `idx-job:550e8400-e29b-41d4-a716-446655440000:indexed`

Apabila perintah transisi dengan kunci yang sama dieksekusi berulang kali, State Machine mengembalikan status saat ini secara idempoten tanpa melakukan mutasi ganda pada basis data.

