{{-- DESIGN §8 x-ui.pagination for Livewire components using WithPagination and a public `$perPage` (name via `perPageModel`).
     "Menampilkan 1–25 dari 312", per-page 25/50/100, page buttons call gotoPage/previousPage/nextPage.
     `:per-page-options="[]"` hides the per-page select (fixed page size, public Realisasi). --}}
@props(['paginator', 'perPageModel' => 'perPage', 'perPageOptions' => [25, 50, 100]])

@php
    $n = fn ($v) => number_format((int) $v, 0, ',', '.');
    $pageName = $paginator->getPageName();
    $window = \Illuminate\Pagination\UrlWindow::make($paginator);
    $pages = array_filter([$window['first'], is_array($window['slider']) ? '…' : null, $window['slider'], is_array($window['last']) ? '…' : null, $window['last']]);
    $btn = 'inline-flex size-11 items-center justify-center rounded-md text-sm font-medium lg:size-9';
    $perPageId = 'per-page-'.$pageName;
@endphp

<div {{ $attributes->class('flex flex-col gap-3 text-sm text-neutral-600 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <p class="tabular-nums">Menampilkan {{ $paginator->total() ? $n($paginator->firstItem()).'–'.$n($paginator->lastItem()) : '0' }} dari {{ $n($paginator->total()) }}</p>
        @if ($perPageOptions)
        <div class="flex items-center gap-2">
            <label for="{{ $perPageId }}">Baris per halaman</label>
            <select id="{{ $perPageId }}" wire:model.live="{{ $perPageModel }}"
                class="h-11 rounded-md border-border-strong bg-white py-0 pr-8 pl-3 text-base text-neutral-900 focus:border-mint-700 focus:ring-2 focus:ring-mint-600 lg:h-9 lg:text-sm">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}" @selected($paginator->perPage() == $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        @endif
    </div>

    @if ($paginator->hasPages())
        <nav aria-label="Navigasi halaman">
            <ul role="list" class="flex flex-wrap items-center gap-1 tabular-nums">
                <li>
                    <button type="button" wire:click="previousPage('{{ $pageName }}')" @disabled($paginator->onFirstPage()) aria-label="Halaman sebelumnya"
                        class="{{ $btn }} text-mint-700 enabled:hover:bg-mint-50 disabled:text-neutral-400">{{ svg('heroicon-m-chevron-left', 'size-5') }}</button>
                </li>
                @foreach ($pages as $part)
                    @if (is_string($part))
                        <li aria-hidden="true" class="px-1 text-neutral-600">…</li>
                    @else
                        @foreach ($part as $page => $url)
                            <li>
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="{{ $btn }} bg-mint-50 text-mint-700"><span class="sr-only">Halaman </span>{{ $page }}</span>
                                @else
                                    <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" class="{{ $btn }} text-neutral-900 hover:bg-neutral-50"><span class="sr-only">Halaman </span>{{ $page }}</button>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach
                <li>
                    <button type="button" wire:click="nextPage('{{ $pageName }}')" @disabled(! $paginator->hasMorePages()) aria-label="Halaman berikutnya"
                        class="{{ $btn }} text-mint-700 enabled:hover:bg-mint-50 disabled:text-neutral-400">{{ svg('heroicon-m-chevron-right', 'size-5') }}</button>
                </li>
            </ul>
        </nav>
    @endif
</div>
