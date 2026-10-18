@php $links = \App\Support\Navigation::links(\App\Enums\Role::Admin); @endphp
<x-layouts::app title="Dasbor Admin">
    <x-ui.page-header title="Dasbor Admin" />

    {{-- admin.md Dashboard: quick links from the sidebar definition. Health cards and "Aktivitas terbaru" are deferred (DECISIONS.md). --}}
    @if ($links)
        <section aria-labelledby="akses-cepat">
            <h2 id="akses-cepat" class="mb-3 text-lg font-semibold text-neutral-900">Akses cepat</h2>
            <ul role="list" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($links as $link)
                    <li>
                        <x-ui.card :href="route($link['route'])" wire:navigate class="flex items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-mint-100 text-mint-800">{{ svg('heroicon-o-'.$link['icon'], 'size-6') }}</span>
                            <span class="font-medium text-mint-700">{{ $link['label'] }}</span>
                        </x-ui.card>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts::app>
