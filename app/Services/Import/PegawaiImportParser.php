<?php

namespace App\Services\Import;

use App\Actions\MasterData\SavePegawai;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PegawaiImportParser
{
    public const HEADERS = ['nama_bergelar', 'nip', 'pangkat', 'golongan', 'jabatan', 'email', 'aktif'];

    public const MAX_ROWS = 2000;

    private const AKTIF = ['1' => true, 'ya' => true, 'true' => true, '0' => false, 'tidak' => false, 'false' => false];

    /** @return list<array{baris:int,data:array,status:string,errors:list<array{kolom:string,pesan:string}>,warning:?string}> */
    public function parse(string $path): array
    {
        return $this->analyze($this->read($path));
    }

    /** Raw rows only: strings keyed by header plus the sheet row number `baris`. */
    public function read(string $path): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $grid = $sheet->toArray(null, false, false, false);

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($grid) ?? []);
        $missing = array_diff(['nama_bergelar', 'nip'], $header);
        if ($missing) {
            throw new InvalidArgumentException('Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Gunakan templat.');
        }

        $raw = [];
        foreach ($grid as $i => $cells) {
            $row = ['baris' => $i + 2];
            foreach (self::HEADERS as $h) {
                $col = array_search($h, $header, true);
                $v = $col === false ? null : ($cells[$col] ?? null);
                if ($h === 'nip' && (is_int($v) || is_float($v))) {
                    // Excel keeps only ~15 significant digits of a numeric cell, so the NIP cannot be trusted.
                    $row['nip_numerik'] = true;
                    $v = '';
                }
                $row[$h] = trim(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
            }
            if (implode('', array_map(fn ($h) => $row[$h], self::HEADERS)) === '' && ! isset($row['nip_numerik'])) {
                continue; // blank row
            }
            $raw[] = $row;
            if (count($raw) > self::MAX_ROWS) {
                throw new InvalidArgumentException(self::capMessage());
            }
        }

        return $raw;
    }

    public static function capMessage(): string
    {
        return 'Berkas terlalu besar: maksimal '.self::MAX_ROWS.' baris data per impor.';
    }

    public function analyze(array $raw): array
    {
        if (count($raw) > self::MAX_ROWS) {
            throw new InvalidArgumentException(self::capMessage());
        }
        // The raw rows can come from client-tampered Livewire state: normalize every row.
        $raw = array_map(function ($r) {
            $r = is_array($r) ? $r : [];
            $n = ['baris' => (int) ($r['baris'] ?? 0), 'nip_numerik' => ! empty($r['nip_numerik'])];
            foreach (self::HEADERS as $h) {
                $n[$h] = isset($r[$h]) && is_scalar($r[$h]) ? trim((string) $r[$h]) : '';
            }

            return $n;
        }, array_values($raw));
        $existing = Pegawai::whereIn('nip', array_column($raw, 'nip'))->pluck('nip')->flip();
        $rules = SavePegawai::rules();
        $rules['nip'] = ['required', 'digits:18']; // uniqueness is a warning here, not an error
        $rules['email'] = ['nullable', 'string', 'max:255', 'email:rfc']; // uniqueness is checked below against the row's NIP
        $emailDipakai = Pegawai::whereIn('email', array_filter(array_map(fn ($r) => mb_strtolower($r['email']), $raw)))->pluck('nip', 'email')->mapWithKeys(fn ($nip, $e) => [mb_strtolower($e) => $nip]);
        $seen = [];
        $seenEmail = [];
        $out = [];

        foreach ($raw as $r) {
            $errors = [];
            $aktif = self::AKTIF[strtolower($r['aktif'])] ?? ($r['aktif'] === '' ? true : null);
            $data = ['nama' => $r['nama_bergelar'], 'nip' => $r['nip'], 'pangkat' => $r['pangkat'], 'golongan' => $r['golongan'], 'jabatan' => $r['jabatan'], 'email' => mb_strtolower($r['email']), 'aktif' => $aktif ?? true];

            $v = Validator::make($data, $rules, SavePegawai::messages(), ['nama' => 'Nama bergelar', 'pangkat' => 'Pangkat', 'golongan' => 'Golongan', 'jabatan' => 'Jabatan']);
            foreach ($v->errors()->messages() as $field => $msgs) {
                $errors[] = ['kolom' => $field === 'nama' ? 'nama_bergelar' : $field, 'pesan' => $msgs[0]];
            }
            if ($data['email'] !== '' && ! $v->errors()->has('email')) {
                if (isset($seenEmail[$data['email']])) {
                    $errors[] = ['kolom' => 'email', 'pesan' => "Email ganda dengan baris {$seenEmail[$data['email']]}."];
                } else {
                    $seenEmail[$data['email']] = $r['baris'];
                    if (isset($emailDipakai[$data['email']]) && $emailDipakai[$data['email']] !== $r['nip']) {
                        $errors[] = ['kolom' => 'email', 'pesan' => SavePegawai::messages()['email.unique']];
                    }
                }
            }
            if ($aktif === null) {
                $errors[] = ['kolom' => 'aktif', 'pesan' => 'Isi aktif dengan ya/tidak, 1/0, atau true/false.'];
            }
            if ($r['nip_numerik']) {
                $errors = array_values(array_filter($errors, fn ($e) => $e['kolom'] !== 'nip'));
                $errors[] = ['kolom' => 'nip', 'pesan' => 'NIP tersimpan sebagai angka; ubah kolom NIP menjadi teks.'];
            } elseif (! $v->errors()->has('nip')) {
                if (isset($seen[$r['nip']])) {
                    $errors[] = ['kolom' => 'nip', 'pesan' => "NIP ganda dengan baris {$seen[$r['nip']]}."];
                } else {
                    $seen[$r['nip']] = $r['baris'];
                }
            }

            $warning = ! $errors && isset($existing[$r['nip']]) ? 'NIP sudah terdaftar, data akan diperbarui.' : null;
            $out[] = ['baris' => $r['baris'], 'data' => $data, 'errors' => $errors, 'warning' => $warning,
                'status' => $errors ? 'error' : ($warning ? 'warning' : 'valid')];
        }

        return $out;
    }
}
