<?php

namespace App\Actions\Spt;

use App\Enums\KejadianSpt;
use App\Enums\StatusPengajuan;
use App\Enums\SumberSpt;
use App\Models\Irban;
use App\Models\Klasifikasi;
use App\Models\Spt;
use App\Models\User;
use App\Services\Penomoran;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Bagian Umum records an urgent SPT outside the inbox flow. The number comes from the same locked sequence as accepted submissions
 * (PRD §7.7), never typed in. $data: irban_id, klasifikasi_id, jenis_penugasan_id?, uraian_untuk, tanggal_mulai, tanggal_selesai, alasan.
 */
class CatatNomorManual
{
    public function __construct(private Penomoran $penomoran) {}

    public function handle(User $user, array $data): Spt
    {
        Gate::forUser($user)->authorize('kelolaNomor', Spt::class);
        $d = Validator::make($data, [
            'irban_id' => ['required', 'exists:irban,id'],
            'klasifikasi_id' => ['required', 'exists:klasifikasi,id'],
            'jenis_penugasan_id' => ['nullable', 'exists:jenis_penugasan,id'],
            'uraian_untuk' => ['required', 'string', 'max:1000'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'irban_id.*' => 'Pilih Irban.', 'klasifikasi_id.*' => 'Pilih klasifikasi.', 'jenis_penugasan_id.*' => 'Jenis penugasan tidak valid.',
            'uraian_untuk.required' => 'Perihal wajib diisi.', 'uraian_untuk.max' => 'Perihal maksimal 1000 karakter.',
            'tanggal_mulai.*' => 'Tanggal mulai wajib diisi.', 'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.', 'tanggal_selesai.date_format' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'alasan.required' => 'Alasan wajib diisi.', 'alasan.min' => 'Alasan minimal 10 karakter.', 'alasan.max' => 'Alasan maksimal 1000 karakter.',
        ])->validate();

        return DB::transaction(function () use ($user, $d) {
            $irban = Irban::findOrFail($d['irban_id']);
            $tanggal = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
            $n = $this->penomoran->ambil($irban, $tanggal, Klasifikasi::find($d['klasifikasi_id']));
            $spt = new Spt(['irban_id' => $irban->id, 'klasifikasi_id' => $d['klasifikasi_id'], 'jenis_penugasan_id' => ($d['jenis_penugasan_id'] ?? null) ?: null,
                'uraian_untuk' => trim($d['uraian_untuk']), 'tanggal_mulai' => $d['tanggal_mulai'], 'tanggal_selesai' => $d['tanggal_selesai']]);
            $spt->forceFill([
                'status_pengajuan' => StatusPengajuan::Diterima, 'sumber' => SumberSpt::Manual, 'dibuat_oleh' => $user->id, 'diajukan_pada' => now(),
                'nomor_urut' => $n['nomor_urut'], 'nomor_lengkap' => $n['nomor_lengkap'], 'nomor_counter_id' => $n['counter']->id,
                'tahun' => $tanggal->year, 'tanggal_spt' => $tanggal->toDateString(),
            ])->save();
            $spt->riwayat()->create([
                'kejadian' => KejadianSpt::DICATAT_MANUAL, 'nilai_baru' => ['status_pengajuan' => StatusPengajuan::Diterima->value, 'nomor_lengkap' => $n['nomor_lengkap']],
                'alasan' => trim($d['alasan']), 'oleh' => $user->id,
            ]);

            return $spt;
        }, attempts: 3);
    }
}
