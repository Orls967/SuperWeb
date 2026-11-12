<x-ds.shell title="Kalender Kepatuhan Regulasi & Tenggat Waktu (Compliance Calendar)">
    <div class="space-y-6">
        <x-ds.breadcrumb :items="[
            ['label' => 'Beranda', 'url' => '/dashboard'],
            ['label' => 'Enterprise Finance', 'url' => '/enterprise-finance'],
            ['label' => 'Kalender Kepatuhan', 'url' => '#'],
        ]" />

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-ds.stat label="Total Kewajiban" value="{{ $scanSummary['total_scanned'] }} Tenggat" hint="Pajak, Kontrak, Lisensi" />
            <x-ds.stat label="Eskalasi Terkirim" value="{{ $scanSummary['escalations_sent'] }} Notifikasi" hint="Outbox terkirim" />
            <x-ds.stat label="Tenggat Mendekati (H-1/7)" value="{{ $scanSummary['tiers']['H-1'] + $scanSummary['tiers']['H-7'] }}" hint="Perlu perhatian" />
            <x-ds.stat label="Kewajiban Lewat Jatuh Tempo" value="{{ $scanSummary['tiers']['OVERDUE'] }}" hint="Risiko penalti denda" />
        </div>

        <x-ds.card title="Status Pemindaian Eskalasi Berjenjang Kepatuhan">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-center">
                <div class="p-3 border rounded bg-gray-50">
                    <div class="text-xs text-gray-500 font-semibold">Tier H-30</div>
                    <div class="text-xl font-bold text-gray-800">{{ $scanSummary['tiers']['H-30'] }}</div>
                </div>
                <div class="p-3 border rounded bg-gray-50">
                    <div class="text-xs text-gray-500 font-semibold">Tier H-14</div>
                    <div class="text-xl font-bold text-blue-600">{{ $scanSummary['tiers']['H-14'] }}</div>
                </div>
                <div class="p-3 border rounded bg-gray-50">
                    <div class="text-xs text-gray-500 font-semibold">Tier H-7</div>
                    <div class="text-xl font-bold text-yellow-600">{{ $scanSummary['tiers']['H-7'] }}</div>
                </div>
                <div class="p-3 border rounded bg-gray-50">
                    <div class="text-xs text-gray-500 font-semibold">Tier H-1</div>
                    <div class="text-xl font-bold text-orange-600">{{ $scanSummary['tiers']['H-1'] }}</div>
                </div>
                <div class="p-3 border rounded bg-gray-50">
                    <div class="text-xs text-gray-500 font-semibold">Overdue</div>
                    <div class="text-xl font-bold text-red-600">{{ $scanSummary['tiers']['OVERDUE'] }}</div>
                </div>
            </div>
        </x-ds.card>
    </div>
</x-ds.shell>

