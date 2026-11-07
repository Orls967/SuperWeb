# Spesifikasi & Pembuktian Matematis: LoyaltyNightTierEngine (H7)

## 1. Definisi Masalah
Sistem loyalitas hotel memerlukan penetapan tingkat keanggotaan (*membership tier*) yang adil dan transparan berbasis jumlah malam menginap terakumulasi dalam 365 hari terakhir ($N_{total} = N_{prior} + N_{stay}$), serta formula perolehan poin dengan pengali (*points multiplier*).

## 2. Model Kualifikasi Tingkat Membership

$$Tier(N) = \begin{cases}
\text{PLATINUM}, & \text{bila } N \ge 50 \quad (Multiplier = 1.50) \\
\text{GOLD}, & \text{bila } 25 \le N < 50 \quad (Multiplier = 1.30) \\
\text{SILVER}, & \text{bila } 10 \le N < 25 \quad (Multiplier = 1.15) \\
\text{BASIC}, & \text{bila } N < 10 \quad (Multiplier = 1.00)
\end{cases}$$

## 3. Formula Perolehan Poin (Points Accrual)
Poin dasar diperoleh dari setiap kelipatan IDR 10.000 pembelanjaan kamar yang memenuhi syarat ($S_{eligible}$):
$$P_{base} = \lfloor \frac{S_{eligible}}{10.000} \rfloor \times 10$$
Poin akhir dihitung dengan pembulatan terhadap pengali tingkat:
$$P_{earned} = \text{round}(P_{base} \times Multiplier)$$
Saldo poin aktif:
$$Balance_{active} = Balance_{existing} + P_{earned}$$

## 4. Jaminan Sertifikasi
Sertifikat `LoyaltyTierCertificate` mengunci data malam, tier, multiplier, poin diperoleh, dan privileges khusus dengan SHA-256 checksum anti-pemalsuan.

