#!/usr/bin/env bash
set -euo pipefail

# PROGRESS Gate Guard: scripts/merge-guard.sh
# Verifikasi status check-runs CI sebelum PR di-merge (F-01, KONSEP §A14).

if [ "$#" -lt 1 ]; then
    echo "Penggunaan: $0 <nomor-PR>"
    exit 1
fi

PR="$1"
REPO="${GITHUB_REPOSITORY:-Orls967/superweb}"

echo "Memeriksa check-runs untuk PR #${PR} di repository ${REPO}..."

# Dukungan fixture/mock untuk unit testing
if [ -n "${MERGE_GUARD_CHECK_RUNS_JSON:-}" ] && [ -f "${MERGE_GUARD_CHECK_RUNS_JSON}" ]; then
    CHECK_RUNS_JSON="$(cat "${MERGE_GUARD_CHECK_RUNS_JSON}")"
else
    # 1. Dapatkan HEAD SHA dari PR
    HEAD_SHA="$(gh api "repos/${REPO}/pulls/${PR}" --jq .head.sha 2>/dev/null || true)"
    if [ -z "${HEAD_SHA}" ]; then
        echo "Galat: Gagal mengambil SHA HEAD untuk PR #${PR} via gh api."
        exit 1
    fi
    echo "PR #${PR} HEAD SHA: ${HEAD_SHA}"

    # 2. Ambil check-runs untuk SHA tersebut
    CHECK_RUNS_JSON="$(gh api "repos/${REPO}/commits/${HEAD_SHA}/check-runs" 2>/dev/null || true)"
fi

if [ -z "${CHECK_RUNS_JSON}" ]; then
    echo "Galat: Gagal mengambil data check-runs."
    exit 1
fi

# Parsing total count dan data check-runs
RUNS_DATA="$(python3 -c '
import json, sys

try:
    data = json.loads(sys.argv[1])
except Exception as e:
    print(f"ERROR: Invalid JSON: {e}")
    sys.exit(2)

check_runs = data.get("check_runs", [])
total = len(check_runs)
print(f"TOTAL:{total}")

has_failure = False
for cr in check_runs:
    name = cr.get("name", "unknown")
    status = cr.get("status", "unknown")
    conclusion = cr.get("conclusion") or "pending"
    
    # Check is healthy if status is completed AND conclusion in ["success", "skipped", "neutral"]
    is_ok = (status == "completed" and conclusion in ["success", "skipped", "neutral"])
    if not is_ok:
        has_failure = True
    print(f"RUN:{name}|{status}|{conclusion}|{1 if is_ok else 0}")

if has_failure:
    print("STATUS:FAILED")
elif total == 0:
    print("STATUS:EMPTY")
else:
    print("STATUS:OK")
' "${CHECK_RUNS_JSON}")"

TOTAL_RUNS="$(echo "${RUNS_DATA}" | grep '^TOTAL:' | cut -d: -f2)"
OVERALL_STATUS="$(echo "${RUNS_DATA}" | grep '^STATUS:' | cut -d: -f2)"

echo ""
echo "=== TABEL CHECK-RUNS PR #${PR} ==="
printf "%-50s | %-12s | %-12s\n" "Nama Check" "Status" "Conclusion"
printf "%s\n" "---------------------------------------------------+--------------+-------------"

while IFS='|' read -r name status conclusion is_ok; do
    printf "%-50s | %-12s | %-12s\n" "${name}" "${status}" "${conclusion}"
done < <(echo "${RUNS_DATA}" | grep '^RUN:' | sed 's/^RUN://')

echo "---------------------------------------------------+--------------+-------------"
echo "Total check-runs: ${TOTAL_RUNS}"
echo ""

if [ "${TOTAL_RUNS}" -eq 0 ] || [ "${OVERALL_STATUS}" = "EMPTY" ]; then
    echo "GALAT: Belum ada check-run sama sekali pada HEAD commit PR #${PR}."
    echo "Merge diblokir sampai CI selesai berjalan."
    exit 1
fi

if [ "${OVERALL_STATUS}" = "FAILED" ]; then
    echo "GALAT: Terdapat check-run yang gagal, dibatalkan, atau masih berjalan."
    echo "Merge diblokir! Dilarang me-merge PR dengan status CI merah (F-01)."
    exit 1
fi

echo "SEMUA CHECK-RUN HIJAU (LULUS)."
echo ""
echo "Untuk melakukan merge secara manual (hanya bila diizinkan pemilik repo):"
echo "  gh pr merge ${PR} --merge"
echo ""
echo "(Peringatan: JANGAN jalankan perintah merge di atas secara otomatis)."
exit 0
