<?php

namespace Tests\Feature\Notifikasi;

use App\Actions\Komentar\BalasKomentar;
use App\Actions\Komentar\TulisKomentar;
use App\Models\IrbanTahunan;
use App\Models\JenisPenugasan;
use App\Models\Komentar;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\BalasanPenjelasan;
use App\Notifications\KomentarBaru;
use App\Support\JenisNotifikasi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Fase 7 Task 2: notifications sent by TulisKomentar and BalasKomentar. */
class NotifikasiKomentarTest extends TestCase
{
    use RefreshDatabase;

    private Spt $spt;

    private User $pimpinan;

    private User $pimpinanLain;

    private User $irban;

    private User $irbanLain;

    private User $bu;

    private Pegawai $penginput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 3, 25));
        $this->spt = Spt::factory()->diterima()->create(['nomor_lengkap' => '700/12/INSP/2026']);
        $this->pimpinan = User::factory()->pimpinan()->create();
        $this->pimpinanLain = User::factory()->pimpinan()->create();
        $this->irban = User::factory()->irban()->create();
        $this->irban->forceFill(['irban_id' => $this->spt->irban_id])->save();
        $this->irbanLain = User::factory()->irban()->create();
        $this->bu = User::factory()->bagianUmum()->create();
        $this->penginput = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => $this->spt->irban_id, 'tahun' => 2026, 'ppj_pegawai_id' => $this->penginput->id]);
        Notification::fake();
    }

    private function tulis(bool $minta = false, ?User $user = null): Komentar
    {
        return app(TulisKomentar::class)->handle($user ?? $this->pimpinan, $this->spt, ['isi' => 'Mohon dijelaskan.', 'minta_penjelasan' => $minta]);
    }

    private function balas(Komentar $root, User $user): Komentar
    {
        return app(BalasKomentar::class)->handle($user, $this->spt, ['parent_id' => $root->id, 'isi' => 'Baik.', 'diinput_oleh' => $this->penginput->id]);
    }

    /** @return list<int> Ids of every user who got a notification of this class. */
    private function penerima(string $kelas): array
    {
        return User::all()->filter(fn ($u) => Notification::sent($u, $kelas)->isNotEmpty())->pluck('id')->sort()->values()->all();
    }

    public function test_root_goes_to_irban_users_of_the_spt_only(): void
    {
        $this->tulis();

        $this->assertSame([$this->irban->id], $this->penerima(KomentarBaru::class));
        $this->assertSame([], $this->penerima(BalasanPenjelasan::class));
    }

    public function test_jenis_and_judul_of_root_and_minta_penjelasan(): void
    {
        $this->tulis();
        $this->tulis(true);

        $data = Notification::sent($this->irban, KomentarBaru::class)->map(fn ($n) => $n->toArray($this->irban))->values();
        $this->assertSame('komentar_baru', $data[0]['jenis']);
        $this->assertSame("Komentar baru dari {$this->pimpinan->name}", $data[0]['judul']);
        $this->assertSame('minta_penjelasan', $data[1]['jenis']);
        $this->assertSame("{$this->pimpinan->name} meminta penjelasan", $data[1]['judul']);
    }

    public function test_payload_konteks_url_and_catatan(): void
    {
        $root = app(TulisKomentar::class)->handle($this->pimpinan, $this->spt, ['isi' => str_repeat('a', 500), 'minta_penjelasan' => false]);

        $n = Notification::sent($this->irban, KomentarBaru::class)->first();
        $d = $n->toArray($this->irban);
        $this->assertSame('SPT 700/12/INSP/2026', $d['konteks']);
        $this->assertSame(route('spt.show', $this->spt, absolute: false).'#komentar-'.$root->id, $d['url']);
        $this->assertSame(str_repeat('a', 200).'...', $d['catatan']); // Str::limit(…, 200)
        $this->assertSame(["{$this->pimpinan->name} menulis di SPT 700/12/INSP/2026:"], $n->isi());
    }

    public function test_konteks_without_number(): void
    {
        $spt = Spt::factory()->menungguNomor()->create(['irban_id' => $this->spt->irban_id, 'jenis_penugasan_id' => JenisPenugasan::factory(), 'uraian_untuk' => str_repeat('x', 100)]);
        app(TulisKomentar::class)->handle($this->pimpinan, $spt, ['isi' => 'Halo', 'minta_penjelasan' => false]);

        $n = Notification::sent($this->irban, KomentarBaru::class)->first();
        $konteks = $n->toArray($this->irban)['konteks'];
        $this->assertStringStartsWith($spt->jenisPenugasan->nama.' · xxx', $konteks);
        $this->assertStringEndsWith('...', $konteks);
        $this->assertSame(["{$this->pimpinan->name} menulis di SPT tanpa nomor:"], $n->isi());

        // Neither number, jenis nor uraian: konteks is null (as the NotifikasiSintas contract says), not ''.
        $kosong = Spt::factory()->menungguNomor()->create(['irban_id' => $this->spt->irban_id, 'jenis_penugasan_id' => null, 'uraian_untuk' => '']);
        app(TulisKomentar::class)->handle($this->pimpinan, $kosong, ['isi' => 'Halo', 'minta_penjelasan' => false]);
        $n = Notification::sent($this->irban, KomentarBaru::class)->last();
        $this->assertNull($n->toArray($this->irban)['konteks']);
    }

    public function test_irban_reply_on_minta_penjelasan_notifies_peminta_with_balasan_only(): void
    {
        $root = $this->tulis(true);
        $this->balas($root, $this->bu);
        Notification::fake();

        $this->balas($root, $this->irban);

        Notification::assertSentTo($this->pimpinan, BalasanPenjelasan::class, function ($n) {
            $d = $n->toArray($this->pimpinan);

            return $d['jenis'] === 'balasan_penjelasan' && $d['judul'] === "Balasan atas permintaan penjelasan dari {$this->irban->name}";
        });
        Notification::assertNotSentTo($this->pimpinan, KomentarBaru::class);
        Notification::assertSentTo($this->bu, KomentarBaru::class, function ($n) {
            $d = $n->toArray($this->bu);

            return $d['judul'] === "Balasan komentar dari {$this->irban->name}" && $d['jenis'] === 'komentar_baru';
        });
        $this->assertSame([$this->pimpinan->id], $this->penerima(BalasanPenjelasan::class));
        $this->assertSame([$this->bu->id], $this->penerima(KomentarBaru::class));
    }

    public function test_bu_reply_on_normal_thread_goes_to_irban_and_earlier_authors(): void
    {
        $root = $this->tulis();
        Notification::fake();

        $this->balas($root, $this->bu);

        $this->assertSame(collect([$this->irban->id, $this->pimpinan->id])->sort()->values()->all(), $this->penerima(KomentarBaru::class));
        $this->assertSame([], $this->penerima(BalasanPenjelasan::class));
    }

    public function test_peminta_replying_in_own_thread_sends_komentar_baru_only(): void
    {
        $root = $this->tulis(true);
        Notification::fake();

        $this->balas($root, $this->pimpinan);

        $this->assertSame([$this->irban->id], $this->penerima(KomentarBaru::class));
        $this->assertSame([], $this->penerima(BalasanPenjelasan::class));
    }

    public function test_cta_is_buka_spt_for_the_three_jenis(): void
    {
        $root = $this->tulis(true);
        $this->balas($root, $this->irban);
        $this->tulis();

        $semua = Notification::sent($this->irban, KomentarBaru::class)->merge(Notification::sent($this->pimpinan, BalasanPenjelasan::class));
        $this->assertSame(['balasan_penjelasan', 'komentar_baru', 'minta_penjelasan'], $semua->map(fn ($n) => $n->jenis())->unique()->sort()->values()->all());
        foreach ($semua as $n) {
            $this->assertSame('Buka SPT', $n->toMail($this->irban)->actionText);
        }
    }

    public function test_nothing_sent_when_action_throws(): void
    {
        $root = $this->tulis();
        Notification::fake();

        foreach ([
            fn () => app(TulisKomentar::class)->handle($this->pimpinan, $this->spt, ['isi' => '  ']),
            fn () => app(TulisKomentar::class)->handle($this->irban, $this->spt, ['isi' => 'x']),
            fn () => app(BalasKomentar::class)->handle($this->bu, $this->spt, ['parent_id' => 999, 'isi' => 'x']),
            fn () => app(BalasKomentar::class)->handle($this->bu, $this->spt, ['parent_id' => $root->id, 'isi' => '']),
            fn () => app(BalasKomentar::class)->handle($this->irbanLain, $this->spt, ['parent_id' => $root->id, 'isi' => 'x']),
        ] as $fn) {
            try {
                $fn();
                $this->fail('exception expected');
            } catch (ValidationException|AuthorizationException) {
            }
        }
        Notification::assertNothingSent();
    }

    public function test_ikon_of_the_three_jenis(): void
    {
        $this->assertSame(['ikon' => 'chat-bubble-left-ellipsis', 'nada' => 'info'], JenisNotifikasi::ikon('komentar_baru'));
        $this->assertSame(['ikon' => 'question-mark-circle', 'nada' => 'warning'], JenisNotifikasi::ikon('minta_penjelasan'));
        $this->assertSame(['ikon' => 'chat-bubble-left-right', 'nada' => 'success'], JenisNotifikasi::ikon('balasan_penjelasan'));
    }

    public function test_catatan_is_plain_text_in_the_email(): void
    {
        $k = app(TulisKomentar::class)->handle($this->pimpinan, $this->spt, ['isi' => "Lihat [Reset sandi](https://e.test) - *tebal* (ok) https://b.test\nbaris dua"]);
        $mail = (new KomentarBaru($k))->toMail($this->irban);

        $html = (string) $mail->render();
        $this->assertStringNotContainsString('href="https://e.test"', $html);
        $this->assertStringNotContainsString('<strong>', $html);
        $this->assertStringNotContainsString('href="https://b.test"', $html);
        $this->assertStringContainsString('[Reset sandi](https://e.test) - *tebal* (ok) https://b.test baris dua', html_entity_decode(strip_tags($html)));

        $text = (string) app(Markdown::class)->renderText($mail->markdown, $mail->data());
        $this->assertStringContainsString('Reset sandi', $text);
        $this->assertStringNotContainsString('\\', $text); // no visible backslashes
    }

    public function test_literal_backslash_survives_in_text_part_and_html_stays_inert(): void
    {
        $k = app(TulisKomentar::class)->handle($this->pimpinan, $this->spt, ['isi' => 'a\b [x](https://e.test)']);
        $mail = (new KomentarBaru($k))->toMail($this->irban);

        $text = (string) app(Markdown::class)->renderText($mail->markdown, $mail->data());
        $this->assertStringContainsString('Catatan: a\b [x](https://e.test)', $text);

        $this->assertStringNotContainsString('href="https://e.test"', (string) $mail->render());
    }
}
