{{-- Panel "Wilayah Pengawasan" above the calendar ($w = WilayahPengawasan::untuk(tahun, irban)[0]): three lists, collapsible
     (button[aria-expanded]); open from 768, closed below. Opens/closes instantly (DESIGN §7.5). --}}
<section aria-labelledby="judul-wilayah" class="mb-6 rounded-lg border border-neutral-200 bg-white shadow-xs"
    x-data="{ buka: matchMedia('(min-width: 768px)').matches }">
    <h2 id="judul-wilayah">
        <button type="button" x-on:click="buka = ! buka" aria-expanded="false" x-bind:aria-expanded="buka.toString()" aria-controls="panel-wilayah"
            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg px-4 py-2 text-left lg:px-5">
            <span class="font-display text-lg leading-[26px] font-bold text-neutral-900">Wilayah Pengawasan</span>
            <span x-bind:class="buka && 'rotate-180'" class="shrink-0 text-neutral-600">{{ svg('heroicon-m-chevron-down', 'size-5', ['aria-hidden' => 'true']) }}</span>
        </button>
    </h2>
    <div id="panel-wilayah" x-show="buka" x-cloak class="border-t border-neutral-200 px-4 py-4 lg:px-5">
        <div class="grid gap-6 md:grid-cols-3">
            @foreach (['Kecamatan' => $w['kecamatan'] ?? [], 'Dinas/Badan' => $w['dinas_badan'] ?? [], 'Perusda/BUMD' => $w['bumd'] ?? []] as $judul => $nama)
                <div>
                    <h3 class="text-base font-semibold text-neutral-900">{{ $judul }} <span class="text-sm font-normal text-neutral-500 tabular-nums">({{ count($nama) }})</span></h3>
                    @if ($nama === [])
                        <p class="mt-2 text-sm text-neutral-600">Belum ada wilayah.</p>
                    @else
                        <ul role="list" class="mt-2 space-y-1 text-sm text-neutral-900">
                            @foreach ($nama as $n)
                                <li>{{ $n }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="mt-4 text-sm text-neutral-600">RSUD, Puskesmas, Desa, BUMDes, dan Sekolah mengikuti wilayah kecamatan.</p>
    </div>
</section>
