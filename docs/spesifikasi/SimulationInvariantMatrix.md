# Matriks Invarian Formal Kernel Simulasi (Fase 67)

Dokumen ini memuat matriks lengkap 5 Invarian Utama Kernel Simulasi Superweb, landasan aksiomatis matematis, lokasi penegakan dalam kode, dan respon kegagalan sistemik.

---

## 1. Matriks Lima Invarian Kunci

| No | Nama Invarian | Definisi Matematis / Aksioma | Lokasi Penegakan Kode | Respon Pelanggaran |
|---|---|---|---|---|
| **INV-1** | Monotonisitas Waktu Virtual | $\forall t_{k}, t_{k+1}: t_{k+1} \ge t_k$ | `SimulationInvariantEnforcerService`, `DiscreteEventScheduler` | Melempar `PastEventScheduledException` (`PAST_EVENT`) |
| **INV-2** | Kausalitas Deterministik | $S_t = f(S_{t-1}, K) \implies S_t \equiv S'_t$ | `StreamSeedAndFingerprint`, `SimDiffCommand` | Deteksi divergensi via `sim:diff`, Exit Code 1 |
| **INV-3** | Keseimbangan Ledger Probe | $\sum \text{Debit} - \sum \text{Kredit} = 0$ | `ProbeLedgerBalanceAction`, `VerifyLedgerStep` | Melempar `RuntimeException`, pembatalan transaksi |
| **INV-4** | Zero Sandbox Leakage | $\Delta \text{Rows}_{\text{live}} = 0 \text{ saat sandbox}$ | `SimulationSandboxIsolationService` | Rollback database transaction wajib |
| **INV-5** | Append-Only State Audit | $\forall r \in \text{Transitions}: \text{Immutable}$ | `SimRunTransition::boot()`, `SimTwinState` | Melempar `LogicException` jika mencoba update/delete |

---

## 2. Prosedur Pembuktian Invarian Berkala

Seluruh invarian diuji secara otomatis pada setiap siklus Continuous Integration (CI) melalui skrip `SimKernelAcceptanceTest.php` dan `SimKernelAlgorithmsTest.php`. Manipulasi atau regresi pada salah satu invarian akan menggagalkan gerbang pra-push dan memblokir penggabungan kode ke master.

