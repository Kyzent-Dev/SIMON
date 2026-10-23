<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the old Irban schedule revisions (pkpt_item_revisi) into the Perubahan PKPT model, then drops the table.
 * Query builder only, so later model changes cannot break it. Irreversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Snapshot = current item dates (revisions were stored apart, so these are still the original dates).
        //    Draf PKPT items get theirs at SahkanPkpt.
        DB::table('pkpt_item')
            ->whereIn('pkpt_id', DB::table('pkpt')->where('status', 'disahkan')->select('id'))
            ->update(['tanggal_mulai_disahkan' => DB::raw('tanggal_mulai'), 'tanggal_selesai_disahkan' => DB::raw('tanggal_selesai')]);

        if (! Schema::hasTable('pkpt_item_revisi')) {
            return;
        }

        DB::transaction(function () {
            $this->disetujui();
            $this->usulan();
        });

        Schema::dropIfExists('pkpt_item_revisi');
    }

    public function down(): void
    {
        throw new RuntimeException('Migrasi revisi jadwal tidak dapat dibalik.');
    }

    /** 2. Every approved revision becomes a historical Perubahan with one `ubah` operation; the last one sets the item dates. */
    private function disetujui(): void
    {
        $revisi = DB::table('pkpt_item_revisi')->where('status', 'disetujui')->orderBy('diputuskan_pada')->orderBy('id')->get();
        $nomor = [];

        foreach ($revisi as $r) {
            $item = DB::table('pkpt_item')->where('id', $r->pkpt_item_id)->first();
            $nomor[$item->pkpt_id] = ($nomor[$item->pkpt_id] ?? (int) DB::table('pkpt_perubahan')->where('pkpt_id', $item->pkpt_id)->max('nomor_urut')) + 1;

            $perubahanId = DB::table('pkpt_perubahan')->insertGetId([
                'pkpt_id' => $item->pkpt_id,
                'nomor_urut' => $nomor[$item->pkpt_id],
                'jenis' => 'perubahan',
                'alasan' => $r->alasan,
                'status' => 'disetujui',
                'sumber' => 'migrasi_revisi',
                'dibuat_oleh' => null,
                'diajukan_pada' => $r->created_at,
                'diputuskan_oleh' => $r->diputuskan_oleh,
                'diputuskan_pada' => $r->diputuskan_pada,
                'komentar_putusan' => $r->komentar,
                // Old revisions never had an SK: the reminder is switched off by the system (oleh = null).
                'pengingat_sk_dimatikan_oleh' => null,
                'pengingat_sk_dimatikan_pada' => $r->diputuskan_pada,
                'created_at' => $r->created_at,
                'updated_at' => $r->diputuskan_pada ?? $r->updated_at,
            ]);

            DB::table('pkpt_perubahan_item')->insert([
                'perubahan_id' => $perubahanId,
                'aksi' => 'ubah',
                'pkpt_item_id' => $item->id,
                'data_lama' => json_encode($this->data($item, $r->tanggal_mulai_semula, $r->tanggal_selesai_semula)),
                'data_baru' => json_encode($this->data($item, $r->tanggal_mulai, $r->tanggal_selesai)),
                'usulan_id' => null,
                'urutan' => 1,
                'created_at' => $r->created_at,
                'updated_at' => $r->diputuskan_pada ?? $r->updated_at,
            ]);

            DB::table('pkpt_perubahan_riwayat')->insert([
                'perubahan_id' => $perubahanId,
                'kejadian' => 'disetujui',
                'oleh' => $r->diputuskan_oleh,
                'catatan' => 'Dimigrasikan dari revisi jadwal #'.$r->id,
                'created_at' => $r->diputuskan_pada ?? $r->updated_at,
            ]);

            DB::table('pkpt_item')->where('id', $item->id)->update([
                'tanggal_mulai' => $this->tanggal($r->tanggal_mulai),
                'tanggal_selesai' => $this->tanggal($r->tanggal_selesai),
            ]);
        }
    }

    /** 3. Pending revisions become open proposals; rejected ones become closed proposals with the comment as note. */
    private function usulan(): void
    {
        $revisi = DB::table('pkpt_item_revisi')->whereIn('status', ['menunggu', 'ditolak'])->orderBy('id')->get();

        foreach ($revisi as $r) {
            $item = DB::table('pkpt_item')->where('id', $r->pkpt_item_id)->first();
            $ditolak = $r->status === 'ditolak';

            DB::table('pkpt_usulan')->insert([
                'pkpt_id' => $item->pkpt_id,
                'irban_id' => $item->irban_id,
                'pkpt_item_id' => $item->id,
                'aksi_usulan' => 'geser',
                'tanggal_mulai' => $this->tanggal($r->tanggal_mulai),
                'tanggal_selesai' => $this->tanggal($r->tanggal_selesai),
                'jenis_penugasan_id' => null,
                'alasan' => $r->alasan,
                'diajukan_oleh' => $r->diajukan_oleh,
                'diinput_oleh' => $r->diinput_oleh,
                'status' => $ditolak ? 'ditutup' : 'terbuka',
                'perubahan_id' => null,
                'ditutup_oleh' => $ditolak ? $r->diputuskan_oleh : null,
                'catatan_penutupan' => $ditolak ? $r->komentar : null,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ]);
        }
    }

    /** One json shape for every operation (E4). */
    private function data(object $item, string $mulai, string $selesai): array
    {
        return [
            'irban_id' => (int) $item->irban_id,
            'jenis_penugasan_id' => (int) $item->jenis_penugasan_id,
            'tanggal_mulai' => $this->tanggal($mulai),
            'tanggal_selesai' => $this->tanggal($selesai),
            'keterangan' => $item->keterangan,
            'target_spt' => $item->target_spt === null ? null : (int) $item->target_spt,
        ];
    }

    /** Date columns may come back as 'Y-m-d' or 'Y-m-d 00:00:00' depending on the driver. */
    private function tanggal(string $v): string
    {
        return substr($v, 0, 10);
    }
};
