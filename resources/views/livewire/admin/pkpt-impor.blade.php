{{-- Placeholder for the PKPT Excel import (Fase 3 Task 3). --}}
<div>
    <x-ui.page-header :title="'Impor Item PKPT '.$pkpt->tahun">
        <x-slot:actions>
            <a href="{{ route('admin.pkpt.show', $pkpt) }}" wire:navigate class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-border-strong bg-white px-4 text-sm font-medium text-mint-700 hover:bg-mint-50 lg:h-10">
                {{ svg('heroicon-m-arrow-left', 'size-5') }}Kembali ke PKPT {{ $pkpt->tahun }}
            </a>
        </x-slot:actions>
    </x-ui.page-header>
    <x-ui.card>
        <x-ui.empty-state icon="arrow-up-tray" title="Segera tersedia" description="Impor item PKPT dari Excel sedang disiapkan. Sementara ini, tambahkan item lewat baris tambah di halaman PKPT." />
    </x-ui.card>
</div>
