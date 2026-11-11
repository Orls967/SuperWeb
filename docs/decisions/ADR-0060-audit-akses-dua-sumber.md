# ADR-0060: Audit Tata Kelola Akses & Identitas Dua-Sumber dengan Fixture Korupsi Terdaftar

**Status:** ACCEPTED  
**Tanggal:** 2026-11-12  
**Pengambil Keputusan:** Lead Auditor, Platform Architect, Pelaksana W8-P4  
**Konteks Gelombang:** Gelombang 8, Fase 188 (W8-P4.3 / W8-D60)

---

## Konteks & Masalah

Keluaran perintah audit (`access:audit`) tidak boleh bersifat tautologis (hanya mengulang query tanpa validasi independen). Sistem audit yang handal wajib membandingkan dua sumber kebenaran yang berbeda (*two independent sources of truth*), misalnya: membandingkan state rekaman basis data aktif terhadap rantai log imutabel (*hash-chained transitions*) dan sertifikat integritas evaluator.

Selain itu, efektivitas perintah audit wajib dibuktikan melalui *corruption fixture* yang secara sengaja menyuntikkan inkonsistensi data untuk membuktikan bahwa audit dapat mendeteksi manipulasi dengan tepat.

---

## Keputusan Arsitektur

1. **Prinsip Audit Dua-Sumber (`Two-Source Reconciliation`):**
   - **Sumber A:** Kondisi saat ini pada tabel operasional (`core_access_grants`, `core_access_policies`, `core_identity_links`).
   - **Sumber B:** Rantai riwayat transisi imutabel (`core_access_grant_transitions`) dan kalkulasi ulang independen menggunakan mesin evaluator di memori.
   - Pengecekan mencakup:
     - Apakah grant berstatus `active` memiliki riwayat transisi lengkap dari `requested` $\to$ `active`?
     - Apakah terdapat grant yang berstatus `active` padahal masa berlakunya telah melewati `valid_to`?
     - Apakah terdapat tautan identitas aktif yang melanggar ketentuan *double consent*?
2. **Fixture Korupsi Terdaftar (`AccessAuditCorruptionFixture`):**
   - Diimplementasikan di `app/Quality/Audit/Fixtures/AccessAuditCorruptionFixture.php` dan didaftarkan pada `tests/Architecture/baselines/audit-command-fixtures.json`.
   - Mampu menyuntikkan anomali:
     - Korupsi status grant tanpa melalui state machine transisi.
     - Korupsi hash sertifikat evaluasi.
     - Pengubahan tanggal kedaluwarsa secara ilegal.
   - Perintah `access:audit` wajib keluar dengan exit code `0` pada data bersih, dan wajib keluar dengan exit code `1` (`[ACCESS_AUDIT_FAIL]`) ketika fixture korupsi disuntikkan.

---

## Konsekuensi

- **Positif:** Verifikasi integritas tata kelola akses yang tidak dapat dipalsukan, kepatuhan arsitektur teruji secara otomatis.
- **Negatif:** Diperlukan logika rekonsiliasi yang ketat dan perawatan berkala terhadap skema fixture.
- **Kepatuhan:** Diuji di `AuditCommandContractTest` dan `AccessLifecycleTest`.

