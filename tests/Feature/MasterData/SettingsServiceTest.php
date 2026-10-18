<?php

namespace Tests\Feature\MasterData;

use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_returned_when_unset(): void
    {
        $s = app(Settings::class);
        $this->assertSame('{kode_arsip}/{nomor_urut}/{kode_irban}/SPT/{tahun}', $s->get('penomoran.format'));
        $this->assertSame('700', $s->get('penomoran.kode_arsip'));
        $this->assertSame(14, $s->get('penanda.batas_terlambat_hari'));
        $this->assertTrue($s->get('penomoran.reset_tahunan'));
        $this->assertSame('Lainnya', last($s->get('kendala.kategori')));
        $this->assertCount(14, Settings::DEFAULTS);
    }

    public function test_set_get_round_trip_clears_cache(): void
    {
        $s = app(Settings::class);
        $this->assertSame(10, $s->get('upload.maks_mb')); // warms the cache
        $s->set('upload.maks_mb', 25);
        $this->assertSame(25, $s->get('upload.maks_mb'));
        $this->assertSame(25, app(Settings::class)->get('upload.maks_mb'));
        $this->assertDatabaseHas('settings', ['key' => 'upload.maks_mb', 'value' => '25']);
    }

    public function test_unknown_keys_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(Settings::class)->set('nope.nope', 1);
    }

    public function test_unknown_key_get_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(Settings::class)->get('nope.nope');
    }

    public function test_settings_is_a_singleton(): void
    {
        $this->assertSame(app(Settings::class), app(Settings::class));
    }
}
