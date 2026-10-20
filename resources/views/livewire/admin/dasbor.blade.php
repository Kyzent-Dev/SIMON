{{-- Dasbor Admin (design/dashboard/admin.md "Dashboard", _shared.md §1, DESIGN §8). Health cards + quick links (from App\Support\Navigation, so only
     existing pages show). The PKPT status uses the rectangular x-app.tag (admin.md PKPT: pills stay reserved for SPT status). No motion. --}}
<div>
    <x-ui.page-header title="Dasbor Admin" description="Kesehatan data dan akses cepat ke halaman pengelolaan." />

    <section aria-label="Kesehatan data">
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li class="flex"><x-ui.stat-card class="w-full" label="Pengguna aktif" :value="$pengguna" /></li>
            <li class="flex"><x-ui.stat-card class="w-full" label="Pegawai" :value="$pegawai" :href="route('admin.pegawai')" /></li>
            <li class="flex">
                <a href="{{ route('admin.pkpt') }}" wire:navigate class="flex w-full min-w-0 flex-col rounded-lg border border-neutral-200 bg-white p-4 shadow-xs hover:bg-mint-50 lg:p-5">
                    <span class="text-xs font-medium text-neutral-600">PKPT {{ $tahun }}</span>
                    <span class="mt-auto pt-3">
                        @if ($pkpt)
                            <x-app.tag :icon="$pkpt->status->tag()['icon']" :variant="$pkpt->status->tag()['variant']">{{ $pkpt->status->label() }}</x-app.tag>
                        @else
                            <x-app.tag icon="minus-circle" variant="neutral">Belum dibuat</x-app.tag>
                        @endif
                    </span>
                </a>
            </li>
        </ul>
    </section>

    @if ($links)
        <section aria-labelledby="akses-cepat" class="mt-6">
            <h2 id="akses-cepat" class="mb-3 font-display text-[22px] leading-[30px] font-bold text-neutral-900">Akses cepat</h2>
            <ul role="list" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($links as $link)
                    <li>
                        <x-ui.card :href="route($link['route'])" wire:navigate class="flex items-center gap-3">
                            <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-md bg-mint-100 text-mint-800">{{ svg('heroicon-o-'.$link['icon'], 'size-6') }}</span>
                            <span class="font-medium text-mint-700">{{ $link['label'] }}</span>
                        </x-ui.card>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
