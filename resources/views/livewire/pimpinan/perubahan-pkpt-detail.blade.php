{{-- Pimpinan: detail Perubahan PKPT (Fase 9.2 Task 8; pimpinan.md Approval list + Accessibility, _shared.md §1 page header, §5 modals/toasts; DESIGN §3.4, §7.5).
     Order: failed-approval alert → paket header → operasi before → after (x-app.operasi-perubahan) → dampak → peringatan (cek) → Setujui/Tolak (diajukan only).
     After a decision the buttons disappear, so `fokus-ke` moves focus to the h1; a failed approval focuses the alert. No animation; modal transitions only. --}}
@php
    $tag = $p->status->tag();
    $tgl = fn ($d) => $d?->timezone('Asia/Jakarta')->translatedFormat('d F Y');
@endphp
<div x-on:fokus-ke.window="setTimeout(() => document.getElementById($event.detail.id)?.focus(), 50)">
    <x-ui.page-header :title="$judul" title-id="judul-perubahan">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-neutral-600">
                <x-app.tag :icon="$tag['icon']" :variant="$tag['variant']">{{ $p->status->label() }}</x-app.tag>
                <span>{{ $p->jenis->label() }}</span>
                <span>Dibuat oleh {{ $p->pembuat?->name ?? 'Admin' }}</span>
                @if ($p->diajukan_pada)<span class="tabular-nums">Diajukan {{ $tgl($p->diajukan_pada) }}</span>@endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('pimpinan.perubahan-pkpt') }}" wire:navigate class="inline-flex h-11 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 lg:h-10">
                {{ svg('heroicon-m-arrow-left', 'size-5') }}Kembali ke daftar
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="max-w-4xl space-y-6">
        @if ($alasanGagal)
            <div id="alert-gagal" tabindex="-1" role="alert" class="rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mint-600">
                <x-ui.alert variant="danger" title="Perubahan belum dapat disetujui">
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                        @foreach ($alasanGagal as $m)<li class="break-words">{{ $m }}</li>@endforeach
                    </ul>
                    <p class="mt-1 text-neutral-600">Perubahan tetap menunggu keputusan. Anda dapat menolaknya dengan alasan agar Admin memperbaikinya.</p>
                </x-ui.alert>
            </div>
        @endif

        @if ($p->status === \App\Enums\StatusPerubahan::Ditolak)
            <x-ui.alert variant="danger" title="Ditolak">
                <p class="mt-1 whitespace-pre-line break-words">{{ $p->komentar_putusan }}</p>
                <p class="mt-1 text-neutral-600">{{ $p->pemutus?->name ?? 'Pimpinan' }} · {{ $tgl($p->diputuskan_pada) }}</p>
            </x-ui.alert>
        @endif

        <x-ui.card>
            <h2 class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Ringkasan</h2>
            <dl class="mt-4 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[9rem_1fr]">
                <dt class="text-neutral-600">Jenis</dt><dd>{{ $p->jenis->label() }}</dd>
                <dt class="text-neutral-600">Alasan</dt><dd class="whitespace-pre-line break-words">{{ $p->alasan }}</dd>
                @if ($p->status === \App\Enums\StatusPerubahan::Disetujui)
                    <dt class="text-neutral-600">Disetujui</dt><dd class="tabular-nums">{{ $p->pemutus?->name ?? 'Pimpinan' }} · {{ $tgl($p->diputuskan_pada) }}</dd>
                    @if ($p->komentar_putusan)<dt class="text-neutral-600">Komentar</dt><dd class="whitespace-pre-line break-words">{{ $p->komentar_putusan }}</dd>@endif
                @endif
                <dt class="text-neutral-600">SK</dt>
                <dd class="break-words">
                    @if ($p->nomor_sk || $p->tanggal_sk || $p->berkas_sk)
                        <span class="tabular-nums">{{ $p->nomor_sk ?: 'Nomor belum diisi' }}@if ($p->tanggal_sk), {{ $tgl($p->tanggal_sk) }}@endif</span>
                        @if ($p->berkas_sk)
                            <span class="ml-2 inline-flex gap-1">
                                <a href="{{ route('perubahan-pkpt.sk', $p) }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center rounded-md px-2 font-medium text-mint-700 hover:bg-mint-50 lg:h-8">Lihat<span class="sr-only"> berkas SK (tab baru)</span></a>
                                <a href="{{ route('perubahan-pkpt.sk', ['perubahan' => $p, 'unduh' => 1]) }}" class="inline-flex h-11 items-center rounded-md px-2 font-medium text-mint-700 hover:bg-mint-50 lg:h-8">Unduh<span class="sr-only"> berkas SK</span></a>
                            </span>
                        @endif
                    @else
                        <span class="text-neutral-600">Belum diisi Admin.</span>
                    @endif
                </dd>
            </dl>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Operasi <span class="font-sans text-base font-medium text-neutral-600 tabular-nums">({{ count($operasi) }})</span></h2>
            <div class="mt-4">
                @if (count($operasi))
                    <x-app.operasi-perubahan :operasi="$operasi" />
                @else
                    <p class="rounded-md border border-dashed border-neutral-300 p-4 text-sm text-neutral-600">Tidak ada operasi.</p>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Dampak</h2>
            <h3 class="mt-4 text-lg leading-[26px] font-semibold text-neutral-900">Dampak per irban</h3>
            @if ($dampak['irban'])
                <ul role="list" class="mt-2 divide-y divide-neutral-200 rounded-md border border-neutral-200 text-sm">
                    @foreach ($dampak['irban'] as $i)
                        <li class="flex items-center justify-between gap-3 px-3 py-2"><span class="min-w-0 break-words">{{ $i['nama'] }}</span><span class="shrink-0 tabular-nums text-neutral-600">{{ $i['jumlah'] }} item</span></li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm text-neutral-600">Tidak ada irban yang terdampak.</p>
            @endif

            <h3 class="mt-6 text-lg leading-[26px] font-semibold text-neutral-900">SPT yang kesesuaiannya berubah</h3>
            @if ($dampak['spt'])
                <ul role="list" class="mt-2 space-y-2">
                    @foreach ($dampak['spt'] as $s)
                        <li class="rounded-md border border-neutral-200 p-3 text-sm">
                            <p class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <a href="{{ route('spt.show', $s['id']) }}" wire:navigate class="break-all font-semibold text-mint-700 tabular-nums hover:underline">{{ $s['nomor_lengkap'] ?? 'Nomor belum terbit' }}</a>
                                <span class="text-neutral-600">{{ $s['irban'] }}</span>
                            </p>
                            <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                @foreach (['Sebelum' => $s['sebelum'], 'Sesudah' => $s['sesudah']] as $nama => $nilai)
                                    @if (! $loop->first){{ svg('heroicon-m-arrow-right', 'size-4 text-mint-700', ['aria-hidden' => 'true']) }}@endif
                                    <span class="text-neutral-600">{{ $nama }}:</span>
                                    @if (! $nilai)
                                        <x-app.flag jenis="tidak_sesuai_pkpt" />
                                    @else
                                        <span class="inline-flex items-center gap-1">{{ svg('heroicon-m-check', 'size-4 text-neutral-700', ['aria-hidden' => 'true']) }}Sesuai</span>
                                    @endif
                                @endforeach
                            </p>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm text-neutral-600">Tidak ada SPT yang kesesuaiannya berubah.</p>
            @endif
        </x-ui.card>

        @if ($diajukan)
            @if ($cek)
                <x-ui.alert variant="warning" title="Perhatian sebelum memutuskan">
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                        @foreach ($cek as $m)<li class="break-words">{{ $m }}</li>@endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <x-ui.card>
                <h2 class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Keputusan</h2>
                <p class="mt-1 text-sm text-neutral-600">Menyetujui menerapkan semua operasi ke PKPT {{ $p->pkpt->tahun }} sekaligus. Menolak mengembalikan perubahan ke Admin beserta alasan Anda.</p>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <x-ui.button type="button" variant="danger-outline" icon="x-circle" wire:click="$set('tolakOpen', true)" aria-label="Tolak {{ $judul }}" class="max-sm:w-full">Tolak</x-ui.button>
                    <x-ui.button type="button" icon="check" wire:click="$set('setujuiOpen', true)" aria-label="Setujui {{ $judul }}" class="max-sm:w-full">Setujui</x-ui.button>
                </div>
            </x-ui.card>
        @endif
    </div>

    @if ($diajukan)
        <x-ui.modal name="perubahan-setujui" title="Setujui perubahan?" size="md" wire:model="setujuiOpen">
            <p>Semua operasi pada {{ $judul }} diterapkan ke PKPT dan nomor perubahan diberikan. Pemeriksaan ulang dilakukan saat ini juga; jika gagal, perubahan tetap menunggu.</p>
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'perubahan-setujui' })">Batal</x-ui.button>
                <x-ui.button type="button" icon="check" wire:click="setujui" wire:loading.attr="disabled" wire:target="setujui">Setujui</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="perubahan-tolak" title="Tolak perubahan?" size="md" wire:model="tolakOpen">
            <form id="form-tolak" wire:submit="tolak" novalidate>
                <x-ui.textarea name="komentar" label="Alasan penolakan" required wire:model="komentar" maxlength="1000" :error="$errors->first('komentar')" helper="Dibaca Admin. Maksimal 1000 karakter." />
            </form>
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'perubahan-tolak' })">Batal</x-ui.button>
                <x-ui.button form="form-tolak" variant="danger" icon="x-circle" wire:loading.attr="disabled" wire:target="tolak">Tolak</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
