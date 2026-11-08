<?php

declare(strict_types=1);

namespace Modules\Aviation\Integrations;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Logistics\Contracts\CustodyVerifier;

class LogisticsCargoIntegrationService
{
    public function __construct(
        private readonly ?CustodyVerifier $custodyVerifier = null
    ) {}

    /**
     * Memverifikasi rantai integritas kustodi kargo udara terhadap modul Logistics (K6).
     *
     * @param AviationCargoConsignment $consignment
     * @return bool
     */
    public function verifyCargoCustodyChain(AviationCargoConsignment $consignment): bool
    {
        if (empty($consignment->custody_hash_chain)) {
            return false;
        }

        if ($this->custodyVerifier !== null) {
            try {
                $verification = $this->custodyVerifier->verifyDocumentHash(
                    documentType: 'POD',
                    sha256Hex: $consignment->custody_hash_chain
                );

                if ($verification !== null) {
                    return (bool) ($verification['chain_valid'] ?? false);
                }
            } catch (Exception $e) {
                Log::warning("Gagal verifikasi kustodi kargo {$consignment->consignment_number} ke modul Logistics: ".$e->getMessage());
            }
        }

        // Validasi format SHA-256 lokal
        return strlen($consignment->custody_hash_chain) === 64
            && ctype_xdigit($consignment->custody_hash_chain);
    }
}
