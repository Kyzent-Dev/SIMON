{{-- admin.md Master Data "Wilayah Irban": year select, one card per irban with a multi combobox of eligible objek, plus a read-only summary like the old wilayah table (PRD §7.2). No animation (DESIGN §7.5). --}}
<div>
    <x-ui.page-header title="Wilayah Irban" description="Tentukan kecamatan, dinas/badan, BUMD, dan RSUD yang diperiksa setiap Irban per tahun. Satu objek hanya boleh dipegang satu Irban dalam satu tahun." />

    <div class="mb-4 sm:max-w-xs">
        <x-ui.select label="Tahun" name="tahun" wire:model.live="tahun" :options="$years" :value="$tahun" />
    </div>

    @if ($irbans->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="building-office-2" title="Belum ada data Irban." description="Tambahkan Irban terlebih dahulu.">
                <a href="{{ route('admin.irban') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 lg:h-10">Buka halaman Irban</a>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($irbans as $irban)
                <x-ui.card wire:key="wilayah-{{ $irban->id }}-{{ $tahun }}">
                    <h2 class="mb-3 font-display text-lg font-bold text-neutral-900">{{ $irban->nama }}</h2>
                    <x-ui.combobox :name="'wilayah-'.$irban->id" label="Objek yang diperiksa" multiple :options="$options[$irban->id]" wire:model="selected.{{ $irban->id }}" placeholder="Cari objek…" :error="$errors->first('selected.'.$irban->id)" />
                    @if ($taken[$irban->id])
                        <details class="mt-3 text-sm text-neutral-700">
                            <summary class="min-h-11 cursor-pointer py-2 lg:min-h-0">{{ count($taken[$irban->id]) }} objek tidak tersedia (sudah dipegang Irban lain)</summary>
                            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                                @foreach ($taken[$irban->id] as $label)
                                    <li>{{ $label }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    <div class="mt-4 flex justify-end">
                        <x-ui.button type="button" wire:click="save({{ $irban->id }})" wire:loading.attr="disabled" wire:target="save({{ $irban->id }})">Simpan</x-ui.button>
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <h2 class="mt-8 mb-3 font-display text-lg font-bold text-neutral-900">Ringkasan wilayah tahun {{ $tahun }}</h2>
        <x-ui.table label="Ringkasan wilayah per Irban">
            <x-slot:head>
                <x-ui.th>Irban</x-ui.th>
                <x-ui.th>Kecamatan</x-ui.th>
                <x-ui.th>Dinas/Badan</x-ui.th>
                <x-ui.th>BUMD/RSUD</x-ui.th>
            </x-slot:head>
            @foreach ($summary as $row)
                <tr wire:key="ringkas-{{ $row['irban'] }}" class="align-top">
                    <td class="min-w-24 !py-3 font-medium whitespace-nowrap">{{ $row['irban'] }}</td>
                    @foreach (['kecamatan', 'dinas', 'bumd'] as $col)
                        <td class="min-w-48 !py-3">
                            @forelse ($row[$col] as $name)
                                <div>{{ $name }}</div>
                            @empty
                                <span class="text-neutral-600">—</span>
                            @endforelse
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</div>
