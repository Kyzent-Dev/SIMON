{{-- DESIGN §8 x-ui.toast stack (rendered once by the layout). Trigger: Livewire `$this->dispatch('toast', type: 'success', message: '…')`,
     Alpine `$dispatch('toast', {type, message})`, or `session()->flash('toast', [...])` before a redirect.
     success/info auto-dismiss after 5s; error/warning persist until closed. Max 3 visible. Top-right ≥1024, bottom-center below. --}}
@php
    $icons = ['success' => 'check-circle', 'error' => 'x-circle', 'info' => 'information-circle', 'warning' => 'exclamation-triangle'];
@endphp
<div x-data="{
        toasts: [], next: 0,
        add(t) {
            const id = this.next++;
            this.toasts = [...this.toasts, { id, type: t.type ?? 'info', message: t.message }].slice(-3);
            if (['success', 'info'].includes(t.type ?? 'info')) setTimeout(() => this.remove(id), 5000);
        },
        remove(id) { this.toasts = this.toasts.filter((t) => t.id !== id) },
    }"
    x-init="@if (session('toast')) add(@js(session('toast'))) @endif"
    x-on:toast.window="add($event.detail)"
    class="pointer-events-none fixed inset-x-4 bottom-[max(1rem,env(safe-area-inset-bottom))] z-[80] flex flex-col items-center gap-2 lg:inset-x-auto lg:top-4 lg:right-4 lg:bottom-auto lg:items-end">
    <template x-for="t in toasts" :key="t.id">
        <div x-bind:role="t.type === 'error' ? 'alert' : 'status'"
            x-transition:enter="transition duration-[var(--dur-base)] ease-[var(--ease-enter)]"
            x-transition:enter-start="opacity-0 translate-y-4 lg:translate-y-0 lg:translate-x-4"
            x-transition:leave="transition duration-[var(--dur-fast)] ease-[var(--ease-exit)]"
            x-transition:leave-end="opacity-0"
            x-bind:class="{ 'border-l-success-700': t.type === 'success', 'border-l-danger-700': t.type === 'error', 'border-l-info-800': t.type === 'info', 'border-l-accent-700': t.type === 'warning' }"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border border-l-4 border-neutral-200 bg-white p-3 text-sm text-neutral-900 shadow-md">
            @foreach ($icons as $type => $icon)
                <template x-if="t.type === '{{ $type }}'">{{ svg('heroicon-m-'.$icon, 'mt-px size-5 shrink-0 '.['success' => 'text-success-700', 'error' => 'text-danger-700', 'info' => 'text-info-800', 'warning' => 'text-accent-700'][$type]) }}</template>
            @endforeach
            <p class="min-w-0 flex-1" x-text="t.message"></p>
            <button type="button" x-on:click="remove(t.id)" aria-label="Tutup notifikasi"
                class="-m-2 inline-flex size-11 shrink-0 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 lg:-m-1 lg:size-8">
                {{ svg('heroicon-m-x-mark', 'size-5') }}
            </button>
        </div>
    </template>
</div>
