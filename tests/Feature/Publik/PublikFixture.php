<?php

namespace Tests\Feature\Publik;

use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Klasifikasi;
use App\Models\Objek;
use App\Models\Pegawai;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\Spt;
use App\Models\SptKendala;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Hand-computed fixture (`rekap.dasar_triwulan` = tanggal_selesai, today 15 Desember 2026, Jakarta).
 *
 * Irban A, B, C (C has nothing). Jenis J1 "Jenis Satu", J2 "Jenis Dua".
 * Objek: Kec X (kecamatan) ← Desa X1 (desa), Kec Y (kecamatan), Dinas D (dinas_badan).
 * PKPT 2026 Disahkan: a1 (A, J1, 5 Jan–31 Mar, target 2) → TW I; b1 (B, J2, 1 Apr–30 Jun, target null = 1) → TW II.
 * PKPT 2027 Draf: d1 (A, J1, Feb 2026, target 5).
 *
 * Visible (REGULER, Selesai, item in a Disahkan PKPT):
 *   v1 A a1 J1  3–20 Feb 2026        objek Desa X1          → Februari, TW I, wilayah "Kec X"
 *   v2 B b1 J2 28 Mei – 2 Jun 2026   objek Kec Y + Dinas D  → Juni, TW II, wilayah "Dinas D, Kec Y"
 * Excluded (all dated in 2026, so only the visibility rule keeps them out):
 *   e1 KHUSUS Selesai with a1 (10–12 Feb)
 *   e2 REGULER diterima, berjalan (10–20 Des), a1, objek Kec Y
 *   e3 REGULER Selesai without item (1–5 Mar)
 *   e4 REGULER Selesai then Dibatalkan, a1 (1–5 Mar)
 *   e5 REGULER Selesai with the Draf item d1 (1–5 Feb)
 *   e6 REGULER diterima, dates past (1–5 Mar) but not marked Selesai, a1
 * Every SPT carries private text "RAHASIA-…" and a factory number "FACTORY/n"; v1 has a pegawai and a kendala.
 */
trait PublikFixture
{
    protected Irban $a;

    protected Irban $b;

    protected Irban $c;

    protected JenisPenugasan $j1;

    protected JenisPenugasan $j2;

    protected Objek $kecX;

    protected Objek $desaX1;

    protected Objek $kecY;

    protected Objek $dinasD;

    protected PkptItem $a1;

    protected PkptItem $b1;

    protected PkptItem $d1;

    protected Spt $v1;

    protected Spt $v2;

    protected int $reguler;

    protected int $khusus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-12-15 12:00', 'Asia/Jakarta'));

        $this->a = Irban::factory()->create(['nama' => 'Irban A']);
        $this->b = Irban::factory()->create(['nama' => 'Irban B']);
        $this->c = Irban::factory()->create(['nama' => 'Irban C']);
        $this->j1 = JenisPenugasan::factory()->create(['nama' => 'Jenis Satu', 'warna' => '#3F6FA6', 'warna_teks' => '#FFFFFF']);
        $this->j2 = JenisPenugasan::factory()->create(['nama' => 'Jenis Dua', 'warna' => '#B54708', 'warna_teks' => '#FFFFFF']);
        $this->reguler = Klasifikasi::where('kode', 'REGULER')->value('id');
        $this->khusus = Klasifikasi::where('kode', 'KHUSUS')->value('id');

        $this->kecX = Objek::factory()->create(['nama' => 'Kec X', 'jenis' => 'kecamatan']);
        $this->desaX1 = Objek::factory()->create(['nama' => 'Desa X1', 'jenis' => 'desa', 'parent_id' => $this->kecX->id]);
        $this->kecY = Objek::factory()->create(['nama' => 'Kec Y', 'jenis' => 'kecamatan']);
        $this->dinasD = Objek::factory()->create(['nama' => 'Dinas D', 'jenis' => 'dinas_badan']);

        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $draf = Pkpt::factory()->create(['tahun' => 2027]);
        $item = fn (Pkpt $p, Irban $i, JenisPenugasan $j, string $m, string $s, ?int $t) => PkptItem::factory()->create([
            'pkpt_id' => $p->id, 'irban_id' => $i->id, 'jenis_penugasan_id' => $j->id, 'tanggal_mulai' => $m, 'tanggal_selesai' => $s,
            'target_spt' => $t, 'keterangan' => 'RAHASIA-KETERANGAN']);
        $this->a1 = $item($pkpt, $this->a, $this->j1, '2026-01-05', '2026-03-31', 2);
        $this->b1 = $item($pkpt, $this->b, $this->j2, '2026-04-01', '2026-06-30', null);
        $this->d1 = $item($draf, $this->a, $this->j1, '2026-02-01', '2026-02-28', 5);

        $this->v1 = $this->spt('selesai', $this->a, $this->a1, $this->reguler, '2026-02-03', '2026-02-20', [$this->desaX1]);
        $this->v2 = $this->spt('selesai', $this->b, $this->b1, $this->reguler, '2026-05-28', '2026-06-02', [$this->kecY, $this->dinasD]);
        $this->spt('selesai', $this->a, $this->a1, $this->khusus, '2026-02-10', '2026-02-12');
        $this->spt('diterima', $this->a, $this->a1, $this->reguler, '2026-12-10', '2026-12-20', [$this->kecY]);
        $this->spt('selesai', $this->a, null, $this->reguler, '2026-03-01', '2026-03-05');
        $this->spt('selesai', $this->a, $this->a1, $this->reguler, '2026-03-01', '2026-03-05')->forceFill(['status_pengajuan' => 'dibatalkan'])->save();
        $this->spt('selesai', $this->a, $this->d1, $this->reguler, '2026-02-01', '2026-02-05');
        $this->spt('diterima', $this->a, $this->a1, $this->reguler, '2026-03-01', '2026-03-05');

        $pegawai = Pegawai::factory()->create(['nama_bergelar' => 'RAHASIA-PEGAWAI', 'nip' => '199001012020011001']);
        DB::table('spt_personel')->insert(['spt_id' => $this->v1->id, 'pegawai_id' => $pegawai->id, 'peran' => 'ketua', 'urutan' => 1, 'sumber' => 'tambahan']);
        SptKendala::factory()->create(['spt_id' => $this->v1->id, 'uraian' => 'RAHASIA-KENDALA']);
    }

    /** @param list<Objek> $objek */
    protected function spt(string $state, Irban $irban, ?PkptItem $item, int $klasifikasi, string $mulai, string $selesai, array $objek = [], ?int $jenis = null): Spt
    {
        $spt = Spt::factory()->{$state}()->create([
            'irban_id' => $irban->id, 'pkpt_item_id' => $item?->id, 'jenis_penugasan_id' => $jenis ?? $item?->jenis_penugasan_id ?? $this->j1->id,
            'klasifikasi_id' => $klasifikasi, 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai,
            'periode_diperiksa' => 'RAHASIA-PERIODE', 'uraian_untuk' => 'RAHASIA-URAIAN', 'catatan_bagian_umum' => 'RAHASIA-CATATAN',
            'nomor_lhp' => 'RAHASIA-LHP',
        ]);
        foreach ($objek as $o) {
            DB::table('spt_objek')->insert(['spt_id' => $spt->id, 'objek_id' => $o->id]);
        }

        return $spt;
    }
}
