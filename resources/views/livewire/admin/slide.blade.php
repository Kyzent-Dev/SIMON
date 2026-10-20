{{-- admin.md "Slide carousel manager": card list by urutan with thumbnail, judul, Aktif switch, drag reorder (wire:sort = Alpine Sort, default ghost)
     plus Naikkan/Turunkan (reordering without drag), Ubah. Editor in x-ui.drawer lg with a live 16:9 preview of the real hero composition
     (landing.md "Hero carousel": image, mint-950 scrim, accent rule + overline, title, quote, owner) and a dashed safe-area overlay.
     After a move, focus returns to the moved card's same button (or its other one when that became disabled). No animation beyond the drawer. --}}
<div>
    <x-ui.page-header title="Slide Beranda" description="Gambar dan kutipan di bagian atas halaman Home. Slide aktif tampil sesuai urutan.">
        <x-slot:actions>
            <x-ui.button type="button" icon="plus" wire:click="create">Tambah slide</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($slides->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="photo" title="Belum ada slide. Beranda menampilkan latar bawaan.">
                <x-ui.button type="button" icon="plus" wire:click="create">Tambah slide</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div x-data="{
                geser(id, arah) {
                    $wire[arah === 'naik' ? 'naikkan' : 'turunkan'](id).then(() => {
                        const sama = document.getElementById(`slide-${id}-${arah}`);
                        const lain = document.getElementById(`slide-${id}-${arah === 'naik' ? 'turun' : 'naik'}`);
                        (sama && ! sama.disabled ? sama : lain)?.focus();
                    });
                },
            }">
            <p id="slide-petunjuk" class="mb-3 text-sm text-neutral-600"><span class="hidden sm:inline">Seret pegangan untuk mengubah urutan, atau pakai</span><span class="sm:hidden">Ubah urutan dengan</span> tombol Naikkan dan Turunkan.</p>
            <ol role="list" wire:sort="pindahkan" class="space-y-3" aria-describedby="slide-petunjuk">
                @foreach ($slides as $s)
                    <li wire:key="slide-{{ $s->id }}" wire:sort:item="{{ $s->id }}"
                        class="flex flex-wrap items-center gap-3 rounded-lg border border-neutral-200 bg-white p-3 shadow-xs sm:flex-nowrap lg:p-4">
                        <span wire:sort:handle aria-hidden="true" title="Seret untuk mengubah urutan"
                            class="hidden size-10 shrink-0 cursor-grab items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 sm:inline-flex">
                            {{ svg('heroicon-m-bars-3', 'size-5') }}
                        </span>
                        <img src="{{ $s->gambarUrl(960) }}" alt="" width="160" height="90" loading="lazy"
                            class="aspect-video w-28 shrink-0 rounded-md bg-neutral-100 object-cover sm:w-40">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-neutral-500 tabular-nums">Urutan {{ $loop->iteration }}</p>
                            <p id="slide-{{ $s->id }}-judul" class="truncate font-semibold text-neutral-900">{{ $s->judul }}</p>
                            <x-ui.switch :label="'Aktif'" name="aktif-{{ $s->id }}" :checked="$s->aktif" aria-label="Aktif: {{ $s->judul }}"
                                x-on:change="$wire.aturAktif({{ $s->id }}, $event.target.checked)" />
                        </div>
                        <div class="flex w-full shrink-0 items-center justify-end gap-2 sm:w-auto">
                            <button type="button" id="slide-{{ $s->id }}-naik" aria-label="Naikkan slide {{ $s->judul }}" title="Naikkan" @disabled($loop->first)
                                x-on:click="geser({{ $s->id }}, 'naik')"
                                class="inline-flex size-11 items-center justify-center rounded-md border border-border-strong bg-white text-mint-700 enabled:hover:bg-mint-50 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-50 disabled:text-neutral-400 lg:size-10">
                                {{ svg('heroicon-m-arrow-up', 'size-5') }}
                            </button>
                            <button type="button" id="slide-{{ $s->id }}-turun" aria-label="Turunkan slide {{ $s->judul }}" title="Turunkan" @disabled($loop->last)
                                x-on:click="geser({{ $s->id }}, 'turun')"
                                class="inline-flex size-11 items-center justify-center rounded-md border border-border-strong bg-white text-mint-700 enabled:hover:bg-mint-50 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-50 disabled:text-neutral-400 lg:size-10">
                                {{ svg('heroicon-m-arrow-down', 'size-5') }}
                            </button>
                            <x-ui.button type="button" variant="secondary" wire:click="edit({{ $s->id }})" aria-label="Ubah slide {{ $s->judul }}">Ubah</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    <x-ui.drawer name="slide-form" :title="$editingId ? 'Ubah slide' : 'Tambah slide'" size="lg" wire:model="open">
        <form novalidate id="slide-form" wire:submit="save" class="space-y-5">
            @include('auth.partials.error-summary', ['class' => '', 'fields' => [
                'gambar' => 'Gambar', 'judul' => 'Judul', 'kutipan' => 'Kutipan atau penjelasan', 'pemilik_nama' => 'Nama pemilik kutipan',
                'pemilik_jabatan' => 'Jabatan', 'alt' => 'Teks alternatif gambar',
            ]])

            <x-ui.file-upload name="gambar" label="Gambar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" wire:model="gambar"
                helper="JPG, PNG, atau WebP, maks. 5 MB, lebar minimal 960 piksel. Gambar dipotong 16:9." :error="$errors->first('gambar')"
                :filename="$gambar?->getClientOriginalName() ?? ($editing ? 'Gambar tersimpan' : null)"
                :size="$gambar ? number_format(max(1, round($gambar->getSize() / 1024)), 0, ',', '.').' KB' : null" />

            {{-- Live preview: the hero at 16:9, sized in container units so it matches the 1280px composition at any drawer width. Decorative. --}}
            <div>
                <p class="mb-2 text-sm font-medium text-neutral-900">Pratinjau</p>
                <div aria-hidden="true" class="@container relative aspect-video overflow-hidden rounded-xl bg-mint-950">
                    @if ($pratinjau)
                        <img src="{{ $pratinjau }}" alt="" class="absolute inset-0 size-full object-cover">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-r from-mint-950/90 via-mint-950/80 to-mint-950/40"></div>
                    <svg class="absolute inset-y-0 right-0 h-full w-1/3 text-mint-200 opacity-[.12]" viewBox="0 0 400 400" preserveAspectRatio="none" fill="none" focusable="false">
                        @foreach (range(1, 8) as $i)
                            @php $y = $i * 44; @endphp
                            <path d="M0 {{ $y }} C 50 {{ $y - 18 }}, 100 {{ $y + 18 }}, 150 {{ $y }} S 250 {{ $y - 18 }}, 300 {{ $y }} S 400 {{ $y + 18 }}, 450 {{ $y }}" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" />
                        @endforeach
                    </svg>
                    <div class="absolute inset-x-[5%] bottom-[9%] max-w-[50%]">
                        <div class="h-[.25cqw] min-h-[2px] w-[3.2cqw] bg-accent-400"></div>
                        <p class="mt-[1cqw] text-[1.1cqw] font-semibold tracking-[.08em] text-accent-400 uppercase">Inspektorat Daerah</p>
                        <p class="mt-[.8cqw] font-display text-[3.75cqw] leading-[1.17] font-extrabold break-words text-white" x-text="$wire.judul || 'Judul slide'">{{ $judul ?: 'Judul slide' }}</p>
                        <p class="mt-[1cqw] text-[1.4cqw] leading-normal break-words text-mint-200" x-show="$wire.kutipan" x-text="$wire.kutipan">{{ $kutipan }}</p>
                        <p class="mt-[.8cqw] text-[1cqw] font-medium text-mint-300" x-show="$wire.pemilik_nama"
                            x-text="'— ' + $wire.pemilik_nama + ($wire.pemilik_jabatan ? ', ' + $wire.pemilik_jabatan : '')"></p>
                    </div>
                    {{-- Safe area: where the hero text sits on the public page. --}}
                    <div class="absolute inset-x-[4%] bottom-[6%] h-[58%] w-[54%] rounded-md border-2 border-dashed border-accent-400/70"></div>
                </div>
                <p class="mt-1.5 text-xs text-neutral-500">Garis putus-putus menandai area teks. Bagian penting gambar sebaiknya di luar area itu.</p>
            </div>

            <div x-data>
                <x-ui.input label="Judul" name="judul" wire:model="judul" required maxlength="80" autocomplete="off" :error="$errors->first('judul')" />
                <p class="mt-1 text-right text-xs text-neutral-500 tabular-nums"><span x-text="($wire.judul || '').length">{{ mb_strlen($judul) }}</span>/80</p>
            </div>
            <div>
                <x-ui.textarea label="Kutipan atau penjelasan" name="kutipan" rows="3" maxlength="200" wire:model="kutipan" :error="$errors->first('kutipan')" />
                <p class="mt-1 text-right text-xs text-neutral-500 tabular-nums"><span x-text="($wire.kutipan || '').length">{{ mb_strlen($kutipan) }}</span>/200</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Nama pemilik kutipan" name="pemilik_nama" maxlength="100" autocomplete="off" wire:model="pemilik_nama" :error="$errors->first('pemilik_nama')" />
                <x-ui.input label="Jabatan" name="pemilik_jabatan" maxlength="100" autocomplete="off" wire:model="pemilik_jabatan" :error="$errors->first('pemilik_jabatan')" />
            </div>
            <div class="space-y-2">
                <x-ui.input label="Teks alternatif gambar" name="alt" maxlength="150" autocomplete="off" wire:model="alt" x-bind:disabled="$wire.dekoratif"
                    class="disabled:bg-neutral-50 disabled:text-neutral-400" helper="Deskripsi gambar untuk pembaca layar" :error="$errors->first('alt')" />
                <x-ui.checkbox label="Gambar dekoratif (tanpa teks alternatif)" name="dekoratif" wire:model="dekoratif" />
            </div>
            <x-ui.switch label="Aktif" name="aktif_form" wire:model="aktif" description="Slide nonaktif tetap tersimpan tetapi tidak tampil di Home." />
        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'slide-form' })">Batal</x-ui.button>
            <x-ui.button form="slide-form" wire:loading.attr="disabled" wire:target="save,gambar">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>
</div>
