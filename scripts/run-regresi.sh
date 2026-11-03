#!/usr/bin/env bash
set -euo pipefail

FOLDERS=(
    "tests/Perilaku"
    "tests/Keamanan"
    "tests/Audit"
    "tests/Spine"
    "tests/Acceptance/Hospital"
    "tests/Acceptance/Hotel"
    "tests/Verifikasi"
)

declare -a RESULTS=()
ANY_FAILED=0

echo "=== Menjalankan Suite Regresi SuperWeb ==="

for folder in "${FOLDERS[@]}"; do
    echo ""
    echo "--- Memeriksa: ${folder} ---"
    if [ ! -d "${folder}" ]; then
        echo "[CATATAN] Folder ${folder} belum ada, dilewati."
        RESULTS+=("${folder}|DILEWATI (belum ada)")
        continue
    fi

    echo "Menjalankan: vendor/bin/pest ${folder} --parallel"
    if vendor/bin/pest "${folder}" --parallel; then
        echo "[SUKSES] ${folder} lulus."
        RESULTS+=("${folder}|LULUS")
    else
        EXIT_CODE=$?
        echo "[GAGAL] ${folder} gagal dengan exit code ${EXIT_CODE}."
        RESULTS+=("${folder}|GAGAL (exit ${EXIT_CODE})")
        ANY_FAILED=1
    fi
done

echo ""
echo "=== Ringkasan Suite Regresi ==="
printf "+------------------------------------------+-----------------------+\n"
printf "| %-40s | %-21s |\n" "Folder" "Hasil"
printf "+------------------------------------------+-----------------------+\n"
for item in "${RESULTS[@]}"; do
    FOLDER_NAME="${item%%|*}"
    RESULT_TEXT="${item##*|}"
    printf "| %-40s | %-21s |\n" "${FOLDER_NAME}" "${RESULT_TEXT}"
done
printf "+------------------------------------------+-----------------------+\n"

if [ "${ANY_FAILED}" -ne 0 ]; then
    echo "❌ Terdapat kegagalan pada suite regresi."
    exit 1
fi

echo "✅ Seluruh suite regresi berhasil lulus."
exit 0

