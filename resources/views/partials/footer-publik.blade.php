{{-- Public footer (design/public/landing.md "Footer", DESIGN §1.6 motif, §3.4 pairs on mint-950, §6 accent-400 focus on dark). Shared by layouts/public and the
     Admin Kontak & Footer preview. Data: Settings group `kontak` ($kontak, read from Settings when not passed); an empty value omits its column.
     The map iframe is inserted only after "Tampilkan peta" (saves data); without JS the same link opens the map in a new tab. No animation. --}}
@php
    $kontak ??= app(\App\Services\Settings::class)->all()['kontak'];
    $h = $judulTag ?? 'h2';
    $telepon = trim((string) ($kontak['telepon'] ?? ''));
    $email = trim((string) ($kontak['email'] ?? ''));
    $ig = ltrim(trim((string) ($kontak['instagram'] ?? '')), '@');
    $alamat = trim((string) ($kontak['alamat'] ?? ''));
    $peta = trim((string) ($kontak['peta_url'] ?? ''));
    $peta = str_starts_with($peta, \App\Services\Settings::PETA_PREFIX) ? $peta : '';
    $link = 'rounded-sm text-mint-200 underline underline-offset-2 hover:text-white focus-visible:outline-accent-400';
@endphp

<footer class="relative border-t-2 border-accent-400 bg-mint-950 text-sm text-mint-200 {{ $class ?? '' }}">
    @if ($telepon !== '' || $email !== '' || $ig !== '' || $alamat !== '' || $peta !== '')
        <div class="mx-auto grid max-w-[1200px] gap-8 px-4 py-10 md:px-6 lg:grid-cols-4 lg:py-12">
            @if ($telepon !== '' || $email !== '')
                <section>
                    <{{ $h }} class="mb-3 text-base font-semibold text-white">Hubungi Kami</{{ $h }}>
                    <ul role="list" class="space-y-2">
                        @if ($telepon !== '')
                            <li class="flex items-start gap-2">{{ svg('heroicon-o-phone', 'mt-0.5 size-5 shrink-0', ['aria-hidden' => 'true']) }}<a href="tel:{{ preg_replace('/[^0-9+]/', '', $telepon) }}" class="{{ $link }} tabular-nums"><span class="sr-only">Telepon </span>{{ $telepon }}</a></li>
                        @endif
                        @if ($email !== '')
                            <li class="flex min-w-0 items-start gap-2">{{ svg('heroicon-o-envelope', 'mt-0.5 size-5 shrink-0', ['aria-hidden' => 'true']) }}<a href="mailto:{{ $email }}" class="{{ $link }} break-all"><span class="sr-only">Email </span>{{ $email }}</a></li>
                        @endif
                    </ul>
                </section>
            @endif
            @if ($ig !== '')
                <section>
                    <{{ $h }} class="mb-3 text-base font-semibold text-white">Instagram</{{ $h }}>
                    <a href="https://www.instagram.com/{{ rawurlencode($ig) }}" target="_blank" rel="noopener" class="{{ $link }} break-all">{{ '@'.$ig }}<span class="sr-only"> (tab baru)</span></a>
                </section>
            @endif
            @if ($alamat !== '')
                <section>
                    <{{ $h }} class="mb-3 text-base font-semibold text-white">Alamat Kantor</{{ $h }}>
                    <address class="whitespace-pre-line not-italic">{{ $alamat }}</address>
                </section>
            @endif
            @if ($peta !== '')
                <section x-data="{ peta: false }">
                    <{{ $h }} class="mb-3 text-base font-semibold text-white">Lokasi</{{ $h }}>
                    <a x-show="! peta" href="{{ $peta }}" target="_blank" rel="noopener" x-on:click.prevent="peta = true; $nextTick(() => $refs.peta?.focus())"
                        class="inline-flex h-11 items-center gap-2 rounded-md bg-mint-300 px-4 font-medium text-mint-950 hover:underline focus-visible:outline-accent-400">
                        {{ svg('heroicon-o-map-pin', 'size-5', ['aria-hidden' => 'true']) }}Tampilkan peta
                    </a>
                    <template x-if="peta">
                        <iframe x-ref="peta" src="{{ $peta }}" title="Peta lokasi kantor" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            class="aspect-video w-full rounded-md border-0 bg-mint-900"></iframe>
                    </template>
                </section>
            @endif
        </div>
    @endif

    {{-- "Delapan Sungai" motif in its own strip, never behind text. --}}
    <svg class="block h-8 w-full text-mint-200 opacity-15" viewBox="0 0 400 32" preserveAspectRatio="none" fill="none" aria-hidden="true" focusable="false">
        @foreach (range(1, 8) as $i)
            @php $y = $i * 3.6; @endphp
            <path d="M0 {{ $y }} C 50 {{ $y - 2 }}, 100 {{ $y + 2 }}, 150 {{ $y }} S 250 {{ $y - 2 }}, 300 {{ $y }} S 400 {{ $y + 2 }}, 450 {{ $y }}" stroke="currentColor" stroke-width="1" vector-effect="non-scaling-stroke" />
        @endforeach
    </svg>

    <div class="border-t border-mint-900">
        <p class="mx-auto max-w-[1200px] px-4 py-4 text-neutral-300 md:px-6">© {{ now('Asia/Jakarta')->year }} Inspektorat Daerah Kabupaten Muara Enim</p>
    </div>
</footer>
