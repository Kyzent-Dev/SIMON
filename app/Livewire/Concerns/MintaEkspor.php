<?php

namespace App\Livewire\Concerns;

use App\Services\Ekspor;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Export request from a Livewire page: the rate-limit refusal (no form field) and the "queued" notice are shown as toasts. */
trait MintaEkspor
{
    protected function mintaEkspor(string $jenis, string $format, array $filter): ?Response
    {
        try {
            $unduhan = app(Ekspor::class)->minta(auth()->user(), $jenis, $format, $filter);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->validator->errors()->first('ekspor') ?: $e->getMessage());

            return null;
        }
        if ($unduhan === null) {
            $this->dispatch('toast', type: 'info', message: 'Ekspor sedang disiapkan. Anda akan menerima notifikasi saat file siap.');
        }

        return $unduhan;
    }
}
