{{-- admin.md "Kontak & Footer": one card form (_shared.md §4, explicit "Simpan perubahan" as on Pengaturan) + preview with the public footer partial
     (landing.md "Footer"). The preview follows the form as you type; nothing is saved until "Simpan perubahan". No animation (DESIGN §7.5). --}}
@php $err = fn ($key) => $errors->first("v.kontak.$key"); @endphp
<div>
    <x-ui.page-header title="Kontak & Footer" description="Kontak kantor yang tampil di footer halaman publik. Kolom yang kosong tidak ditampilkan." />

    <div class="space-y-6">
        <x-ui.card class="max-w-[720px]">
            <form novalidate wire:submit="save" class="space-y-4">
                <h2 class="text-lg font-semibold text-neutral-900">Kontak kantor</h2>
                <x-ui.input label="Telepon" name="kontak_telepon" type="tel" inputmode="tel" maxlength="30" autocomplete="off" wire:model.live.debounce.400ms="v.kontak.telepon"
                    :error="$err('telepon')" helper="Angka, spasi, +, -, dan tanda kurung." />
                <x-ui.input label="Email" name="kontak_email" type="email" maxlength="100" autocomplete="off" wire:model.live.debounce.400ms="v.kontak.email" :error="$err('email')" />
                <div>
                    @php $igErr = $err('instagram'); @endphp
                    <label for="kontak_instagram" class="mb-1.5 block text-sm font-medium text-neutral-900">Instagram</label>
                    <div class="flex">
                        <span aria-hidden="true" class="inline-flex items-center rounded-l-md border border-r-0 border-border-strong bg-neutral-50 px-3 text-base text-neutral-600 lg:text-sm">@</span>
                        <input id="kontak_instagram" name="kontak_instagram" type="text" maxlength="31" autocomplete="off" wire:model.live.debounce.400ms="v.kontak.instagram"
                            aria-describedby="kontak_instagram-helper{{ $igErr ? ' kontak_instagram-error' : '' }}" @if ($igErr) aria-invalid="true" @endif
                            class="block h-12 w-full min-w-0 rounded-r-md bg-white px-3 text-base text-neutral-900 focus:border-mint-700 focus:ring-2 focus:ring-mint-600 lg:h-10 lg:text-sm {{ $igErr ? 'border-danger-700' : 'border-border-strong' }}">
                    </div>
                    <x-ui.field-meta part="meta" id="kontak_instagram" helper="Nama akun tanpa @, misalnya inspektorat.muaraenim." :error="$igErr" />
                </div>
                <x-ui.textarea label="Alamat kantor" name="kontak_alamat" rows="3" maxlength="300" wire:model.live.debounce.400ms="v.kontak.alamat" :error="$err('alamat')" />
                <x-ui.input label="Tautan sematan peta" name="kontak_peta_url" type="url" autocomplete="off" wire:model.live.debounce.400ms="v.kontak.peta_url" :error="$err('peta_url')"
                    helper="Di Google Maps: Bagikan → Sematkan peta, lalu salin alamat yang diawali https://www.google.com/maps/embed?" />
                <div class="flex justify-end">
                    <x-ui.button wire:loading.attr="disabled" wire:target="save">Simpan perubahan</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <section aria-labelledby="pratinjau-footer" class="min-w-0">
            <h2 id="pratinjau-footer" class="mb-1 text-lg font-semibold text-neutral-900">Pratinjau footer</h2>
            <p class="mb-3 text-sm text-neutral-600">Tampilan footer di halaman publik.</p>
            <div class="overflow-hidden rounded-lg border border-neutral-200 shadow-xs">
                @include('partials.footer-publik', ['kontak' => $v['kontak'], 'judulTag' => 'h3'])
            </div>
        </section>
    </div>
</div>
