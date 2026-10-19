{{-- Irban "Jadwal PKPT" (irban.md "Ajukan Revisi Jadwal", _shared.md tables/modals, login.md §5 Diinput oleh).
     Only this irban's items of a Disahkan PKPT. Table ≥1024, cards below (DESIGN §11). Revision state = rectangular x-app.tag (never a pill).
     No animation on the table, cards or the form (DESIGN §7.5); only the modal's own transition. --}}
@use('App\Enums\StatusRevisi')
@use('App\Support\Tanggal')
@php
    $tahun = (int) $this->tahun;
    $disahkan = $pkpt?->status === \App\Enums\StatusPkpt::Disahkan;
@endphp
<div>
    <x-ui.page-header title="Jadwal PKPT" :description="'Item PKPT '.$irbanNama.' yang sudah disahkan. Ajukan revisi bila jadwal perlu digeser; revisi berlaku setelah disetujui pimpinan.'">
        @if ($disahkan)
            <x-slot:meta>
                @php $tag = $pkpt->status->tag(); @endphp
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-neutral-600">
                    <x-app.tag :icon="$tag['icon']" :variant="$tag['variant']">{{ $pkpt->status->label() }}</x-app.tag>
                    @if ($pkpt->nomor_sk)
                        <span>SK Bupati <span class="tabular-nums text-neutral-900">{{ $pkpt->nomor_sk }}</span>@if ($pkpt->tanggal_sk), {{ $pkpt->tanggal_sk->translatedFormat('d F Y') }}@endif</span>
                    @endif
                </div>
            </x-slot:meta>
        @endif
        <x-slot:actions>
            <div class="w-full sm:w-40">
                <x-ui.select label="Tahun" name="tahun" wire:model.live="tahun" :options="$tahunList" :value="$this->tahun" />
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div wire:loading.class="opacity-60" wire:target="tahun">
        @if (! $pkpt)
            <x-ui.card>
                <x-ui.empty-state icon="calendar-days" :title="'Belum ada PKPT tahun '.$tahun.'.'" description="PKPT disusun dan disahkan oleh Admin. Pilih tahun lain atau hubungi Admin." />
            </x-ui.card>
        @elseif (! $disahkan)
            <x-ui.card>
                <x-ui.empty-state icon="pencil-square" :title="'PKPT tahun '.$tahun.' belum disahkan.'" description="Jadwal dapat dilihat dan direvisi setelah Admin mengesahkan PKPT." />
            </x-ui.card>
        @elseif ($items->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="calendar-days" :title="'Tidak ada item PKPT untuk '.$irbanNama.' tahun '.$tahun.'.'" description="Hubungi Admin bila item untuk irban ini seharusnya ada." />
            </x-ui.card>
        @else
            {{-- ≥1024: table --}}
            <x-ui.table :label="'Item PKPT '.$irbanNama.' '.$tahun" class="max-lg:hidden">
                <x-slot:head>
                    <x-ui.th>Jenis Penugasan</x-ui.th>
                    <x-ui.th>Jadwal berlaku</x-ui.th>
                    <x-ui.th>Keterangan</x-ui.th>
                    <x-ui.th>Revisi terakhir</x-ui.th>
                    <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
                </x-slot:head>
                @foreach ($items as $item)
                    @php [$mulai, $selesai] = $rentang[$item->id]; @endphp
                    <tr wire:key="t-{{ $item->id }}">
                        <td class="min-w-40 align-top !py-3"><x-app.type-chip :color="$item->jenisPenugasan->warna" :label="$item->jenisPenugasan->nama" /></td>
                        <td class="min-w-56 align-top !py-3">
                            <x-app.jadwal-berlaku :item="$item" :mulai="$mulai" :selesai="$selesai" />
                            <x-app.riwayat-revisi :revisi="$item->revisi" class="mt-2" />
                        </td>
                        <td class="min-w-28 align-top !py-3">@if ($item->keterangan){{ $item->keterangan }}@else<span class="text-neutral-500">—</span>@endif</td>
                        <td class="whitespace-nowrap align-top !py-3">@include('livewire.irban.partials.revisi-tag')</td>
                        <td class="align-top !py-2 text-right">@include('livewire.irban.partials.revisi-aksi', ['v' => 't'])</td>
                    </tr>
                @endforeach
            </x-ui.table>

            {{-- <1024: cards (at 768 the table would hide the action column inside its scroller) --}}
            <ul class="space-y-3 lg:hidden" aria-label="Item PKPT {{ $irbanNama }} {{ $tahun }}">
                @foreach ($items as $item)
                    @php [$mulai, $selesai] = $rentang[$item->id]; @endphp
                    <li wire:key="c-{{ $item->id }}" class="rounded-lg border border-neutral-200 bg-white p-4 text-sm text-neutral-900 shadow-xs">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <x-app.type-chip :color="$item->jenisPenugasan->warna" :label="$item->jenisPenugasan->nama" />
                            @if ($item->revisi->isNotEmpty())@include('livewire.irban.partials.revisi-tag')@endif
                        </div>
                        <div class="mt-3 tabular-nums"><x-app.jadwal-berlaku :item="$item" :mulai="$mulai" :selesai="$selesai" /></div>
                        @if ($item->keterangan)
                            <p class="mt-2 text-neutral-600">{{ $item->keterangan }}</p>
                        @endif
                        <div class="mt-3 sm:[&_button]:w-auto [&_button]:w-full">@include('livewire.irban.partials.revisi-aksi', ['v' => 'c'])</div>
                        <x-app.riwayat-revisi :revisi="$item->revisi" class="mt-2" />
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-ui.modal name="revisi-jadwal" title="Ajukan Revisi Jadwal" size="md" wire:model="open">
        @if ($aktif)
            @php [$mulai, $selesai] = $rentang[$aktif->id]; @endphp
            <form id="form-revisi" wire:submit="kirim" novalidate class="space-y-5">
                @include('auth.partials.error-summary', ['class' => '', 'fields' => [
                    'tanggal_mulai' => 'Tanggal mulai', 'tanggal_selesai' => 'Tanggal selesai', 'alasan' => 'Alasan revisi', 'diinput_oleh' => 'Diinput oleh',
                ]])
                <dl class="grid grid-cols-1 gap-x-4 gap-y-1 rounded-md border border-neutral-200 bg-neutral-25 p-3 sm:grid-cols-[auto_1fr]">
                    <dt class="text-neutral-600">Jenis penugasan</dt>
                    <dd class="max-sm:mb-2"><x-app.type-chip :color="$aktif->jenisPenugasan->warna" :label="$aktif->jenisPenugasan->nama" /></dd>
                    <dt class="text-neutral-600">Jadwal berlaku</dt>
                    <dd class="tabular-nums max-sm:mb-2">{{ Tanggal::rentang($mulai, $selesai) }} · {{ (int) $mulai->diffInDays($selesai) + 1 }} hari</dd>
                    @if ($aktif->keterangan)
                        <dt class="text-neutral-600">Keterangan</dt>
                        <dd>{{ $aktif->keterangan }}</dd>
                    @endif
                </dl>

                <x-ui.date-range legend="Jadwal baru" required :min="$pkpt->tahun.'-01-01'" :max="$pkpt->tahun.'-12-31'"
                    :error-mulai="$errors->first('tanggal_mulai')" :error-selesai="$errors->first('tanggal_selesai')" />

                <x-ui.textarea label="Alasan revisi" name="alasan" wire:model="alasan" rows="3" maxlength="1000" required
                    helper="Jelaskan mengapa jadwal perlu diubah (maksimal 1000 karakter)." :error="$errors->first('alasan')" />

                <x-app.diinput-oleh :pegawai="$penginput" :value="$diinput_oleh" :ganti="$ganti" :error="$errors->first('diinput_oleh')"
                    wire:model.live="diinput_oleh" class="border-t border-neutral-200 pt-4" />
            </form>
        @endif
        <x-slot:footer>
            <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-modal', { name: 'revisi-jadwal' })">Batal</x-ui.button>
            @php $nama = $diinput_oleh ? $penginput->firstWhere('id', $diinput_oleh)?->nama_bergelar : null; @endphp
            <x-ui.button form="form-revisi" icon="paper-airplane" wire:loading.attr="disabled" wire:target="kirim" class="max-sm:w-full">
                {{ $nama ? 'Ajukan sebagai '.$nama : 'Ajukan revisi' }}
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
