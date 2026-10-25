{{-- admin.md "Impor Excel": stepper 1 Unggah -> 2 Pratinjau & Validasi -> 3 Impor, same markup as pkpt-impor. Row tints come from --color-row-* tokens (text on them is
     neutral-900, DESIGN §3.4); status is always icon + text. No animation (DESIGN §7.5). Focus moves to the step heading when the step changes. --}}
@php
    $steps = [1 => 'Unggah', 2 => 'Pratinjau & Validasi', 3 => 'Impor'];
    $tampil = $galatSaja ? array_filter($rows, fn ($r) => $r['status'] === 'error') : $rows;
    $dapatDiimpor = $valid + $peringatan;
    $terblokir = $galat > 0 && ! $lewati;
    $tgl = fn (?string $t) => $t ? \Carbon\CarbonImmutable::parse($t) : null;
@endphp
<div x-data x-on:impor-fokus.window="$nextTick(() => $refs.langkah?.focus())">
    <x-ui.page-header title="Impor Data Lama" description="Unggah berkas Excel SPT lama, periksa hasil validasi, lalu impor. SPT lama tercatat dengan nomor aslinya dan tidak mengirim notifikasi." />

    <h2 x-ref="langkah" tabindex="-1" class="sr-only">Langkah {{ $step }} dari 3: {{ $steps[$step] }}</h2>
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
                helper="Format .xlsx, maksimal 5 MB dan 2000 baris. Gunakan templat agar kolom sesuai. Tanggal: dd/mm/yyyy atau yyyy-mm-dd."
                :error="$errors->first('file')" :filename="$file && $errors->has('file') ? $file->getClientOriginalName() : null" remove-action="hapusFile" />
            <div wire:loading wire:target="file" class="mt-3 text-sm text-neutral-600" role="status">Memeriksa berkas…</div>
            <div class="mt-4">
                <button type="button" wire:click="unduhTemplat" class="inline-flex h-11 items-center gap-2 rounded-md px-1 text-sm font-medium text-mint-700 underline underline-offset-2 hover:bg-mint-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mint-600 lg:h-8">
                    {{ svg('heroicon-m-arrow-down-tray', 'size-5') }}Unduh templat Excel
                </button>
            </div>
        </x-ui.card>

        <details class="mt-6 max-w-4xl rounded-lg border border-neutral-200 bg-white shadow-xs">
            <summary class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-4 text-sm font-medium text-neutral-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mint-600">
                {{ svg('heroicon-m-information-circle', 'size-5 text-info-800') }}Petunjuk kolom
            </summary>
            <div class="border-t border-neutral-200 p-4">
                <p class="mb-3 text-sm text-neutral-600">
                    Satu baris berkas menjadi satu SPT. Pegawai harus sudah ada di
                    <a href="{{ route('admin.pegawai.impor') }}" wire:navigate class="font-medium text-mint-700 underline underline-offset-2">Impor pegawai</a>;
                    item PKPT diimpor per PKPT dari <a href="{{ route('admin.pkpt') }}" wire:navigate class="font-medium text-mint-700 underline underline-offset-2">daftar PKPT</a>.
                </p>
                <x-ui.table label="Petunjuk kolom templat impor SPT" density="compact">
                    <x-slot:head>
                        <x-ui.th>Kolom</x-ui.th>
                        <x-ui.th>Wajib</x-ui.th>
                        <x-ui.th>Format dan contoh</x-ui.th>
                    </x-slot:head>
                    @foreach ($kolom as [$nama, $wajib, $format])
                        <tr wire:key="kolom-{{ $nama }}">
                            <td class="whitespace-nowrap font-mono text-[13px]">{{ $nama }}</td>
                            <td class="whitespace-nowrap">{{ $wajib }}</td>
                            <td class="min-w-64">{{ $format }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        </details>
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

        <x-ui.table label="Pratinjau impor SPT data lama" density="compact">
            <x-slot:head>
                <x-ui.th class="text-right">Baris</x-ui.th>
                <x-ui.th>Nomor SPT</x-ui.th>
                <x-ui.th>Irban</x-ui.th>
                <x-ui.th>Klasifikasi</x-ui.th>
                <x-ui.th>Jadwal</x-ui.th>
                <x-ui.th class="text-right">Personel</x-ui.th>
                <x-ui.th>Selesai</x-ui.th>
                <x-ui.th>Status</x-ui.th>
            </x-slot:head>
            @forelse ($tampil as $r)
                @php($d = $r['data'])
                <tr wire:key="baris-{{ $r['baris'] }}" @class(['!bg-row-error' => $r['status'] === 'error', '!bg-row-warning' => $r['status'] === 'warning'])>
                    <td class="text-right">{{ $r['baris'] }}</td>
                    <td class="min-w-48 break-all">{{ $d['nomor_spt'] ?: '—' }}</td>
                    <td class="min-w-24">{{ $d['irban'] ?: '—' }}</td>
                    <td class="min-w-28">{{ $d['klasifikasi'] ?: '—' }}</td>
                    <td class="whitespace-nowrap">{{ $tgl($d['tanggal_mulai']) && $tgl($d['tanggal_selesai']) ? \App\Support\Tanggal::rentang($tgl($d['tanggal_mulai']), $tgl($d['tanggal_selesai'])) : '—' }}</td>
                    <td class="text-right">{{ $d['jumlah_personel'] }}</td>
                    <td>{{ $d['selesai'] ? 'Ya' : 'Tidak' }}</td>
                    <td class="min-w-72 py-2">
                        <ul class="space-y-1">
                            @foreach ($r['errors'] as $e)
                                <li class="flex items-start gap-1.5">{{ svg('heroicon-m-x-circle', 'mt-px size-5 shrink-0 text-danger-700') }}<span class="[overflow-wrap:anywhere]"><span class="font-medium">Baris {{ $r['baris'] }}:</span> {{ $e['pesan'] }}</span></li>
                            @endforeach
                            @foreach ($r['warnings'] as $w)
                                <li class="flex items-start gap-1.5">{{ svg('heroicon-m-exclamation-triangle', 'mt-px size-5 shrink-0 text-accent-700') }}<span class="[overflow-wrap:anywhere]"><span class="font-medium">Baris {{ $r['baris'] }}:</span> {{ $w }}</span></li>
                            @endforeach
                            @if ($r['status'] === 'valid')
                                <li class="flex items-center gap-1.5">{{ svg('heroicon-m-check-circle', 'size-5 shrink-0 text-success-700') }}Valid</li>
                            @endif
                        </ul>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="!h-auto !p-0"><x-ui.empty-state icon="check-circle" title="Tidak ada baris galat." /></td></tr>
            @endforelse
        </x-ui.table>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-ui.button type="button" icon="arrow-up-tray" wire:click="impor" :disabled="$terblokir || $dapatDiimpor === 0">Impor {{ $dapatDiimpor }} baris</x-ui.button>
            <x-ui.button type="button" variant="secondary" wire:click="ulangi">Unggah ulang</x-ui.button>
            @if ($terblokir)
                <p class="text-sm text-neutral-600">Selesaikan galat atau centang "Lewati baris galat".</p>
            @endif
        </div>
    @else
        <x-ui.card class="max-w-2xl p-6">
            <x-ui.alert variant="success" title="Impor selesai">{{ $diimpor }} SPT berhasil diimpor.</x-ui.alert>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="inline-flex h-11 items-center justify-center rounded-md bg-mint-700 px-4 text-sm font-medium text-white hover:bg-mint-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mint-600 lg:h-10">Ke dasbor</a>
                <x-ui.button type="button" variant="secondary" wire:click="ulangi">Impor berkas lain</x-ui.button>
            </div>
        </x-ui.card>
    @endif
</div>
