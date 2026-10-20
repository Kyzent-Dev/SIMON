<?php

namespace Tests\Feature\Komentar;

use App\Actions\Komentar\BalasKomentar;
use App\Actions\Komentar\TulisKomentar;
use App\Actions\Komentar\TutupPermintaan;
use App\Enums\StatusPermintaan;
use App\Models\IrbanTahunan;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** PRD §14 butir 5 as one flow: real database channel and array mailer (sync queue), nothing faked. */
class AlurPenjelasanTest extends TestCase
{
    use RefreshDatabase;

    private function surel(): array
    {
        return collect(app('mailer')->getSymfonyTransport()->messages())
            ->map(fn ($m) => collect($m->getOriginalMessage()->getTo())->map->getAddress()->all())->all();
    }

    public function test_minta_penjelasan_flow_end_to_end(): void
    {
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 3, 25));
        $spt = Spt::factory()->diterima()->create(['nomor_lengkap' => '700/12/INSP/2026']);
        $pimpinan = User::factory()->pimpinan()->create();
        $irban = User::factory()->irban()->create();
        $irban->forceFill(['irban_id' => $spt->irban_id])->save();
        $penginput = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => $spt->irban_id, 'tahun' => 2026, 'ppj_pegawai_id' => $penginput->id]);

        $root = app(TulisKomentar::class)->handle($pimpinan, $spt, ['isi' => 'Mohon dijelaskan.', 'minta_penjelasan' => true]);
        $this->assertSame('minta_penjelasan', $irban->notifications()->sole()->data['jenis']);
        $this->assertSame([[$irban->email]], $this->surel());

        $balasan = app(BalasKomentar::class)->handle($irban, $spt, ['parent_id' => $root->id, 'isi' => 'Sudah kami lengkapi.', 'diinput_oleh' => $penginput->id]);
        $this->assertSame($penginput->id, $balasan->diinput_oleh);
        $this->assertSame('balasan_penjelasan', $pimpinan->notifications()->sole()->data['jenis']);

        $root = app(TutupPermintaan::class)->handle($pimpinan, $spt, ['komentar_id' => $root->id]);
        $this->assertSame(StatusPermintaan::Selesai, $root->fresh()->status_permintaan);
    }

    public function test_closing_with_a_reply_id_is_rejected(): void
    {
        $spt = Spt::factory()->diterima()->create();
        $pimpinan = User::factory()->pimpinan()->create();
        $root = app(TulisKomentar::class)->handle($pimpinan, $spt, ['isi' => 'Mohon dijelaskan.', 'minta_penjelasan' => true]);
        $balasan = $spt->komentar()->create(['parent_id' => $root->id, 'user_id' => $pimpinan->id, 'isi' => 'Ya.']);

        try {
            app(TutupPermintaan::class)->handle($pimpinan, $spt, ['komentar_id' => $balasan->id]);
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('Komentar ini bukan permintaan penjelasan.', $e->errors()['komentar'][0]);
        }
    }
}
