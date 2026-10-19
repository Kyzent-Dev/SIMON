{{-- Register Nomor SPT (design/dashboard/bagian-umum.md "Register", _shared.md §3 tables + §6 filter bar/export): compact table, sticky header, search by number,
     filters status/irban/bulan, 100 per page, export split button. Cancelled rows keep the Dibatalkan badge, text never struck through. No animation (DESIGN §7.5). --}}
@php
    $filterAktif = count(array_filter([$this->status, $this->irban, $this->bulan, $this->cari]));
    $total = $baris->total();
    $atur = fn () => '$set(\'cari\', \'\'); $set(\'status\', \'\'); $set(\'irban\', \'\'); $set(\'bulan\', \'\')';
@endphp
<div>
    <x-ui.page-header title="Register Nomor SPT" description="Stambook digital nomor SPT tahun {{ $this->tahun }}. Nomor yang dibatalkan tetap tercatat dan tidak dipakai ulang.">
        <x-slot:actions>
            <x-app.export-buttons />
            <x-ui.button type="button" variant="secondary" icon="hashtag" wire:click="bukaAwal" :disabled="$terkunciHalaman" aria-describedby="{{ $terkunciHalaman ? 'awal-terkunci' : '' }}">Atur nomor awal</x-ui.button>
            <x-ui.button type="button" icon="plus" wire:click="bukaManual">Catat Nomor Manual</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($terkunciHalaman)
        <p id="awal-terkunci" class="-mt-3 mb-4 text-sm text-neutral-600">Nomor awal tidak dapat diubah karena sudah ada nomor yang terbit pada register {{ $this->tahun }}.</p>
    @endif

    <div x-data="{ filter: false }" class="mb-4">
        <button type="button" x-on:click="filter = !filter" x-bind:aria-expanded="filter.toString()" aria-controls="filter-register"
            class="inline-flex h-11 items-center gap-2 rounded-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 md:hidden">
            {{ svg('heroicon-m-funnel', 'size-5') }}Filter{{ $filterAktif ? " ({$filterAktif})" : '' }}
        </button>
        <div id="filter-register" class="grid gap-3 max-md:mt-3 max-md:hidden sm:grid-cols-2 md:flex md:flex-wrap md:items-end" x-bind:class="filter && 'max-md:!grid'">
            <div class="md:w-28"><x-ui.select label="Tahun" name="tahun" wire:model.live="tahun" :options="$tahunList" :value="$this->tahun" /></div>
            <div class="md:w-64">
                <x-ui.input type="search" label="Cari nomor" name="cari" wire:model.live.debounce.300ms="cari" :value="$this->cari" placeholder="Cari…" autocomplete="off" />
            </div>
            <div class="md:w-44"><x-ui.select label="Status" name="status" wire:model.live="status" :options="$statusOptions" placeholder="Semua status" :value="$this->status" /></div>
            <div class="md:w-44"><x-ui.select label="Irban" name="irban" wire:model.live="irban" :options="$irbanOptions" placeholder="Semua irban" :value="$this->irban" /></div>
            <div class="md:w-40"><x-ui.select label="Bulan" name="bulan" wire:model.live="bulan" :options="$bulanOptions" placeholder="Semua bulan" :value="$this->bulan" /></div>
            @if ($filterAktif)
                <x-ui.button type="button" variant="ghost" wire:click="{{ $atur() }}">Atur ulang</x-ui.button>
            @endif
        </div>
    </div>

    <div wire:loading.delay.class="opacity-60" wire:target="tahun,cari,status,irban,bulan,gotoPage,previousPage,nextPage,perPage" wire:loading.delay.attr="aria-busy">
        @if ($total === 0)
            <x-ui.card>
                <x-ui.empty-state icon="book-open" :title="$filterAktif ? 'Tidak ada nomor yang cocok.' : 'Belum ada nomor terbit pada '.$this->tahun.'.'"
                    :description="$filterAktif ? 'Coba atur ulang filter.' : 'Nomor yang diterbitkan dari Kotak Masuk atau dicatat manual akan tampil di sini.'">
                    @if ($filterAktif)
                        <x-ui.button type="button" variant="secondary" wire:click="{{ $atur() }}">Atur ulang filter</x-ui.button>
                    @endif
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <x-ui.table label="Register Nomor SPT {{ $this->tahun }}" density="compact">
                <x-slot:head>
                    <x-ui.th numeric>No. Urut</x-ui.th>
                    <x-ui.th>Nomor SPT</x-ui.th>
                    <x-ui.th>Tanggal</x-ui.th>
                    <x-ui.th>Irban</x-ui.th>
                    <x-ui.th>Perihal/Jenis</x-ui.th>
                    <x-ui.th>Status</x-ui.th>
                    <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
                    <x-ui.th>Keterangan</x-ui.th>
                </x-slot:head>
                @foreach ($baris as $s)
                    @php $b = $register->baris($s); @endphp
                    <tr wire:key="reg-{{ $s->id }}">
                        <td class="text-right font-medium">{{ $b[0] }}</td>
                        <td class="whitespace-nowrap">
                            <span class="inline-flex items-center gap-1">
                                <a href="{{ route('spt.show', $s) }}" wire:navigate class="font-medium text-mint-700 underline-offset-2 hover:underline">{{ $b[1] }}</a>
                                <button type="button" x-data x-on:click="navigator.clipboard?.writeText(@js($b[1])).then(() => $dispatch('toast', { type: 'success', message: 'Nomor disalin' }))"
                                    aria-label="Salin nomor {{ $b[1] }}" class="inline-flex size-11 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 lg:size-8">
                                    {{ svg('heroicon-m-clipboard', 'size-4') }}
                                </button>
                            </span>
                        </td>
                        <td class="whitespace-nowrap">{{ $b[2] }}</td>
                        <td class="whitespace-nowrap">{{ $b[3] }}</td>
                        <td class="min-w-36 max-w-44"><span class="line-clamp-1" title="{{ $s->uraian_untuk }}">{{ $b[4] }}</span></td>
                        <td><x-app.status-badge :status="$s->status()" /></td>
                        <td class="relative">
                            @if ($s->status_pengajuan === \App\Enums\StatusPengajuan::Diterima)
                                <button type="button" wire:click="bukaBatal({{ $s->id }})" aria-label="Batalkan Nomor {{ $s->nomor_lengkap }}" title="Batalkan Nomor"
                                    class="inline-flex size-11 items-center justify-center rounded-md text-danger-700 hover:bg-neutral-50 lg:size-8">{{ svg('heroicon-m-no-symbol', 'size-5') }}</button>
                            @endif
                        </td>
                        <td class="min-w-32 max-w-56"><span class="line-clamp-2" title="{{ $b[6] }}">{{ $b[6] ?: '—' }}</span></td>
                    </tr>
                @endforeach
            </x-ui.table>
            <x-ui.pagination :paginator="$baris" perPageModel="perPage" :perPageOptions="[100]" class="mt-4" />
        @endif
    </div>

    {{-- Catat Nomor Manual --}}
    <x-ui.drawer name="manual" title="Catat Nomor Manual" size="md" wire:model="showManual">
        <x-ui.alert variant="warning" class="mb-4">Gunakan hanya untuk SPT mendesak.</x-ui.alert>
        <div class="space-y-4">
            <x-ui.select label="Irban" name="manual_irban" id="manual-irban" wire:model.live="manual.irban_id" :options="$irbanOptions" placeholder="Pilih Irban" required
                :value="$manual['irban_id'] ?? ''" :error="$errors->first('manual.irban_id')" />
            <x-ui.select label="Klasifikasi" name="manual_klasifikasi" id="manual-klasifikasi" wire:model.live="manual.klasifikasi_id" :options="$klasifikasiOptions" placeholder="Pilih klasifikasi" required
                :value="$manual['klasifikasi_id'] ?? ''" :error="$errors->first('manual.klasifikasi_id')" />
            <x-ui.select label="Jenis penugasan" name="manual_jenis" id="manual-jenis" wire:model="manual.jenis_penugasan_id" :options="$jenisOptions" placeholder="Tanpa jenis penugasan"
                :value="$manual['jenis_penugasan_id'] ?? ''" :error="$errors->first('manual.jenis_penugasan_id')" />
            <x-ui.textarea label="Perihal" name="manual_perihal" id="manual-perihal" wire:model="manual.uraian_untuk" rows="3" maxlength="1000" required
                helper="Isi seperti pada kalimat setelah kata Untuk di SPT." :error="$errors->first('manual.uraian_untuk')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input type="date" label="Tanggal mulai" name="manual_mulai" id="manual-mulai" wire:model="manual.tanggal_mulai" required :error="$errors->first('manual.tanggal_mulai')" />
                <x-ui.input type="date" label="Tanggal selesai" name="manual_selesai" id="manual-selesai" wire:model="manual.tanggal_selesai" required :error="$errors->first('manual.tanggal_selesai')" />
            </div>
            <x-ui.textarea label="Alasan" name="manual_alasan" id="manual-alasan" wire:model="manual.alasan" rows="3" maxlength="1000" required
                helper="Minimal 10 karakter. Dicatat pada riwayat SPT." :error="$errors->first('manual.alasan')" />
            <div>
                <p class="text-sm font-medium text-neutral-900">Nomor yang akan terbit</p>
                <p class="mt-1.5 rounded-md border border-mint-200 bg-mint-50 px-4 py-3 text-center font-sans text-xl font-bold [overflow-wrap:anywhere] text-neutral-900 tabular-nums" aria-live="polite">
                    {{ $pratinjau ?: 'Pilih Irban untuk melihat nomor' }}
                </p>
                <p class="mt-1.5 text-xs text-neutral-500">Nomor diambil dari urutan yang sama dengan pengajuan Irban dan baru pasti saat Anda menekan "Terbitkan Nomor".</p>
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button type="button" variant="ghost" x-on:click="$wire.showManual = false">Batal</x-ui.button>
            <x-ui.button type="button" icon="check" wire:click="catatManual" wire:loading.attr="disabled" wire:target="catatManual">Terbitkan Nomor</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    {{-- Batalkan Nomor --}}
    <x-ui.modal name="batal" title="Batalkan Nomor" wire:model="showBatal">
        @if ($batal)
            <p class="text-neutral-900">Nomor <span class="font-semibold tabular-nums [overflow-wrap:anywhere]">{{ $batal->nomor_lengkap }}</span> akan ditandai Dibatalkan. Nomor tetap tercatat di register dan tidak akan dipakai ulang.</p>
        @endif
        @error('spt')
            <x-ui.alert variant="danger" class="mt-3">{{ $message }}</x-ui.alert>
        @enderror
        <div class="mt-4">
            <x-ui.textarea label="Alasan pembatalan" name="alasan" id="alasan-batal" wire:model="alasan" rows="4" maxlength="1000" required autofocus
                helper="Minimal 10 karakter." :error="$errors->first('alasan')" />
            <p class="mt-1 text-right text-xs text-neutral-500 tabular-nums" aria-live="polite" x-text="($wire.alasan ?? '').length + '/1000 karakter'">{{ mb_strlen($alasan) }}/1000 karakter</p>
        </div>
        <x-slot:footer>
            <x-ui.button type="button" variant="ghost" x-on:click="$wire.showBatal = false">Kembali</x-ui.button>
            <x-ui.button type="button" variant="danger" icon="no-symbol" wire:click="batalkan" wire:loading.attr="disabled" wire:target="batalkan">Batalkan Nomor</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Atur nomor awal --}}
    <x-ui.modal name="awal" title="Atur nomor awal" size="sm" wire:model="showAwal">
        <p class="mb-4 text-neutral-600">Isi dengan nomor terakhir dari stambook fisik. Nomor berikutnya yang terbit adalah angka ini ditambah 1. Hanya untuk register {{ $this->tahun }}.</p>
        <div class="space-y-4">
            @if ($perIrban)
                <x-ui.select label="Irban" name="awal_irban" id="awal-irban" wire:model.live="awalIrban" :options="$irbanOptions" placeholder="Pilih Irban" required
                    :value="$this->awalIrban" :error="$errors->first('awalIrban')" />
            @endif
            @if ($awalTerkunci)
                <x-ui.alert variant="info">Nomor awal tidak dapat diubah karena sudah ada nomor yang terbit pada register ini.</x-ui.alert>
            @endif
            <x-ui.input type="number" min="0" inputmode="numeric" label="Nomor terakhir dari stambook fisik" name="awal_nomor" id="awal-nomor" wire:model="awalNomor" required
                :disabled="$awalTerkunci" :error="$errors->first('awalNomor')" />
        </div>
        <x-slot:footer>
            <x-ui.button type="button" variant="ghost" x-on:click="$wire.showAwal = false">Batal</x-ui.button>
            <x-ui.button type="button" icon="check" wire:click="aturAwal" wire:loading.attr="disabled" wire:target="aturAwal" :disabled="$awalTerkunci">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
