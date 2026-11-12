<x-ds.shell title="Penutupan Periode Fiskal & Checklist Tutup Buku">
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Enterprise Finance', 'url' => '/enterprise-finance'],
            ['label' => 'Tutup Buku Fiskal', 'url' => '#'],
        ]" />

        @if(session('status'))
            <x-ds.toast type="success" :message="session('status')" />
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-ds.card title="Form Eksekusi Tutup Buku Fiskal">
                <form action="{{ route('enterprise_finance.close.execute') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="close_entity_code" class="block text-sm font-medium text-gray-700">Kode Entitas</label>
                        <input id="close_entity_code" type="text" name="entity_code" value="CORP-HOLDING" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm" required />
                    </div>

                    <div>
                        <label for="close_period" class="block text-sm font-medium text-gray-700">Periode Fiskal (YYYY-MM)</label>
                        <input id="close_period" type="text" name="period" value="{{ date('Y-m', strtotime('-1 month')) }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm font-mono" required />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="close_revenue_minor" class="block text-sm font-medium text-gray-700">Total Pendapatan (Minor IDR)</label>
                            <input id="close_revenue_minor" type="number" name="revenue_minor" value="5000000000" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm tabular-nums" required />
                        </div>
                        <div>
                            <label for="close_expense_minor" class="block text-sm font-medium text-gray-700">Total Beban (Minor IDR)</label>
                            <input id="close_expense_minor" type="number" name="expense_minor" value="3500000000" class="mt-1 block w-full rounded border-gray-300 shadow-sm text-sm tabular-nums" required />
                        </div>
                    </div>

                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800">
                        <strong>Perhatian:</strong> Penutupan buku akan memposting jurnal pemindahan pendapatan/beban ke <code>ef:income_summary</code> dan mengunci periode secara permanen di buku besar.
                    </div>

                    <x-ds.button type="submit" variant="primary">Eksekusi Tutup Buku & Kunci Periode</x-ds.button>
                </form>
            </x-ds.card>

            <x-ds.card title="Daftar Checklist Prasyarat Tutup Buku">
                <div class="space-y-3 text-sm">
                    <div class="p-3 border rounded flex items-center justify-between bg-green-50">
                        <div>
                            <div class="font-semibold text-green-900">1. Rekonsiliasi Subledger AR vs GL</div>
                            <div class="text-xs text-green-700">Diskrepansi: Rp 0 (Cocok)</div>
                        </div>
                        <x-ds.badge-status status="completed" />
                    </div>
                    <div class="p-3 border rounded flex items-center justify-between bg-green-50">
                        <div>
                            <div class="font-semibold text-green-900">2. Rekonsiliasi Subledger AP vs GL</div>
                            <div class="text-xs text-green-700">Diskrepansi: Rp 0 (Cocok)</div>
                        </div>
                        <x-ds.badge-status status="completed" />
                    </div>
                    <div class="p-3 border rounded flex items-center justify-between bg-green-50">
                        <div>
                            <div class="font-semibold text-green-900">3. Penyusutan Aset Tetap Bulanan</div>
                            <div class="text-xs text-green-700">Seluruh depresiasi diakui</div>
                        </div>
                        <x-ds.badge-status status="completed" />
                    </div>
                    <div class="p-3 border rounded flex items-center justify-between bg-green-50">
                        <div>
                            <div class="font-semibold text-green-900">4. Penutupan Akun Nominal</div>
                            <div class="text-xs text-green-700">Siap dipindahkan ke Ikhtisar Laba Rugi</div>
                        </div>
                        <x-ds.badge-status status="completed" />
                    </div>
                </div>
            </x-ds.card>
        </div>
    </div>
</x-ds.shell>
