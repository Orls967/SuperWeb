<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Gelang NFC & Dompet Cashless On-Premise') }}
            </h2>
            <div class="flex space-x-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    Sistem RFID Closed-Loop Aktif
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                <div class="text-xs uppercase font-bold text-gray-400">Total Gelang Dipasangkan</div>
                <div class="text-2xl font-black text-gray-800 mt-2">12,480 <span class="text-xs font-normal text-gray-500">unit</span></div>
                <div class="text-xs text-emerald-600 mt-1">99.2% aktif tanpa malfungsi</div>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                <div class="text-xs uppercase font-bold text-gray-400">Total Saldo Terkunci di Escrow</div>
                <div class="text-2xl font-black text-indigo-700 mt-2">Rp 1.450.000.000</div>
                <div class="text-xs text-gray-500 mt-1">Akun ven:vip_escrow:IDR</div>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                <div class="text-xs uppercase font-bold text-gray-400">Kecepatan Tap Bar POS</div>
                <div class="text-2xl font-black text-amber-600 mt-2">1.8 <span class="text-xs font-normal text-gray-500">detik/transaksi</span></div>
                <div class="text-xs text-amber-700 mt-1">NFC Reader Solenoid Mode</div>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                <div class="text-xs uppercase font-bold text-gray-400">Kepatuhan PDP Kriptografi</div>
                <div class="text-2xl font-black text-emerald-600 mt-2">100%</div>
                <div class="text-xs text-gray-500 mt-1">HMAC Salt Hash Chain</div>
            </div>
        </div>

        <!-- Panel Registrasi & Pemindai Gelang -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-gray-800">Manajemen Registrasi & Penggantian Gelang Hilang</h3>
                    <p class="text-xs text-gray-500">Pasangkan chip RFID fisik dengan tiket resmi gapless atau nonaktifkan gelang yang hilang.</p>
                </div>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Form Pairing -->
                <div class="bg-slate-50 p-5 rounded-lg border border-slate-200">
                    <h4 class="font-bold text-sm text-slate-800 mb-3">Pairing Gelang Baru di Gerbang</h4>
                    <form class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Nomor Tiket Gapless</label>
                            <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="VEN-TKT-2026-000123" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">UID Chip Gelang RFID (Tap pada Reader)</label>
                            <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" placeholder="04:A2:3B:5C:8D:1E:80" />
                        </div>
                        <button type="button" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Validasi Hash & Pasangkan Gelang
                        </button>
                    </form>
                </div>

                <!-- Form Penggantian -->
                <div class="bg-slate-50 p-5 rounded-lg border border-slate-200">
                    <h4 class="font-bold text-sm text-slate-800 mb-3">Prosedur Gelang Hilang / Rusak</h4>
                    <form class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">UID Gelang Lama yang Hilang</label>
                            <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" placeholder="04:99:FF:..." />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">UID Gelang Pengganti Baru</label>
                            <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" placeholder="04:BB:77:..." />
                        </div>
                        <button type="button" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-rose-600 hover:bg-rose-700">
                            Blacklist Gelang Lama & Terbitkan Ulang
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
