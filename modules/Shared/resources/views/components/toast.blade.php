<div
    x-data="{
        toasts: [],
        add(toast) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, ...toast });
            setTimeout(() => this.remove(id), toast.duration || 4000);
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }
    }"
    x-init="
        @if(session('success'))
            add({ type: 'success', message: '{{ addslashes(session('success')) }}' });
        @endif
        @if(session('error'))
            add({ type: 'danger', message: '{{ addslashes(session('error')) }}' });
        @endif
        @if(session('warning'))
            add({ type: 'warning', message: '{{ addslashes(session('warning')) }}' });
        @endif
        @if(session('info'))
            add({ type: 'info', message: '{{ addslashes(session('info')) }}' });
        @endif
        window.addEventListener('toast', (e) => add(e.detail));
    "
    class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"
>
    <template x-for="t in toasts" :key="t.id">
        <div
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="pointer-events-auto p-4 rounded-xl shadow-2xl border flex items-start gap-3 backdrop-blur-xl"
            :class="{
                'bg-emerald-950/90 border-emerald-500/40 text-emerald-100': t.type === 'success',
                'bg-rose-950/90 border-rose-500/40 text-rose-100': t.type === 'danger' || t.type === 'error',
                'bg-amber-950/90 border-amber-500/40 text-amber-100': t.type === 'warning',
                'bg-blue-950/90 border-blue-500/40 text-blue-100': t.type === 'info' || !t.type,
            }"
        >
            <div class="flex-1 text-sm font-medium" x-text="t.message"></div>
            <button @click="remove(t.id)" class="text-xs opacity-60 hover:opacity-100 transition-opacity">✕</button>
        </div>
    </template>
</div>
