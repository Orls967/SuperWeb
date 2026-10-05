<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Kerja Sama Internasional: JV, Lisensi HKI, OEM/ODM & Alih Teknologi') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Foreign Entities & JVs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Mitra Entitas Asing</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($entities as $e)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $e->legal_name }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-blue-100 text-blue-800">{{ $e->jurisdiction_country }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Reg: {{ $e->registration_number }} | Valas: {{ $e->functional_currency }} | Arbitrase: {{ $e->arbitration_jurisdiction }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada entitas mitra asing terdaftar.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Joint Ventures (JV)</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($jvs as $jv)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $jv->name }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-green-100 text-green-800">{{ $jv->jv_type }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Porsi Saham: Lokal {{ $jv->local_share_percent }}% / Asing {{ $jv->foreign_share_percent }}%</div>
                                <div class="font-mono mt-1 text-xs">Disetor: IDR {{ number_format($jv->paid_in_capital_idr) }} / Komitmen: IDR {{ number_format($jv->total_committed_capital_idr) }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada proyek Joint Venture aktif.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Licenses & OEM Contracts -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Lisensi Teknologi & Royalti</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($licenses as $lic)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $lic->title }}</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-purple-100 text-purple-800">{{ $lic->license_type }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Tarif Royalti: {{ $lic->royalty_rate_percent }}% | MAG: IDR {{ number_format($lic->minimum_annual_guarantee_idr) }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada lisensi HKI aktif.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-6 bg-white shadow sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Kontrak OEM / ODM</h3>
                    <div class="space-y-3 text-sm">
                        @forelse($oems as $oem)
                            <div class="border rounded p-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-gray-800">{{ $oem->contract_number }} ({{ $oem->type }})</span>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-yellow-100 text-yellow-800">{{ $oem->status }}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Desain: {{ $oem->product_design_name }} | Standar: {{ $oem->qa_standard }}</div>
                                <div class="font-mono mt-1 text-xs">Tolling Fee: IDR {{ number_format($oem->tolling_fee_per_unit_idr) }}/unit</div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Belum ada kontrak manufaktur OEM/ODM.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
