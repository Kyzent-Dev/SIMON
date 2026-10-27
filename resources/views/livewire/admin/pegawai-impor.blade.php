{{-- admin.md "Impor Excel": stepper 1 Unggah -> 2 Pratinjau & Validasi -> 3 Impor. Row tints come from --color-row-* tokens; status is always icon + text. No animation (DESIGN §7.5). --}}
@php
    $steps = [1 => 'Unggah', 2 => 'Pratinjau & Validasi', 3 => 'Impor'];
    $tampil = $galatSaja ? array_filter($rows, fn ($r) => $r['status'] === 'error') : $rows;
    $dapatDiimpor = $valid + $peringatan;
    $terblokir = $galat > 0 && ! $lewati;
@endphp
<div>
    <x-ui.page-header title="Impor Pegawai" description="Unggah berkas Excel, periksa hasil validasi, lalu impor. NIP yang sudah ada akan diperbarui.">
        <x-slot:actions>
            <a href="{{ route('admin.pegawai') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 lg:h-10">
                {{ svg('heroicon-m-arrow-left', 'size-5') }}Kembali ke daftar
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <ol class="mb-6 flex flex-wrap gap-x-6 gap-y-2" aria-label="Langkah impor">
        @foreach ($steps as $n => $nama)
            <li class="flex items-center gap-2 text-sm {{ $step === $n ? 'font-semibold text-neutral-900' : 'text-neutral-600' }}" @if ($step === $n) aria-current="step" @endif>
                <span class="flex size-7 items-center justify-center rounded-full text-xs font-semibold {{ $step === $n ? 'bg-mint-700 text-white' : 'border border-border-strong bg-white text-neutral-900' }}">
                    @if ($step > $n){{ svg('heroicon-m-check', 'size-4') }}<span class="sr-only">Selesai: </span>@else{{ $n }}@endif
                </span>
                {{ $nama }}
            </li>
        @endforeach
    </ol>

    @if ($step === 1)
        <x-ui.card class="max-w-2xl p-6">
            <x-ui.file-upload name="file" label="Berkas Excel (.xlsx)" accept=".xlsx" wire:model="file"
                helper="Format .xlsx, maksimal 5 MB dan 2000 baris. Kolom: nama_bergelar, nip, pangkat, golongan, jabatan, email (opsional), aktif."
                :error="$errors->first('file')" :filename="$file && $errors->has('file') ? $file->getClientOriginalName() : null" remove-action="hapusFile" />
            <div wire:loading wire:target="file" class="mt-3 text-sm text-neutral-600" role="status">Memeriksa berkas…</div>
            <div class="mt-4">
                <button type="button" wire:click="unduhTemplat" class="inline-flex h-11 items-center gap-2 rounded-md px-1 text-sm font-medium text-mint-700 underline underline-offset-2 hover:bg-mint-50 lg:h-8">
                    {{ svg('heroicon-m-arrow-down-tray', 'size-5') }}Unduh templat Excel
                </button>
            </div>
        </x-ui.card>
    @elseif ($step === 2)
        <div class="mb-4 flex flex-wrap items-center gap-2" role="status">
            <span class="mr-2 break-all text-sm text-neutral-600">{{ $namaFile }}</span>
            <span class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 bg-white px-2.5 py-1 text-sm text-neutral-900">{{ svg('heroicon-m-check-circle', 'size-5 text-success-700') }}{{ $valid }} valid</span>
            <span class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 bg-white px-2.5 py-1 text-sm text-neutral-900">{{ svg('heroicon-m-exclamation-triangle', 'size-5 text-accent-700') }}{{ $peringatan }} peringatan</span>
            <span class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 bg-white px-2.5 py-1 text-sm text-neutral-900">{{ svg('heroicon-m-x-circle', 'size-5 text-danger-700') }}{{ $galat }} galat</span>
        </div>

        @if ($galat > 0)
            <x-ui.alert variant="danger" title="Ada baris galat" class="mb-4">
                Perbaiki berkas lalu unggah ulang, atau centang "Lewati baris galat" untuk mengimpor baris lainnya.
                <button type="button" wire:click="unduhGalat" class="mt-1 block font-medium text-mint-700 underline underline-offset-2">Unduh daftar galat (.xlsx)</button>
            </x-ui.alert>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-1">
            <x-ui.checkbox name="galatSaja" label="Tampilkan hanya galat" wire:model.live="galatSaja" />
            <x-ui.checkbox name="lewati" label="Lewati baris galat" wire:model.live="lewati" />
        </div>

        <x-ui.table label="Pratinjau impor pegawai" density="compact">
            <x-slot:head>
                <x-ui.th class="text-right">Baris</x-ui.th>
                <x-ui.th>Nama bergelar</x-ui.th>
                <x-ui.th>NIP</x-ui.th>
                <x-ui.th>Pangkat/Golongan</x-ui.th>
                <x-ui.th>Jabatan</x-ui.th>
                <x-ui.th>Email</x-ui.th>
                <x-ui.th>Aktif</x-ui.th>
                <x-ui.th>Status</x-ui.th>
            </x-slot:head>
            @forelse ($tampil as $r)
                <tr wire:key="baris-{{ $r['baris'] }}" @class(['!bg-row-error' => $r['status'] === 'error', '!bg-row-warning' => $r['status'] === 'warning'])>
                    <td class="text-right">{{ $r['baris'] }}</td>
                    <td class="min-w-40">{{ $r['data']['nama'] ?: '—' }}</td>
                    <td class="whitespace-nowrap">{{ $r['data']['nip'] ?: '—' }}</td>
                    <td class="min-w-32">{{ collect([$r['data']['pangkat'], $r['data']['golongan']])->filter()->implode(' / ') ?: '—' }}</td>
                    <td class="min-w-32">{{ $r['data']['jabatan'] ?: '—' }}</td>
                    <td class="min-w-40 break-all">{{ ($r['data']['email'] ?? '') ?: '—' }}</td>
                    <td>{{ $r['data']['aktif'] ? 'Ya' : 'Tidak' }}</td>
                    <td class="min-w-64 py-2">
                        @if ($r['status'] === 'error')
                            <ul class="space-y-1">
                                @foreach ($r['errors'] as $e)
                                    <li class="flex items-start gap-1.5">{{ svg('heroicon-m-x-circle', 'mt-px size-5 shrink-0 text-danger-700') }}<span><span class="font-medium">Baris {{ $r['baris'] }}:</span> {{ $e['pesan'] }}</span></li>
                                @endforeach
                            </ul>
                        @elseif ($r['status'] === 'warning')
                            <span class="flex items-start gap-1.5">{{ svg('heroicon-m-exclamation-triangle', 'mt-px size-5 shrink-0 text-accent-700') }}<span>{{ $r['warning'] }}</span></span>
                        @else
                            <span class="flex items-center gap-1.5">{{ svg('heroicon-m-check-circle', 'size-5 shrink-0 text-success-700') }}Valid</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="!h-auto !p-0"><x-ui.empty-state icon="check-circle" title="Tidak ada baris galat." /></td></tr>
            @endforelse
        </x-ui.table>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-ui.button type="button" icon="arrow-up-tray" wire:click="impor" :disabled="$terblokir || $dapatDiimpor === 0">Impor {{ $dapatDiimpor }} baris valid</x-ui.button>
            <x-ui.button type="button" variant="secondary" wire:click="ulangi">Unggah ulang</x-ui.button>
            @if ($terblokir)
                <p class="text-sm text-neutral-600">Selesaikan galat atau centang "Lewati baris galat".</p>
            @endif
        </div>
    @else
        <x-ui.card class="max-w-2xl p-6">
            <x-ui.alert variant="success" title="Impor selesai">{{ $diimpor }} pegawai berhasil diimpor atau diperbarui.</x-ui.alert>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('admin.pegawai') }}" class="inline-flex h-11 items-center justify-center rounded-md bg-mint-700 px-4 text-sm font-medium text-white hover:bg-mint-800 lg:h-10">Lihat daftar pegawai</a>
                <x-ui.button type="button" variant="secondary" wire:click="ulangi">Impor berkas lain</x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
