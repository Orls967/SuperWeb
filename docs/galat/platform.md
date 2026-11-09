# Katalog Galat & Pemecahan Masalah Modul Platform

Dokumen ini mencantumkan kode galat, penyebab umum, dan tindakan pemulihan untuk modul Platform (`plt_`).

| Kode Galat | Pesan Kesalahan | Penyebab Umum | Langkah Pemulihan |
| :--- | :--- | :--- | :--- |
| `PLT-ERR-001` | `InvalidSandboxStateTransitionException` | Transisi status sandbox tidak diizinkan oleh state machine (misal langsung dari `requested` ke `ready`). | Periksa status saat ini pada tabel `plt_sandboxes`. Ikuti alur transisi standar: `requested` $\rightarrow$ `provisioning` $\rightarrow$ `seeded` $\rightarrow$ `ready`. |
| `PLT-ERR-002` | `SandboxAllocationLimitExceededException` | Pengguna telah mencapai batas kuota sandbox aktif (maksimal 5 per pengembang). | Hancurkan sandbox yang sudah tidak terpakai menggunakan `DestroySandboxAction` atau jalankan `platform:sandbox-expire`. |
| `PLT-ERR-003` | `PiiLeakageDetectedException` | Verifikasi masking mendeteksi adanya data pribadi mentah (email asli atau NIK valid) pada dataset hasil penyamaran. | Tinjau aturan masking pada `plt_mask_rules`. Pastikan ekspresi reguler kolom cocok dan salt tokenization terkonfigurasi. |
| `PLT-ERR-004` | `ErrorBudgetExhaustedException` | Layanan telah menghabiskan seluruh error budget bulanan/mingguan yang dialokasikan. | Tahan deployment non-kritis (freeze) untuk layanan terkait. Lakukan stabilisasi dan catat postmortem insiden. |
| `PLT-ERR-005` | `InvalidDuplicateCertificateSignatureException` | Tanda tangan digital pada sertifikat duplikasi Rabin-Karp tidak cocok dengan konten. | Jalankan ulang kalkulator metrik secara penuh menggunakan `DuplicateWindowDetector` untuk menghasilkan sertifikat baru yang valid. |
| `PLT-ERR-006` | `HighDecisionDensityException` | Ditemukan kelas dengan kerapatan percabangan logika ekstrem ($CC > 20$ atau $NPath > 200$). | Refaktor method menjadi unit-unit yang lebih kecil menggunakan extract method atau strategi polymorphism. |
| `PLT-ERR-007` | `AnemicClassDetectedException` | Ditemukan kelas bervolume besar (> 30 baris) dengan $CC = 1$ tanpa logika domain. | Hapus atau integrasikan kelas anemik tersebut. Hindari padding kode buatan. |
| `PLT-ERR-008` | `OrphanClassDetectedException` | Kelas tidak dapat dijangkau dari entry point sistem mana pun (dead code). | Daftarkan entry point jika kelas memang dipanggil via refleksi, atau hapus kelas yang sudah tidak digunakan. |
| `PLT-ERR-009` | `CorrelationHeaderMissingException` | Request HTTP internal tidak menyertakan header `X-Correlation-Id` atau `traceparent`. | Pastikan `CorrelationIdMiddleware` aktif pada pipeline global middleware aplikasi. |
| `PLT-ERR-010` | `AuditHashTamperedException` | Verifikasi berkala mendeteksi inkonsistensi hash SHA-256 pada rantai audit trail `plt_audit_runs`. | Lakukan investigasi keamanan mendalam. Periksa apakah ada update manual langsung pada basis data tanpa melalui action service. |

