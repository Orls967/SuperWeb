<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

/**
 * Service perluasan sinonim pencarian enterprise (Thesaurus Query Expansion).
 * Memetakan istilah operasional, perbankan, armada, dan logistik ke padanan katanya.
 */
class SearchSynonymExpander
{
    /**
     * Peta sinonim dua arah (bidirectional synonyms).
     *
     * @var array<string, string[]>
     */
    private const SYNONYM_DICTIONARY = [
        'mobil' => ['kendaraan', 'armada', 'unit', 'vehicle', 'car'],
        'kendaraan' => ['mobil', 'armada', 'unit', 'vehicle'],
        'armada' => ['fleet', 'kendaraan', 'mobil'],
        'bengkel' => ['workshop', 'servis', 'service', 'perbaikan'],
        'servis' => ['bengkel', 'maintenance', 'perbaikan', 'service'],
        'kontrak' => ['agreement', 'perjanjian', 'mou', 'pks'],
        'perjanjian' => ['kontrak', 'agreement', 'kesepakatan'],
        'faktur' => ['invoice', 'tagihan', 'nota', 'bill'],
        'invoice' => ['faktur', 'tagihan', 'nota'],
        'tagihan' => ['invoice', 'faktur', 'nota'],
        'nota' => ['struk', 'faktur', 'receipt', 'invoice'],
        'kapal' => ['vessel', 'boat', 'tongkang', 'tugboat'],
        'vessel' => ['kapal', 'ship', 'fleet'],
        'kamar' => ['room', 'suite', 'unit'],
        'pesawat' => ['flight', 'aircraft', 'pesawat terbang'],
        'karyawan' => ['pegawai', 'staff', 'employee', 'pekerja'],
        'gudang' => ['warehouse', 'depo', 'storage'],
        'pesanan' => ['order', 'po', 'pemesanan'],
    ];

    /**
     * Memperluas daftar token kueri dengan padanan sinonim yang relevan.
     *
     * @param  string[]  $tokens
     * @return string[] Daftar token yang telah diperluas
     */
    public function expandTokens(array $tokens): array
    {
        $expanded = [];

        foreach ($tokens as $token) {
            $lower = mb_strtolower(trim($token));
            if (empty($lower)) {
                continue;
            }

            $expanded[$lower] = true;

            if (isset(self::SYNONYM_DICTIONARY[$lower])) {
                foreach (self::SYNONYM_DICTIONARY[$lower] as $syn) {
                    $expanded[mb_strtolower($syn)] = true;
                }
            }
        }

        return array_keys($expanded);
    }
}
