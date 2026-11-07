# Peta Jalan Integrasi & Interoperabilitas Arsitektur (Fase 116)

## 1. Posisi Fase 116 dalam Ekosistem Superweb Venue
Fase 116 (Pasar Sekunder Tiket & Dynamic Bundling) merupakan pilar strategis ketiga dalam lini bisnis Venue & Entertainment (KONSEP §10F, Lini 15). Fase ini menghubungkan operasional inti penjualan tiket dan gerbang akses fisik (Fase 89) serta ekonomi malam dan kontrak artis (Fase 90) dengan ekonomi pengalaman terpadu (*integrated experience economy*).

### Matriks Keterhubungan Antar-Fase:
1. **Hubungan dengan Fase 89 (Ticketing & Access Control):**
   Fase 116 mengonsumsi model `EntertainmentTicket`. Tiket yang diterbitkan perdana pada Fase 89 dapat dialihkan secara aman melalui bursa pasar sekunder Fase 116 dengan penonaktifan barcode lama dan penerbitan barcode baru berotasi (`new_ticket_hash`).
2. **Hubungan dengan Fase 90 (Artist Contracts & Night Economics):**
   Fase 116 mengintegrasikan pemotongan royalti promotor dan seniman sebesar $2\%$ dari setiap perputaran tiket sekunder melalui kontrak artis hiburan (G7-K16).
3. **Hubungan dengan Fase 117 (Guest Experience AI, Biometric Entry & Crowd Safety):**
   Fase 116 menyediakan fondasi identitas pembeli sekunder dan token digital yang akan diverifikasi oleh mesin biometrik wajah tanpa tiket fisik (*biometric ticketless entry*) dan manajemen kepadatan kerumunan (*crowd safety*) pada Fase 117 berikutnya.

---

## 2. Kepatuhan Matriks Penyelesaian Fitur (Fase 116.1 – 116.7)
Seluruh sub-item pada `docs/PROGRESS.md` telah diimplementasikan secara komprehensif:

| Kode Fitur | Deskripsi Fungsional | Komponen Utama | Bukti & Sertifikasi |
| :--- | :--- | :--- | :--- |
| **116.1** | Bursa Pasar Sekunder C2C Fan-to-Fan | `TicketResale`, `VenueSecondaryMarketController` | Listing aktif, escrow hold, dan order pembeli |
| **116.2** | Penegakan Plafon Legal Anti-Scalping 120% | `AntiScalpingCapEngine` (V9) | Sertifikat kriptografis & larangan harga $> 120\%$ |
| **116.3** | Aturan Pemindahtanganan Tunggal (Single Transfer) | `SecondaryTicketTransferVerificationService` | Validasi $T \le 1$ dan penolakan resale kedua |
| **116.4** | Penataan Ulang Barcode Berputar (Rotated Barcode) | `ExecuteSecondaryTicketResaleAction` | `new_ticket_hash` unik & pembatalan tiket lama |
| **116.5** | Dynamic Bundling Terpadu (Venue + Hotel + Shuttle) | `DynamicBundlePricer` (V10), `DynamicBundlePackage` | Integrasi G7-K20 & G7-K21 dengan floor guardrail |
| **116.6** | Antrean Daftar Tunggu Prioritas Berbobot | `WaitlistPriorityGovernor` (V11), `TicketWaitlist` | Bobot loyalitas multi-faktor & window 15 menit |
| **116.7** | Pembagian Tertutup Pajak PBJT & Royalti Ledger | `SecondaryMarketTaxWaterfallEngine` (V12) | Konservasi moneter tertutup & audit buku besar |

---

## 3. Strategi Pengujian Otomatis & Jaminan Mutu (QA)
Arsitektur Fase 116 diverifikasi melalui piramida pengujian berstandar industri:
- **Acceptance Tests:** Pengujian alur pengguna menyeluruh dari pendaftaran tiket, evaluasi anti-scalping, alokasi bundling, hingga antrean waitlist (`VenueSecondaryAcceptanceTest`).
- **Algorithm Unit Tests:** Verifikasi ketat vektor hasil versus harapan untuk empat algoritma bersertifikat (V9, V10, V11, V12) beserta verifier HMAC-SHA256.
- **Audit Fixture & Command:** Verifikasi deterministik ketiadaan anomali pembukuan melalui `venue:audit-secondary` dan simulasi sabotase data `VenSecondaryAuditCorruptionFixture`.
- **Route Authorization Ratchet:** Pengujian otorisasi matriks 7.143 asersi untuk memastikan tidak ada celah eskalasi hak akses (*privilege escalation*).

