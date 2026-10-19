{{-- "Riwayat revisi (N)" disclosure for one PKPT item (admin PKPT detail, Irban Jadwal PKPT). Expects revisi ordered oldest first,
     with diputuskanOleh, diinputOleh and diajukanOleh loaded. Native <details>: no animation, keyboard-operable as-is.
     Audit line follows login.md §5: "Diinput oleh **Budi Santoso** (akun Irban II)". --}}
@use('App\Support\Tanggal')
@props(['revisi'])

@if ($revisi->isNotEmpty())
    <details {{ $attributes->class('group') }}>
        <summary class="inline-flex min-h-11 cursor-pointer items-center gap-1 rounded-sm text-sm font-medium text-mint-700 hover:underline lg:min-h-6">
            {{ svg('heroicon-m-chevron-right', 'size-4 group-open:rotate-90') }}Riwayat revisi ({{ $revisi->count() }})
        </summary>
        <ol class="mt-2 max-w-sm space-y-3 border-l-2 border-neutral-200 pl-3">
            @foreach ($revisi as $r)
                @php $rt = $r->status->tag(); @endphp
                <li class="space-y-1 text-xs text-neutral-600" wire:key="revisi-{{ $r->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-app.tag :icon="$rt['icon']" :variant="$rt['variant']">{{ $r->status->label() }}</x-app.tag>
                        <span>Diajukan {{ $r->created_at->timezone('Asia/Jakarta')->translatedFormat('d F Y') }}</span>
                    </div>
                    <p class="text-sm text-neutral-900">
                        {{ Tanggal::rentang($r->tanggal_mulai_semula, $r->tanggal_selesai_semula) }}
                        <span aria-hidden="true">→</span><span class="sr-only">menjadi</span>
                        {{ Tanggal::rentang($r->tanggal_mulai, $r->tanggal_selesai) }}
                    </p>
                    <p class="whitespace-normal"><span class="font-medium text-neutral-900">Alasan:</span> {{ $r->alasan }}</p>
                    @if ($r->diinputOleh)
                        <p class="whitespace-normal">Diinput oleh <strong class="font-semibold text-neutral-900">{{ $r->diinputOleh->nama_bergelar }}</strong>@if ($r->diajukanOleh) (akun {{ $r->diajukanOleh->name }})@endif</p>
                    @endif
                    @if ($r->diputuskan_pada)
                        <p class="whitespace-normal">Diputuskan oleh {{ $r->diputuskanOleh?->name ?? 'pengguna terhapus' }} pada {{ $r->diputuskan_pada->timezone('Asia/Jakarta')->translatedFormat('d F Y') }}</p>
                    @endif
                    @if ($r->komentar)
                        <p class="whitespace-normal"><span class="font-medium text-neutral-900">Komentar:</span> {{ $r->komentar }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </details>
@endif
