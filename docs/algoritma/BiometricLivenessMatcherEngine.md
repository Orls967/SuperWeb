# Spesifikasi Algoritma V13: BiometricLivenessMatcherEngine

## 1. Ikhtisar & Deskripsi Formal
`BiometricLivenessMatcherEngine` adalah mesin pencocokan biometrik nir-sentuh berbasis liveness challenge-response dan kriptografi non-reversible. Algoritma ini dirancang khusus untuk gerbang turnstile otomatis venue guna memverifikasi kehadiran fisik pemilik wajah tanpa risiko spoofing foto/video dan tanpa menyimpan vektor fitur wajah mentah di penyimpanan data.

## 2. Parameter Input & Output
- **Input:**
  - `userId` (int): Identitas anggota / pengunjung.
  - `featureVector` (string): Vektor ekstraksi koordinat landmark wajah terenkripsi sementara di memori.
  - `challengeProof` (string): Respons tantangan keaktifan liveness (misal kedipan mata, senyum, atau rotasi sumbu yaw/pitch).
  - `saltSecret` (string): Garam rahasia kriptografis per-venue.
  - `minLivenessThresholdBps` (int, default: 8500): Ambang minimum pembuktian keaktifan (85%).
- **Output (`BiometricMatchCertificate`):**
  - `isMatched` (bool): Apakah wajah cocok dengan templat terdaftar.
  - `livenessScoreBps` (int): Skor kepercayaan keaktifan fisik (0 - 10000 bps).
  - `templateHash` (string): Hash SHA-256 bergaram 64-karakter dari templat wajah.
  - `certificateSignature` (string): Tanda tangan HMAC-SHA256 untuk audit turnstile.
  - `auditTimestamp` (int): Timestamp Unix saat pencocokan dieksekusi.

## 3. Formulasi & Invarian Matematis
1. **Liveness Scoring Function:**
   $$S_{\text{liveness}} = \mathcal{H}_{\text{HMAC}}(C_{\text{proof}}, K_{\text{liveness}}) \pmod{10000}$$
   Jika $S_{\text{liveness}} < \tau_{\text{liveness}}$, pencocokan dibatalkan seketika (`ERR_LIVENESS_FAILED`).
2. **One-Way Irreversible Template Mapping:**
   $$H_{\text{template}} = \text{HMAC-SHA256}(\mathbf{v}_{\text{feature}} \parallel S_{\text{salt}}, K_{\text{master}})$$
   Pemetaan ini dijamin one-way (pre-image resistant): mustahil merekonstruksi $\mathbf{v}_{\text{feature}}$ dari $H_{\text{template}}$.
3. **Deterministic Verification:**
   $$\text{Match} = \left( H_{\text{probe}} \equiv H_{\text{enrolled}} \right) \land \left( S_{\text{liveness}} \ge \tau \right)$$

## 4. Vektor Uji Algoritma
| Kasus Uji | Input Feature | Liveness Proof | Harapan Hasil |
|---|---|---|---|
| Valid Member | `FEAT-FACE-8899-AAA` | `CHALLENGE-BLINK-SUCCESS` | Match: `true`, Liveness $\ge 8500$, Sertifikat Valid |
| Spoofing Photo | `FEAT-FACE-8899-AAA` | `STATIC-PHOTO-REPLAY` | Reject: Liveness $< 8500$, Exception dilempar |
| Template Mismatch | `FEAT-FACE-UNKNOWN-000` | `CHALLENGE-BLINK-SUCCESS` | Reject: Template Mismatch, `isMatched = false` |
| Tampered Salt | `FEAT-FACE-8899-AAA` | `CHALLENGE-BLINK-SUCCESS` | Reject: Signature Hash Invalid |

