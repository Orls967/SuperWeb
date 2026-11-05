# Desain Arsitektur — Fase 159: Life, Health & Wellness Insurance Advanced

## 1. Arsitektur Domain & ERD

### Tabel-Tabel Baru:
1. `ins_life_policies`: Polis jiwa (term life, saving endowment), usia masuk, uang pertanggungan, premi berkala, status (`active`, `lapsed`, `matured`, `claimed`).
2. `ins_beneficiaries`: Penerima manfaat polis jiwa terhubung ke Party, persentase alokasi hak waris (total = 10.000 bps = 100%).
3. `ins_health_policies`: Polis kesehatan murni, tier benefit (`basic`, `deluxe`, `vip`), plafon kamar harian, limit tahunan, deductible minor unit.
4. `ins_guarantee_letters`: Surat Jaminan Rawat Inap (GL) cashless ke RS, nomor encounter, estimasi biaya, approval limit.
5. `ins_health_claims`: Klaim kesehatan rawat inap/jalan (cashless / reimbursement), status (`submitted`, `approved`, `settled`, `appealed`, `rejected`).
6. `ins_wellness_logs`: Log telemetri kebugaran, langkah harian, denyut rata-rata, skor validitas anti-gaming.
7. `ins_wellness_rewards`: Penghargaan diskon premi atau voucher atas pencapaian target aktivitas sehat.
8. `ins_unit_link_funds`: Dana investasi unit link (`EQUITY_AGGRESSIVE`, `BALANCED`, `FIXED_INCOME_SECURE`), nilai unit NAV awal.
9. `ins_unit_link_nav_histories`: Riwayat harian NAV per unit dana dan total AUM.
10. `ins_unit_link_holdings`: Saldo unit yang dimiliki polis nasabah, akumulasi unit, nilai pasar saat ini.

---

## 2. Algoritma A7: `TermLifePremium`
Formula aktuaria ekuivalensi aktuaria asuransi jiwa berjangka (Term Life $n$-tahun):
$$\text{Net Single Premium } A_{x:\overline{n}|}^1 = \sum_{t=0}^{n-1} v^{t+1} \cdot {}_t p_x \cdot q_{x+t}$$
Di mana:
- $v = (1 + i)^{-1}$ (faktor diskonto suku bunga aktuaria $i$).
- $q_{x+t}$ (probabilitas mortalitas pada usia $x+t$ berdasarkan Tabel Mortalitas Indonesia TMI IV / CSO).
- ${}_t p_x$ (probabilitas bertahan hidup dari usia $x$ hingga $x+t$).
- Annual Net Level Premium $P = \frac{A_{x:\overline{n}|}^1}{\ddot{a}_{x:\overline{n}|}}$.

Result class `TermLifePremiumResult` memuat:
- Nilai premi murni tahunan (integer IDR minor unit).
- Matriks komutasi ($D_x, N_x, C_x, M_x$).
- Sertifikat hash kanonikal diverifikasi oleh `TermLifePremiumCertificateVerifier`.

---

## 3. Kontrak Lintas Modul G7-K4: `EncounterBillReader`
Dideklarasikan di `Modules\Hospital\Contracts\EncounterBillReader`:
- Membaca nomor registrasi rawat inap RS, mendiagnosis kode ICD-10, dan memberikan estimasi billing breakdown.
- Diimplementasikan di `Modules\Hospital\Application\Services\HospitalEncounterBillReaderService`.

---

## 4. Akun Ledger yang Terlibat
- `ins:life_reserve_pool:IDR`: Cadangan klaim aktuaria asuransi jiwa.
- `ins:health_claim_clearing:IDR`: Rekening perantara cashless RS.
- `ins:unit_link_holdings:IDR`: Liabilitas kepemilikan unit nasabah.
- `bank:cash:IDR`: Kas masuk/keluar.

