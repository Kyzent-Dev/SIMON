<?php

namespace App\Actions\MasterData;

use App\Models\Irban;
use App\Models\Pegawai;
use App\Models\Tim;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveTim
{
    public function __construct(private ValidateTimComposition $composition) {}

    /** @param list<array{pegawai_id: int|string, peran_default: string}> $members in display order; urutan becomes 1..n */
    public function handle(Irban $irban, int $tahun, ?Tim $tim, string $nama, array $members): Tim
    {
        $y = (int) now('Asia/Jakarta')->year;
        if ($tahun < $y - 2 || $tahun > $y + 1) {
            throw ValidationException::withMessages(['tahun' => 'Tahun tidak valid.']);
        }
        $nama = trim($nama);
        if ($nama === '' || mb_strlen($nama) > 255) {
            throw ValidationException::withMessages(['nama' => 'Nama tim wajib diisi (maksimal 255 karakter).']);
        }
        if (Tim::where(['irban_id' => $irban->id, 'tahun' => $tahun, 'nama' => $nama])->when($tim, fn ($q) => $q->where('id', '!=', $tim->id))->exists()) {
            throw ValidationException::withMessages(['nama' => 'Nama tim sudah dipakai pada Irban dan tahun ini.']);
        }

        $members = array_values(array_map(fn ($m) => ['pegawai_id' => (int) ($m['pegawai_id'] ?? 0), 'peran_default' => (string) ($m['peran_default'] ?? '')], $members));
        $this->composition->handle($members);

        // Active pegawai only; members already in this tim may stay even if deactivated since.
        $allowed = Pegawai::where('aktif', true)->pluck('id')->merge($tim ? $tim->anggota()->pluck('pegawai_id') : []);
        if (array_diff(array_column($members, 'pegawai_id'), $allowed->all())) {
            throw ValidationException::withMessages(['members' => 'Pilih pegawai aktif.']);
        }

        try {
            return DB::transaction(function () use ($irban, $tahun, $tim, $nama, $members) {
                $tim = $tim ?? new Tim(['irban_id' => $irban->id, 'tahun' => $tahun]);
                $tim->nama = $nama;
                $tim->save();
                $tim->anggota()->delete();
                foreach ($members as $i => $m) {
                    $tim->anggota()->create([...$m, 'urutan' => $i + 1]);
                }

                return $tim;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(str_contains(Str::before($e->getMessage(), 'SQL:'), 'tim_anggota')
                ? ['members' => 'Anggota tim berubah oleh pengguna lain. Muat ulang dan coba lagi.']
                : ['nama' => 'Nama tim sudah dipakai pada Irban dan tahun ini.']);
        }
    }
}
