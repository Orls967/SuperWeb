<?php

declare(strict_types=1);

namespace Modules\Core\Application\Copilot;

/**
 * Immutable aggregated response from the Executive Copilot Engine.
 */
final class ExecutiveCopilotResponse
{
    public readonly bool $integrityVerified;

    /**
     * @param  list<CopilotToolResult>  $toolResults
     * @param  array<string, int|string>  $kpiTable
     * @param  list<string>  $mitigationRecommendations
     */
    public function __construct(
        public readonly string $prompt,
        public readonly string $summary,
        public readonly array $toolResults,
        public readonly array $kpiTable,
        public readonly array $mitigationRecommendations,
        public readonly string $overallProofHash,
        bool $integrityOk,
    ) {
        $this->integrityVerified = $integrityOk;
    }

    /**
     * Format response as an executive Markdown document.
     */
    public function toMarkdown(): string
    {
        $lines = [];
        $lines[] = '# 🤖 ENTERPRISE AI EXECUTIVE COPILOT — LAPORAN EKSEKUTIF';
        $lines[] = '';
        $lines[] = "**Pertanyaan / Instruksi Eksekutif:** `{$this->prompt}`";
        $lines[] = '**Status Integritas Sistem:** '.($this->integrityVerified ? '✅ TERVERIFIKASI INTEGRITAS 100%' : '⚠️ ANOMALI TERDETEKSI');
        $lines[] = "**Master Execution Proof Hash (HMAC-SHA256):** `{$this->overallProofHash}`";
        $lines[] = '';
        $lines[] = '## 📋 Ringkasan Eksekutif';
        $lines[] = $this->summary;
        $lines[] = '';

        $lines[] = '## 🛠️ Tool & Modul yang Dieksekusi';
        foreach ($this->toolResults as $result) {
            $lines[] = "- **{$result->toolName}** [`{$result->moduleScope}`]: {$result->summaryText}";
            $lines[] = "  *Proof Hash:* `{$result->executionProofHash}`";
        }
        $lines[] = '';

        $lines[] = '## 📊 Tabel Konsolidasi Indikator Kunci (KPI)';
        $lines[] = '| Indikator Kunci (KPI) | Nilai Terukur / Metrik |';
        $lines[] = '| :--- | :--- |';
        foreach ($this->kpiTable as $key => $val) {
            $formattedKey = ucwords(str_replace('_', ' ', $key));
            $formattedVal = is_int($val) ? number_format($val, 0, ',', '.') : (string) $val;
            $lines[] = "| {$formattedKey} | `{$formattedVal}` |";
        }
        $lines[] = '';

        if (! empty($this->mitigationRecommendations)) {
            $lines[] = '## 💡 Rekomendasi & Langkah Mitigasi Eksekutif';
            foreach ($this->mitigationRecommendations as $index => $rec) {
                $num = $index + 1;
                $lines[] = "{$num}. {$rec}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'summary' => $this->summary,
            'integrity_verified' => $this->integrityVerified,
            'overall_proof_hash' => $this->overallProofHash,
            'tool_results' => array_map(static fn (CopilotToolResult $r): array => $r->toArray(), $this->toolResults),
            'kpi_table' => $this->kpiTable,
            'mitigation_recommendations' => $this->mitigationRecommendations,
        ];
    }
}
