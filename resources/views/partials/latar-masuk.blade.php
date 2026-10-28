{{-- design/auth/login.md §1: layers of the brand panel behind its content: photo (only when set) → scrim mint-950/80 → Delapan Sungai motif.
     $base = stored photo (`login/{uuid}`, '' = none); $src = a not-yet-saved upload's temporary URL (admin preview), wins over $base.
     Without either the panel is the plain mint-950 of its parent. Decorative. --}}
@php
    $base ??= '';
    $src ??= \App\Actions\Konten\AturLatarMasuk::url($base, 960);
@endphp
@if ($src)
    <img src="{{ $src }}" @if ($base !== '' && ! isset($pratinjau)) srcset="{{ $src }} 960w, {{ \App\Actions\Konten\AturLatarMasuk::url($base, 1920) }} 1920w" sizes="(min-width: 1024px) 42vw, 100vw" @endif alt="" class="absolute inset-0 size-full object-cover">
    <div class="absolute inset-0 bg-mint-950/80" aria-hidden="true"></div>
@endif
<svg class="pointer-events-none absolute inset-0 size-full text-mint-200 opacity-15" viewBox="0 0 400 400" preserveAspectRatio="none" fill="none" aria-hidden="true" focusable="false">
    @foreach (range(1, 8) as $i)
        @php $y = $i * 44; @endphp
        <path d="M0 {{ $y }} C 50 {{ $y - 18 }}, 100 {{ $y + 18 }}, 150 {{ $y }} S 250 {{ $y - 18 }}, 300 {{ $y }} S 400 {{ $y + 18 }}, 450 {{ $y }}" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" />
    @endforeach
</svg>
