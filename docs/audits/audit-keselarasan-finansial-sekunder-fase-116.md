# Audit Keselarasan Finansial Pasar Sekunder (Fase 116)

## 1. Tujuan & Metodologi Audit
Audit keselarasan finansial pasar sekunder bertujuan untuk membuktikan secara empiris dan deterministik bahwa seluruh transaksi penjualan kembali tiket (*secondary ticket resale*) pada modul Superweb Venue:
1. Menegakkan hukum konservasi moneter tertutup ($\sum \text{komponen} \equiv \text{harga bruto}$),
2. Tidak memicu distorsi liabilitas pajak daerah PBJT (10%),
3. Mencegah manipulasi perputaran ganda tiket (*anti-double spending* dan *single transfer limit*),
4. Memelihara konsistensi buku besar pembukuan ganda (*double-entry secondary ledger*).

Audit ini dijalankan secara otomatis melalui perintah konsol `php artisan venue:audit-secondary` (atau alias `php artisan ven:audit-secondary`).

---

## 2. Parameter Invarian yang Diverifikasi
Setiap putaran audit mengevaluasi empat invarian keras:

### Invarian 1: Integritas Konservasi Air Terjun Pajak & Komisi
Untuk setiap order transaksi sekunder yang berstatus `SETTLED`:
$$\text{resale\_price\_idr} = \text{pbjt\_tax\_idr} + \text{platform\_fee\_idr} + \text{promoter\_royalty\_idr} + \text{seller\_payout\_idr}$$
Bila ditemukan order dengan selisih $\Delta \ne 0$, audit seketika mengembalikan kode keluar `Exit 1` dan mencatat ID transaksi ke daftar pelanggaran finansial.

### Invarian 2: Ketunggalan Pemindahtanganan Tiket (Single Transfer Guarantee)
Untuk setiap tiket hiburan yang terdaftar di sistem:
$$\operatorname{count}\left(\{r \in \text{TicketResale} \mid r.\text{ticket\_id} = t.\text{id} \land r.\text{status} = \text{'SETTLED'}\}\right) \le 1$$
Tiket yang telah sukses diperdagangkan satu kali di pasar sekunder secara mutlak dilarang memiliki order `SETTLED` kedua. Pelanggaran aturan ini mengindikasikan upaya calo berantai (*chained scalping arbitrage*).

### Invarian 3: Konsistensi Buku Besar Sekunder (Ledger Cross-Check)
Total nilai bruto transaksi yang tercatat pada tabel ringkasan pesanan `ven_ticket_resales` harus cocok persis dengan total debet/kredit yang tercatat pada buku besar akuntansi `ven_secondary_ledger_entries`:
$$\sum_{r \in \text{SettledResales}} r.\text{resale\_price\_idr} = \sum_{l \in \text{SettlementLedger}} l.\text{gross\_amount\_idr}$$

### Invarian 4: Keberadaan Token Barcode Berotasi (Rotated Ticket Hash Integrity)
Setiap transaksi `SETTLED` wajib memiliki kolom `new_ticket_hash` yang tidak kosong dan berupa string hex SHA-256 valid (tepat 64 karakter) untuk membuktikan bahwa tiket lama telah dinonaktifkan dan tiket baru telah diterbitkan kepada pembeli sah.

---

## 3. Uji Sabotase & Mekanisme Fixture Kerusakan (Corruption Fixture)
Untuk menjamin sensitivitas audit terhadap kecurangan data, sistem dilengkapi dengan fixture uji kerusakan deterministik `VenSecondaryAuditCorruptionFixture`:
1. **Skenario Sabotase 1 (Pencurian Saldo Penjual):**
   Fixture mengurangi `seller_payout_idr` sebesar Rp 50.000 tanpa mengubah total bruto, sehingga $\sum \text{komponen} < \text{resale\_price\_idr}$.
   - **Hasil:** Audit mendeteksi anomali konservasi dan menggagalkan eksekusi dengan `Exit 1`.
2. **Skenario Sabotase 2 (Penjualan Ganda Ilegal):**
   Fixture memanipulasi baris tiket sekunder agar dapat diperdagangkan kedua kalinya.
   - **Hasil:** Audit mendeteksi pelanggaran *Single Transfer Limit* dan menandai ID tiket sebagai korupsi data.

Setelah uji sabotase membuktikan alarm audit bekerja secara sempurna, fixture memulihkan (*rollback*) basis data ke kondisi bersih semula.

