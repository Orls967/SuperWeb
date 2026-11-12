<x-ds.shell title="Paket Bukti Audit Eksternal (Audit Data Pack)">
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Enterprise Finance', 'url' => '/enterprise-finance'],
            ['label' => 'Audit Data Pack', 'url' => '#'],
        ]" />

        @if(session('status'))
            <x-ds.toast type="success" :message="session('status')" />
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-1">
                <x-ds.card title="Generate Paket Data Bukti Audit">
                    <form action="{{ route('enterprise_finance.audit_pack.generate') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label for="pack_entity_code" class="block text-sm font-medium text-gray-700">Kode Entitas</label>
                            <input id="pack_entity_code" type="text" name="entity_code" value="CORP-HOLDING" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm" required />
                        </div>

                        <div>
                            <label for="pack_period" class="block text-sm font-medium text-gray-700">Periode Fiskal (YYYY-MM)</label>
                            <input id="pack_period" type="text" name="period" value="{{ date('Y-m', strtotime('-1 month')) }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm font-mono" required />
                        </div>

                        <div class="p-3 bg-blue-50 border border-blue-200 rounded text-xs text-blue-900">
                            <strong>Isi Paket Data:</strong>
                            <ul class="list-disc list-inside mt-1 space-y-0.5 text-xs text-blue-800">
                                <li>Buku Besar Lengkap (GL Entries)</li>
                                <li>Daftar Rekonsiliasi Subledger</li>
                                <li>Laporan Pajak & SPT Rekapitulasi</li>
                                <li>Log Akses & Pengecualian SoD</li>
                                <li>Manifest Hash SHA-256 Tersegel</li>
                            </ul>
                        </div>

                        <x-ds.button type="submit" variant="primary">Generate & Segel Paket ZIP</x-ds.button>
                    </form>
                </x-ds.card>
            </div>

            <div class="md:col-span-2">
                <x-ds.card title="Manifest Integritas & Status Ekspor Audit Terakhir">
                    <div class="space-y-4">
                        <div class="p-4 bg-gray-50 rounded border text-sm space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-800">Format Paket Bukti Audit</span>
                                <x-ds.badge variant="primary">KAP / Big-4 Compliant</x-ds.badge>
                            </div>
                            <p class="text-xs text-gray-600">
                                Seluruh berkas diekspor dalam format CSV terenkripsi bersama berkas <code>manifest.json</code> yang memuat ringkasan SHA-256 untuk memastikan bukti transaksi tidak diubah pasca penutupan periode.
                            </p>
                        </div>

                        <x-ds.table
                            :headers="[
                                ['key' => 'entity', 'label' => 'Entitas'],
                                ['key' => 'period', 'label' => 'Periode', 'tabular' => true],
                                ['key' => 'file', 'label' => 'Berkas Berita Acara'],
                                ['key' => 'status', 'label' => 'Status Segel'],
                            ]"
                            :rows="[
                                [
                                    'entity' => 'CORP-HOLDING',
                                    'period' => date('Y-m', strtotime('-1 month')),
                                    'file' => 'audit_pack_CORP-HOLDING_' . date('Y-m', strtotime('-1 month')) . '.zip',
                                    'status' => 'SEALED (SHA-256)',
                                ]
                            ]"
                        />
                    </div>
                </x-ds.card>
            </div>
        </div>
    </div>
</x-ds.shell>
