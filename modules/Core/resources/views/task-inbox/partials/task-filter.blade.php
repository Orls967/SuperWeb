<div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-4 sm:p-5 shadow-sm">
    <form method="GET" action="{{ route('core.task-inbox.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Filter Status --}}
        <div>
            <label for="filter_status" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                Status Tugas
            </label>
            <select id="filter_status" name="status" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Semua Status</option>
                <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending (Menunggu)</option>
                <option value="in_progress" {{ ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' }}>Sedang Diproses</option>
                <option value="approved" {{ ($filters['status'] ?? '') === 'approved' ? 'selected' : '' }}>Disetujui</option>
                <option value="rejected" {{ ($filters['status'] ?? '') === 'rejected' ? 'selected' : '' }}>Ditolak</option>
                <option value="delegated" {{ ($filters['status'] ?? '') === 'delegated' ? 'selected' : '' }}>Didelegasikan</option>
                <option value="escalated" {{ ($filters['status'] ?? '') === 'escalated' ? 'selected' : '' }}>Dieskalasi</option>
            </select>
        </div>

        {{-- Filter Prioritas --}}
        <div>
            <label for="filter_priority" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                Prioritas
            </label>
            <select id="filter_priority" name="priority" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Semua Prioritas</option>
                <option value="urgent" {{ ($filters['priority'] ?? '') === 'urgent' ? 'selected' : '' }}>Urgent (&lt; 4 Jam)</option>
                <option value="high" {{ ($filters['priority'] ?? '') === 'high' ? 'selected' : '' }}>Tinggi (High)</option>
                <option value="normal" {{ ($filters['priority'] ?? '') === 'normal' ? 'selected' : '' }}>Normal</option>
                <option value="low" {{ ($filters['priority'] ?? '') === 'low' ? 'selected' : '' }}>Rendah (Low)</option>
            </select>
        </div>

        {{-- Filter Modul --}}
        <div>
            <label for="filter_module" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                Modul Asal
            </label>
            <select id="filter_module" name="module" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Semua Modul</option>
                @foreach($availableModules ?? [] as $mod)
                    <option value="{{ $mod }}" {{ ($filters['module'] ?? '') === $mod ? 'selected' : '' }}>{{ $mod }}</option>
                @endforeach
            </select>
        </div>

        {{-- Tombol Aksi Filter --}}
        <div class="flex items-end space-x-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2 px-4 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900 flex items-center justify-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span>Terapkan Filter</span>
            </button>
            <a href="{{ route('core.task-inbox.index') }}" class="px-3.5 py-2 border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-slate-500" title="Reset Filter">
                Reset
            </a>
        </div>
    </form>
</div>

