<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Contracts;

interface ComplianceDeadlineSource
{
    /**
     * Nama sumber penyedia kewajiban regulasi / kepatuhan.
     */
    public function getSourceName(): string;

    /**
     * Mengambil daftar seluruh tenggat waktu regulasi per tanggal rujukan.
     *
     * @return list<array{
     *     code: string,
     *     title: string,
     *     category: string,
     *     due_date: string,
     *     responsible_role: string,
     *     entity_code: string,
     *     penalty_estimate_minor: int
     * }>
     */
    public function getDeadlines(string $asOfDate): array;
}

