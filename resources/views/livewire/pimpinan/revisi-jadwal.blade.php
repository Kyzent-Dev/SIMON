{{-- Pimpinan "Revisi Jadwal" (pimpinan.md Approval list + Accessibility + Copy; _shared.md tables/modals; login.md §5 audit line).
     Cards for pending revisions (oldest first) with the dashed-vs-solid mini lane, then a compact "Riwayat keputusan" table.
     No animation on cards, table or forms (DESIGN §7.5); only the modals' own transition. --}}
@use('App\Support\Tanggal')
<div x-on:revisi-diputuskan.window="setTimeout(() => document.getElementById('menunggu-heading')?.focus(), 50)">
    <x-ui.page-header title="Revisi Jadwal" description="Pengajuan revisi jadwal PKPT dari irban. Revisi yang disetujui mengubah jadwal berlaku; jadwal asli tetap tersimpan." />

    <section aria-labelledby="menunggu-heading">
        <h2 id="menunggu-heading" tabindex="-1" class="rounded-sm font-display text-[22px] leading-[30px] font-bold text-neutral-900">
            Menunggu Persetujuan Anda <span class="tabular-nums text-neutral-600">({{ $menunggu->count() }})</span>
        </h2>

        @if ($menunggu->isEmpty())
            <x-ui.card class="mt-4">
                <x-ui.empty-state icon="check-circle" title="Tidak ada revisi jadwal yang menunggu persetujuan."
                    description="Pengajuan baru dari irban akan tampil di sini." />
            </x-ui.card>
        @else
            <ul class="mt-4 grid gap-4 xl:grid-cols-2" aria-label="Revisi jadwal menunggu persetujuan">
                @foreach ($menunggu as $r)
                    @php
                        $jenis = $r->item->jenisPenugasan;
                        $konteks = $jenis->nama.' '.$r->item->irban->nama;
                    @endphp
                    <li wire:key="m-{{ $r->id }}" class="flex flex-col rounded-lg border border-neutral-200 bg-white p-4 text-sm text-neutral-900 shadow-xs lg:p-5">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <x-app.type-chip :color="$jenis->warna" :label="$jenis->nama" />
                            <span class="font-semibold">{{ $r->item->irban->nama }}</span>
                            <span class="text-neutral-600">PKPT {{ $r->item->pkpt->tahun }}</span>
                        </div>
                        @if ($r->item->keterangan)
                            <p class="mt-1 text-neutral-600">{{ $r->item->keterangan }}</p>
                        @endif

                        <x-app.revisi-lane :revisi="$r" :color="$jenis->warna" :tahun="$r->item->pkpt->tahun" class="mt-4" />

                        <p class="mt-3 whitespace-pre-line"><span class="font-medium">Alasan:</span> {{ $r->alasan }}</p>
                        <p class="mt-2 text-xs text-neutral-600">
                            Diajukan {{ $r->created_at->timezone('Asia/Jakarta')->translatedFormat('d F Y') }} ·
                            @if ($r->diinputOleh)
                                Diinput oleh <strong class="font-semibold text-neutral-900">{{ $r->diinputOleh->nama_bergelar }}</strong> (akun {{ $r->diajukanOleh?->name }})
                            @else
                                oleh akun <strong class="font-semibold text-neutral-900">{{ $r->diajukanOleh?->name }}</strong>
                            @endif
                        </p>

                        <div class="mt-4 flex flex-col gap-2 border-t border-neutral-200 pt-4 sm:flex-row sm:justify-end">
                            <x-ui.button type="button" variant="danger-outline" icon="x-mark" wire:click="tolak({{ $r->id }})"
                                aria-label="Tolak revisi jadwal {{ $konteks }}">Tolak</x-ui.button>
                            <x-ui.button type="button" icon="check" wire:click="setujui({{ $r->id }})"
                                aria-label="Setujui revisi jadwal {{ $konteks }}">Setujui</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="riwayat-heading" class="mt-10">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <h2 id="riwayat-heading" class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Riwayat keputusan</h2>
            <div class="w-full sm:w-40">
                <x-ui.select label="Tahun PKPT" name="tahun" wire:model.live="tahun" :options="$tahunList" :value="$this->tahun" />
            </div>
        </div>

        <div wire:loading.class="opacity-60" wire:target="tahun,perPage,gotoPage,previousPage,nextPage">
            @if ($riwayat->isEmpty() && $riwayat->currentPage() === 1)
                <x-ui.card>
                    <x-ui.empty-state icon="clock" :title="'Belum ada keputusan revisi jadwal untuk tahun '.$this->tahun.'.'" description="Pilih tahun lain untuk melihat keputusan sebelumnya." />
                </x-ui.card>
            @else
                <x-ui.table :label="'Riwayat keputusan revisi jadwal '.$this->tahun" density="compact">
                    <x-slot:head>
                        <x-ui.th>Diputuskan</x-ui.th>
                        <x-ui.th>Irban</x-ui.th>
                        <x-ui.th>Jenis Penugasan</x-ui.th>
                        <x-ui.th>Semula → Baru</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                        <x-ui.th>Diputuskan oleh</x-ui.th>
                        <x-ui.th>Komentar</x-ui.th>
                    </x-slot:head>
                    @foreach ($riwayat as $r)
                        @php $tag = $r->status->tag(); @endphp
                        <tr wire:key="r-{{ $r->id }}">
                            <td class="whitespace-nowrap">{{ $r->diputuskan_pada?->timezone('Asia/Jakarta')->translatedFormat('d F Y') }}</td>
                            <td class="whitespace-nowrap">{{ $r->item->irban->nama }}</td>
                            <td class="min-w-40"><x-app.type-chip :color="$r->item->jenisPenugasan->warna" :label="$r->item->jenisPenugasan->nama" /></td>
                            {{-- relative: keeps the absolute sr-only span inside the table scroller (else it widens the page at 360). --}}
                            <td class="relative whitespace-nowrap">
                                {{ Tanggal::rentang($r->tanggal_mulai_semula, $r->tanggal_selesai_semula) }}
                                <span aria-hidden="true">→</span><span class="sr-only">menjadi</span>
                                {{ Tanggal::rentang($r->tanggal_mulai, $r->tanggal_selesai) }}
                            </td>
                            <td class="whitespace-nowrap"><x-app.tag :icon="$tag['icon']" :variant="$tag['variant']">{{ $r->status->label() }}</x-app.tag></td>
                            <td class="whitespace-nowrap">{{ $r->diputuskanOleh?->name ?? 'Pengguna terhapus' }}</td>
                            <td class="min-w-56 !py-2">@if ($r->komentar){{ $r->komentar }}@else<span class="text-neutral-500">—</span>@endif</td>
                        </tr>
                    @endforeach
                </x-ui.table>
                <x-ui.pagination :paginator="$riwayat" class="mt-4" />
            @endif
        </div>
    </section>

    @foreach (['setujui' => true, 'tolak' => false] as $mode => $setujui)
        <x-ui.modal :name="'revisi-'.$mode" :title="$setujui ? 'Setujui Revisi Jadwal' : 'Tolak Revisi Jadwal'" size="md" wire:model="{{ $mode }}Open">
            @if ($aktif)
                <form id="form-{{ $mode }}" wire:submit="putuskan({{ $setujui ? 'true' : 'false' }})" novalidate class="space-y-4">
                    <p>
                        @if ($setujui)
                            Jadwal berlaku item ini berubah menjadi <strong class="font-semibold">{{ Tanggal::rentang($aktif->tanggal_mulai, $aktif->tanggal_selesai) }}</strong>
                            dan dipakai untuk kesesuaian SPT. Jadwal asli tetap tersimpan.
                        @else
                            Jadwal berlaku tetap <strong class="font-semibold">{{ Tanggal::rentang($aktif->tanggal_mulai_semula, $aktif->tanggal_selesai_semula) }}</strong>.
                            Alasan penolakan dapat dilihat oleh irban.
                        @endif
                    </p>
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-1 rounded-md border border-neutral-200 bg-neutral-25 p-3 sm:grid-cols-[auto_1fr]">
                        <dt class="text-neutral-600">Jenis penugasan</dt>
                        <dd class="max-sm:mb-2"><x-app.type-chip :color="$aktif->item->jenisPenugasan->warna" :label="$aktif->item->jenisPenugasan->nama" /></dd>
                        <dt class="text-neutral-600">Irban</dt>
                        <dd class="max-sm:mb-2">{{ $aktif->item->irban->nama }}</dd>
                        <dt class="text-neutral-600">Semula</dt>
                        <dd class="tabular-nums max-sm:mb-2">{{ Tanggal::rentang($aktif->tanggal_mulai_semula, $aktif->tanggal_selesai_semula) }}</dd>
                        <dt class="text-neutral-600">Usulan</dt>
                        <dd class="tabular-nums">{{ Tanggal::rentang($aktif->tanggal_mulai, $aktif->tanggal_selesai) }}</dd>
                    </dl>
                    @if ($setujui)
                        <x-ui.textarea label="Komentar (opsional)" name="komentar" id="komentar-setujui" wire:model="komentar" rows="3" maxlength="1000"
                            helper="Maksimal 1000 karakter." :error="$errors->first('komentar')" />
                    @else
                        <x-ui.textarea label="Alasan penolakan" name="komentar" id="komentar-tolak" wire:model="komentar" rows="3" maxlength="1000" required
                            helper="Jelaskan mengapa revisi ditolak (maksimal 1000 karakter)." :error="$errors->first('komentar')" />
                    @endif
                </form>
            @endif
            <x-slot:footer>
                <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-modal', { name: 'revisi-{{ $mode }}' })">Batal</x-ui.button>
                <x-ui.button :form="'form-'.$mode" :variant="$setujui ? 'primary' : 'danger'" :icon="$setujui ? 'check' : 'x-mark'"
                    wire:loading.attr="disabled" wire:target="putuskan" class="max-sm:w-full">
                    {{ $setujui ? 'Setujui revisi' : 'Tolak revisi' }}
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endforeach
</div>
