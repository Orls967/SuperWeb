# Standar Penulisan Kebijakan Otorisasi ABAC (Fase 188)

Dokumen ini menjadi acuan baku bagi pengembang dan administrator keamanan dalam menyusun aturan kebijakan *Attribute-Based Access Control* (ABAC) di platform SuperWeb.

---

## 1. Struktur Standar Dokumen Kebijakan

Setiap kebijakan ABAC dinyatakan dalam skema JSON terstruktur dengan elemen-elemen pokok berikut:

```json
{
  "policy_code": "POL-FIN-DISBURSE-01",
  "name": "Persetujuan Pencairan Dana Keuangan > Rp 500 Juta",
  "effect": "DENY",
  "priority": 10,
  "rules": [
    {
      "rule_code": "POL-FIN-DISBURSE-01-R1",
      "effect": "DENY",
      "description": "Menolak pencairan di luar jam kerja kecuali sesi break-glass aktif",
      "target": {
        "resource_type": "disbursement_order",
        "action_code": "finance.disburse"
      },
      "conditions": {
        "AND": [
          {
            "attribute": "environment.is_business_hours",
            "operator": "EQUALS",
            "value": false
          },
          {
            "attribute": "subject.is_break_glass",
            "operator": "EQUALS",
            "value": false
          }
        ]
      },
      "obligations": ["LOG_SECURITY_ALERT", "NOTIFY_FINANCE_DIRECTOR"]
    }
  ]
}
```

---

## 2. Hirarki Operator Kondisi

Evaluator `ConditionExpressionEvaluator` mendukung operator logika majemuk dan atomik:

| Kategori | Operator | Format Nilai yang Diterima | Contoh Kasus |
|---|---|---|---|
| **Logika Majemuk** | `AND`, `OR`, `NOT` | Array objek kondisi anak | Kombinasi aturan jam kerja DAN otentikasi MFA |
| **Kesetaraan** | `EQUALS`, `NOT_EQUALS` | Tipe data skalar (string, int, bool) | `resource.classification EQUALS 'RESTRICTED'` |
| **Himpunan** | `IN`, `NOT_IN` | Array of scalar | `subject.roles IN ['ciso', 'secops_lead']` |
| **Rentang Angka** | `GT`, `GTE`, `LT`, `LTE`, `NUMERIC_BETWEEN` | Numeric atau array [min, max] | `resource.amount_idr GT 500000000` |
| **Jaringan & IP** | `IP_IN_CIDR` | Notasi CIDR IPv4 / IPv6 | `subject.ip_address IP_IN_CIDR '10.200.0.0/16'` |
| **Pola String** | `CONTAINS`, `STARTS_WITH`, `MATCHES_REGEX` | String / Regular Expression | `action.code STARTS_WITH 'admin.'` |

---

## 3. Praktik Terbaik Efisiensi Memori & Keamanan
1. **Prioritaskan Aturan DENY**: Letakkan aturan penolakan beresiko tinggi pada prioritas teratas (`priority < 50`) agar evaluasi dapat melakukan *early exit*.
2. **Hindari Regex Rumit**: Gunakan operator `STARTS_WITH` atau `IN` dibanding regex yang rentan terhadap kerentanan ReDoS (*Regular Expression Denial of Service*).
3. **Selalu Tentukan Target Spesifik**: Aturan tanpa target eksplisit akan mencocokkan seluruh sumber daya, memperlambat proses evaluasi global.

