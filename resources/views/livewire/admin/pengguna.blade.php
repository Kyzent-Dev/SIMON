{{-- admin.md "Pengguna": table + create/edit drawer, reset-password modal. No animation on the table (DESIGN §7.5). Status/last-login columns and bulk action not built (see decisions). --}}
<div>
    <x-ui.page-header title="Pengguna" description="Akun yang dapat masuk ke SIMON-SPT.">
        <x-slot:actions>
            <x-ui.button type="button" icon="plus" wire:click="create">Tambah pengguna</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 sm:max-w-56">
        <x-ui.select label="Peran" name="peran" wire:model.live="peran" :options="['semua' => 'Semua'] + $roles" />
    </div>

    <x-ui.table label="Daftar pengguna">
        <x-slot:head>
            <x-ui.th>Nama</x-ui.th>
            <x-ui.th>Username</x-ui.th>
            <x-ui.th>Email</x-ui.th>
            <x-ui.th>Peran</x-ui.th>
            <x-ui.th>Irban</x-ui.th>
            <x-ui.th><span class="sr-only">Aksi</span></x-ui.th>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr wire:key="pengguna-{{ $row->id }}">
                <td class="min-w-40 font-medium">{{ $row->name }}</td>
                <td class="whitespace-nowrap">{{ $row->username }}</td>
                <td class="min-w-40 break-all">{{ $row->email }}</td>
                <td class="whitespace-nowrap">{{ $row->role->label() }}</td>
                <td class="min-w-28">{{ $row->irban?->nama ?? '—' }}</td>
                <td class="whitespace-nowrap text-right">
                    <div class="flex items-center justify-end gap-2">
                        <x-ui.button type="button" size="sm" variant="secondary" wire:click="edit({{ $row->id }})" aria-label="Ubah {{ $row->name }}" class="max-lg:h-11">Ubah</x-ui.button>
                        <x-ui.dropdown label="Aksi untuk {{ $row->name }}">
                            <button type="button" role="menuitem" wire:click="confirmReset({{ $row->id }})">{{ svg('heroicon-m-key', 'size-5 text-neutral-600') }}Atur ulang kata sandi</button>
                        </x-ui.dropdown>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="!h-auto !p-0">
                    <x-ui.empty-state icon="magnifying-glass" title="Tidak ada pengguna dengan peran ini." />
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <x-ui.pagination :paginator="$rows" class="mt-4" />

    <x-ui.drawer name="pengguna-form" :title="$editingId ? 'Ubah pengguna' : 'Tambah pengguna'" size="lg" wire:model="open">
        <form novalidate id="pengguna-form" wire:submit="save" class="space-y-5">
            <x-ui.input label="Nama" name="name" wire:model="name" required autocomplete="off" :error="$errors->first('name')" />
            <x-ui.input label="Username" name="username" wire:model="username" required autocomplete="off" helper="Huruf kecil, angka, titik, atau garis bawah (3–30 karakter)." :error="$errors->first('username')" />
            <x-ui.input label="Email" name="email" type="email" wire:model="email" required autocomplete="off" :error="$errors->first('email')" />
            <x-ui.select label="Peran" name="role" wire:model.live="role" required :options="$roles" :error="$errors->first('role')" />
            @if ($role === 'irban')
                <x-ui.select label="Irban" name="irban_id" wire:model="irban_id" required placeholder="Pilih irban" :options="$irbanOptions" :error="$errors->first('irban_id')" />
            @endif
            @unless ($editingId)
                <x-ui.input label="Kata sandi sementara" name="password" wire:model="password" required autocomplete="off"
                    helper="Minimal 8 karakter, huruf besar dan kecil, serta angka. Pengguna wajib menggantinya saat masuk pertama kali." :error="$errors->first('password')" />
            @endunless
        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'pengguna-form' })">Batal</x-ui.button>
            <x-ui.button form="pengguna-form" wire:loading.attr="disabled" wire:target="save">Simpan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    <x-ui.modal name="pengguna-reset" title="Atur ulang kata sandi" size="sm" wire:model="resetOpen">
        <form novalidate id="pengguna-reset-form" wire:submit="aturUlang" class="space-y-4">
            <p>Kata sandi <strong class="font-semibold">{{ $resetting?->name }}</strong> akan diganti. Pengguna wajib menggantinya saat masuk berikutnya.</p>
            <x-ui.input label="Kata sandi sementara" name="sementara" wire:model="sementara" required autocomplete="off"
                helper="Minimal 8 karakter, huruf besar dan kecil, serta angka." :error="$errors->first('sementara')" />
        </form>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', { name: 'pengguna-reset' })">Batal</x-ui.button>
            <x-ui.button form="pengguna-reset-form" wire:loading.attr="disabled" wire:target="aturUlang">Atur ulang</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
