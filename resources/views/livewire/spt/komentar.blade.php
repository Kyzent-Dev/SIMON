{{-- Komentar section of the SPT detail page (_shared.md §7, pimpinan.md "Commenting", irban.md "Balas komentar"). One level of threads, oldest first.
     A Minta Penjelasan root gets the x-ui.alert warning/success treatment with an icon + "Permintaan Penjelasan · Terbuka/Selesai" label.
     One inline reply form at a time; "Diinput oleh" (Irban) in its footer (login.md §5). Composer at the bottom for pimpinan only.
     No animation (DESIGN §7.5): the reply form shows and hides instantly. Focus targets: #judul-komentar, #balas-{root}, #komentar-{id}, #label-permintaan-{root}. --}}
@php
    $judulKartu = 'font-display text-[22px] leading-[30px] font-bold text-neutral-900';
    $hitung = 'mt-1 text-right text-xs text-neutral-500 tabular-nums';
@endphp
<section aria-labelledby="judul-komentar">
    <x-ui.card>
        <h2 id="judul-komentar" tabindex="-1" class="{{ $judulKartu }}">Komentar</h2>
        @if ($jumlah > 0)
            <p class="text-sm text-neutral-600 tabular-nums">{{ $jumlah }} komentar</p>
        @endif

        @if ($utas->isEmpty())
            <p class="mt-2 text-sm text-neutral-600">Belum ada komentar.</p>
        @else
            <ol role="list" class="mt-4 divide-y divide-neutral-200">
                @foreach ($utas as $root)
                    @php
                        $selesai = $root->minta_penjelasan && $root->status_permintaan === \App\Enums\StatusPermintaan::Selesai;
                        $blokir = $blokirTutup[$root->id] ?? null;
                    @endphp
                    <li wire:key="utas-{{ $root->id }}" class="py-4 first:pt-0 last:pb-0">
                        <article id="komentar-{{ $root->id }}" tabindex="-1" aria-labelledby="komentar-{{ $root->id }}-penulis"
                            @class(['rounded-md border border-l-4 border-neutral-200 bg-white p-3' => $root->minta_penjelasan,
                                'border-l-accent-700' => $root->minta_penjelasan && ! $selesai, 'border-l-success-700' => $selesai])>
                            @if ($root->minta_penjelasan)
                                <p id="label-permintaan-{{ $root->id }}" tabindex="-1" class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-neutral-900">
                                    @if ($selesai)
                                        {{ svg('heroicon-m-check-circle', 'size-5 shrink-0 text-success-700', ['aria-hidden' => 'true']) }}Permintaan Penjelasan · Selesai
                                    @else
                                        {{ svg('heroicon-m-question-mark-circle', 'size-5 shrink-0 text-accent-700', ['aria-hidden' => 'true']) }}Permintaan Penjelasan · Terbuka
                                    @endif
                                </p>
                                @if ($selesai)
                                    <p class="mb-2 text-xs text-neutral-600 tabular-nums">Ditutup oleh {{ $root->ditutupOleh?->name }} · {{ $root->ditutup_pada->setTimezone('Asia/Jakarta')->translatedFormat('d F Y H.i') }} WIB</p>
                                @elseif ($blokir)
                                    <p class="mb-2 text-xs text-neutral-600">Menunggu balasan Irban</p>
                                @endif
                            @endif
                            @include('livewire.spt.partials.komentar-item', ['k' => $root])
                            @if ($bisaTutup && $root->minta_penjelasan && ! $selesai && ! $blokir)
                                <x-ui.button type="button" variant="secondary" size="sm" icon="check-circle" class="mt-3 max-lg:h-11"
                                    wire:click="tutup({{ $root->id }})" wire:loading.attr="disabled" wire:target="tutup({{ $root->id }})"
                                    aria-label="Tutup permintaan penjelasan dari {{ $root->penulis->name }}">Tutup permintaan</x-ui.button>
                            @endif
                        </article>

                        @if ($root->balasan->isNotEmpty())
                            <ol role="list" class="mt-3 space-y-3 pl-3 sm:border-l-2 sm:border-neutral-200 sm:pl-4">
                                @foreach ($root->balasan as $b)
                                    <li wire:key="balasan-{{ $b->id }}">
                                        <article id="komentar-{{ $b->id }}" tabindex="-1" aria-labelledby="komentar-{{ $b->id }}-penulis">
                                            @include('livewire.spt.partials.komentar-item', ['k' => $b])
                                        </article>
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        @if ($bisaBalas)
                            @php $terbuka = $balasUntuk === $root->id; @endphp
                            <x-ui.button type="button" variant="ghost" size="sm" icon="chat-bubble-left" id="balas-{{ $root->id }}" class="mt-2 max-lg:h-11"
                                wire:click="{{ $terbuka ? 'batalBalas' : 'bukaBalas('.$root->id.')' }}"
                                aria-label="Balas komentar {{ $root->penulis->name }}" aria-expanded="{{ $terbuka ? 'true' : 'false' }}"
                                :aria-controls="$terbuka ? 'form-balas' : null">Balas</x-ui.button>
                            @if ($terbuka)
                                <form id="form-balas" wire:submit="kirimBalasan" class="mt-2 pl-3 sm:pl-4" novalidate>
                                    <x-ui.textarea label="Balasan" name="balasan" wire:model="balasan" rows="3" maxlength="2000" required :error="$errors->first('balasan')" />
                                    <p class="{{ $hitung }}" x-data x-text="`${$wire.balasan.length}/2000`">{{ mb_strlen($balasan) }}/2000</p>
                                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                        @if ($irban)
                                            <x-app.diinput-oleh id="balasan-diinput-oleh" :pegawai="$penginput" :value="$diinput_oleh" :ganti="$ganti" :error="$errors->first('diinput_oleh')"
                                                wire:model.live="diinput_oleh" class="min-w-0 sm:max-w-sm sm:flex-1" />
                                        @endif
                                        <div class="flex flex-col gap-2 sm:ml-auto sm:shrink-0 sm:flex-row">
                                            <x-ui.button type="button" variant="secondary" wire:click="batalBalas">Batal</x-ui.button>
                                            <x-ui.button icon="paper-airplane" wire:loading.attr="disabled" wire:target="kirimBalasan">Kirim balasan</x-ui.button>
                                        </div>
                                    </div>
                                </form>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($bisaKomentari)
            <form wire:submit="kirimKomentar" class="mt-6 border-t border-neutral-200 pt-5" novalidate>
                <x-ui.textarea label="Tulis komentar" name="isi" wire:model="isi" rows="3" maxlength="2000" required :error="$errors->first('isi')" />
                <p class="{{ $hitung }}" x-data x-text="`${$wire.isi.length}/2000`">{{ mb_strlen($isi) }}/2000</p>
                <div class="mt-2">
                    <x-ui.switch name="minta_penjelasan" label="Tandai sebagai Minta Penjelasan" wire:model="minta_penjelasan"
                        description="Irban wajib membalas. Permintaan tetap terbuka sampai ditutup pimpinan." />
                </div>
                <div class="mt-4 flex justify-end max-sm:[&>*]:w-full">
                    <x-ui.button icon="paper-airplane" wire:loading.attr="disabled" wire:target="kirimKomentar">Kirim komentar</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.card>
</section>
