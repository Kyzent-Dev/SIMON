<?php

namespace Tests\Feature;

use Tests\TestCase;

class FondasiConfigTest extends TestCase
{
    public function test_timezone_dan_locale_sesuai_standar_proyek(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('id', app()->getLocale());
    }
}
