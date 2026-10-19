{{-- login.md §5 "Diinput oleh" for Irban unit accounts (render it only for them). Bind with wire:model.live (the host's confirm button names the person).
     `pegawai` = App\Support\DiinputOleh::pilihan() (active PPJ + anggota of this irban, current year). When `value` is a prefilled session choice and
     `ganti` is false it shows as a mint-50 chip button "Ganti penginput, saat ini …" that calls the host's `gantiPenginput()`; otherwise a required
     combobox ("Budi Santoso · …0012"), focused when it replaces the chip. Never hidden. No list → warning that the admin must fill Komposisi Irban Tahunan. --}}
@use('App\Support\DiinputOleh')
@props(['pegawai', 'value' => null, 'ganti' => false, 'error' => null, 'dropUp' => false])

@php
    $helper = 'Pilih pegawai yang benar-benar melakukan input ini.';
    $chosen = $value ? $pegawai->firstWhere('id', $value) : null;
@endphp

<div {{ $attributes->whereDoesntStartWith('wire:model') }}>
    @if ($pegawai->isEmpty())
        <p class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-neutral-900">{{ svg('heroicon-m-user-circle', 'size-5 shrink-0 text-neutral-600') }}Diinput oleh</p>
        <x-ui.alert variant="warning" title="Daftar penginput belum tersedia">
            Belum ada PPJ atau anggota aktif untuk irban ini pada tahun {{ DiinputOleh::tahun() }}. Minta Admin mengisi Komposisi Irban Tahunan terlebih dahulu.
        </x-ui.alert>
        <x-ui.field-meta part="meta" id="diinput_oleh" :error="$error" />
    @elseif ($chosen && ! $ganti && ! $error)
        <p class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-neutral-900">{{ svg('heroicon-m-user-circle', 'size-5 shrink-0 text-neutral-600') }}Diinput oleh</p>
        <button type="button" wire:click="gantiPenginput" aria-label="Ganti penginput, saat ini {{ $chosen->nama_bergelar }}" aria-describedby="diinput_oleh-helper"
            class="inline-flex min-h-11 max-w-full items-center gap-3 rounded-md border border-mint-200 bg-mint-50 px-3 text-left text-sm text-neutral-900 hover:border-mint-700 lg:min-h-10">
            <span class="truncate font-medium">{{ $chosen->nama_bergelar }}</span>
            <span class="shrink-0 font-medium text-mint-700 underline underline-offset-2">Ganti</span>
        </button>
        <x-ui.field-meta part="meta" id="diinput_oleh" :helper="$helper" />
    @else
        <div @if ($ganti) x-init="$nextTick(() => $el.querySelector('input')?.focus())" @endif>
            <x-ui.combobox name="diinput_oleh" label="Diinput oleh" icon="user-circle" required placeholder="Cari nama penginput"
                :options="$pegawai->mapWithKeys(fn ($p) => [$p->id => DiinputOleh::label($p)])->all()"
                :drop-up="$dropUp" :helper="$helper" :error="$error" {{ $attributes->whereStartsWith('wire:model') }} />
        </div>
    @endif
</div>
