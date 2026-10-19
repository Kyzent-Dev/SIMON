{{-- DESIGN §8 x-ui.file-upload: dashed border-strong dropzone (mint-50 on drag-over), filename + size with "Ganti"/"Hapus", max size in helper.
     `accept` is the extension for the type (".pdf" for documents, ".xlsx" for imports). The native input covers the zone, so click, drop and keyboard all work.
     Pass wire:model on the component; Livewire upload events drive the progress bar. `href` adds a "Lihat" link for an already stored file. --}}
@props(['name', 'label', 'accept' => '.pdf', 'helper' => null, 'error' => null, 'filename' => null, 'size' => null, 'removeAction' => null, 'href' => null])

@php $id = $name; @endphp

<div x-data="{ drag: false, busy: false, pct: 0 }"
    x-on:livewire-upload-start="busy = true; pct = 0" x-on:livewire-upload-progress="pct = $event.detail.progress"
    x-on:livewire-upload-finish="busy = false" x-on:livewire-upload-error="busy = false">
    <span class="mb-1.5 block text-sm font-medium text-neutral-900">{{ $label }}</span>

    <div class="relative flex min-h-36 flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-border-strong bg-white p-4 text-center focus-within:ring-2 focus-within:ring-mint-600 focus-within:ring-offset-2"
        x-bind:class="drag && 'bg-mint-50'" x-on:dragover="drag = true" x-on:dragleave="drag = false" x-on:drop="drag = false">
        <input id="{{ $id }}" name="{{ $name }}" type="file" accept="{{ $accept }}"
            @if ($helper) aria-describedby="{{ $id }}-helper" @endif
            {{ $attributes->class('absolute inset-0 size-full cursor-pointer opacity-0') }}>
        {{ svg('heroicon-o-arrow-up-tray', 'size-8 text-mint-600') }}
        <p class="text-sm text-neutral-900"><span class="font-medium text-mint-700">Pilih berkas</span> atau seret ke sini</p>
        @if ($helper)
            <p id="{{ $id }}-helper" class="text-xs text-neutral-500">{{ $helper }}</p>
        @endif
    </div>

    <div x-show="busy" x-cloak class="mt-3" role="status">
        <div class="h-2 overflow-hidden rounded-full bg-neutral-100"><div class="h-full bg-mint-700" x-bind:style="`width:${pct}%`"></div></div>
        <p class="mt-1 text-xs text-neutral-600">Mengunggah… <span x-text="pct"></span>%</p>
    </div>

    @if ($filename)
        <div class="mt-3 flex flex-wrap items-center gap-2 rounded-md border border-neutral-200 bg-white p-3 text-sm text-neutral-900">
            {{ svg('heroicon-m-document', 'size-5 shrink-0 text-neutral-600') }}
            <span class="min-w-0 flex-1 break-all font-medium">{{ $filename }}@if ($size) <span class="font-normal text-neutral-600">· {{ $size }}</span>@endif</span>
            @if ($href)
                <a href="{{ $href }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center rounded-md px-3 font-medium text-mint-700 hover:bg-mint-50 lg:h-8">Lihat<span class="sr-only"> {{ $filename }} (tab baru)</span></a>
            @endif
            <label for="{{ $id }}" class="inline-flex h-11 cursor-pointer items-center rounded-md border border-border-strong px-3 font-medium text-mint-700 hover:bg-mint-50 lg:h-8">Ganti</label>
            @if ($removeAction)
                <button type="button" wire:click="{{ $removeAction }}" class="inline-flex h-11 items-center rounded-md px-3 font-medium text-mint-700 hover:bg-mint-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mint-600 lg:h-8">Hapus</button>
            @endif
        </div>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" role="alert" class="mt-1.5 flex items-start gap-1.5 text-sm text-danger-700">
            {{ svg('heroicon-m-exclamation-circle', 'size-5 shrink-0') }}<span>{{ $error }}</span>
        </p>
    @endif
</div>
