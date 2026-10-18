{{-- admin.md "Komposisi Irban Tahunan": year select + one card per irban (PPJ single combobox, anggota multi combobox, Simpan). Each pegawai belongs to at most one irban per year. --}}
<div>
    <x-ui.page-header title="Komposisi Irban Tahunan" description="Tentukan Penanggung Jawab (PPJ) dan anggota setiap Irban untuk satu tahun. Seorang pegawai hanya dapat menjadi anggota satu Irban per tahun." />

    <div class="mb-4 max-w-48">
        <x-ui.select label="Tahun" name="tahun" wire:model.live="tahun" :options="$years" />
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
                <x-ui.card wire:key="irban-{{ $irban->id }}-{{ $tahun }}">
                    <form novalidate wire:submit="save({{ $irban->id }})" class="space-y-4">
                        <h2 class="font-display text-lg font-bold text-neutral-900">{{ $irban->nama }}</h2>
                        <x-ui.combobox :name="'ppj-'.$irban->id" label="Penanggung Jawab (PPJ)" :options="$options" wire:model="ppj.{{ $irban->id }}"
                            helper="Opsional." :error="$errors->first('ppj.'.$irban->id)" />
                        <x-ui.combobox :name="'anggota-'.$irban->id" label="Anggota" :options="$options" multiple wire:model="anggota.{{ $irban->id }}"
                            :error="$errors->first('anggota.'.$irban->id)" />
                        <div class="flex justify-end">
                            <x-ui.button wire:loading.attr="disabled" wire:target="save({{ $irban->id }})">Simpan</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>
