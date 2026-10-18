{{-- DESIGN §10 error summary: top of form, receives focus after a failed submit, links to each field. Expects $fields = [name => label]. --}}
@php $failed = collect($fields)->filter(fn ($label, $name) => $errors->has($name)); @endphp

@if ($failed->isNotEmpty())
    <x-ui.alert variant="danger" :title="'Periksa '.$failed->count().' isian berikut'" class="mt-6" tabindex="-1" data-error-focus x-init="$el.focus()">
        <ul class="mt-1 list-disc pl-5">
            @foreach ($failed as $name => $label)
                <li><a href="#{{ $name }}" class="text-mint-700 underline underline-offset-2">{{ $label }}: {{ $errors->first($name) }}</a></li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
