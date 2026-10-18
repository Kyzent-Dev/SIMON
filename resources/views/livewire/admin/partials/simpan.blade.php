{{-- Per-card save button: busy state while this card's request runs (_shared.md §4). --}}
<div class="flex justify-end pt-1">
    <x-ui.button wire:loading.attr="disabled" wire:target="save('{{ $group }}')">
        <span wire:loading.remove wire:target="save('{{ $group }}')">Simpan perubahan</span>
        <span wire:loading wire:target="save('{{ $group }}')">Menyimpan…</span>
    </x-ui.button>
</div>
