<?php

namespace App\Services\Import;

use App\Actions\Pkpt\SavePkptItem;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PkptItemImportParser
{
    public const HEADERS = ['irban', 'jenis_penugasan', 'tanggal_mulai', 'tanggal_selesai', 'keterangan', 'target_spt'];

    public const MAX_ROWS = 2000;

    /** Raw rows only: cell strings keyed by header plus the sheet row number `baris`. */
    public function read(string $path): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $grid = $reader->load($path)->getActiveSheet()->toArray(null, false, false, false);

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($grid) ?? []);
        $missing = array_diff(self::HEADERS, $header);
        if ($missing) {
            throw new InvalidArgumentException('Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Gunakan templat.');
        }

        $raw = [];
        foreach ($grid as $i => $cells) {
            $row = ['baris' => $i + 2];
            foreach (self::HEADERS as $h) {
                $v = $cells[array_search($h, $header, true)] ?? null;
                // A numeric cell stays a digit string; a date serial is recognised later by its shape.
                $row[$h] = trim(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v);
            }
            if (implode('', array_map(fn ($h) => $row[$h], self::HEADERS)) === '') {
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

    /** @return ?string Y-m-d, or null when the format is not recognised */
    private function tanggal(string $v): ?string
    {
        if (preg_match('/^\d{1,6}(\.\d+)?$/', $v)) { // Excel date serial
            $serial = (float) $v;

            return $serial >= 1 && $serial < 2958466 ? Date::excelToDateTimeObject(floor($serial))->format('Y-m-d') : null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            [, $d, $mo, $y] = $m;
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            [, $y, $mo, $d] = $m;
        } else {
            return null;
        }

        return checkdate((int) $mo, (int) $d, (int) $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /** @return list<array{baris:int,data:array,status:string,errors:list<array{kolom:string,pesan:string}>,warning:?string}> */
    public function analyze(Pkpt $pkpt, array $raw): array
    {
        if (count($raw) > self::MAX_ROWS) {
            throw new InvalidArgumentException(self::capMessage());
        }
        // The raw rows can come from client-tampered Livewire state: normalize every row.
        $raw = array_map(function ($r) {
            $r = is_array($r) ? $r : [];
            $n = ['baris' => (int) ($r['baris'] ?? 0)];
            foreach (self::HEADERS as $h) {
                $n[$h] = isset($r[$h]) && is_scalar($r[$h]) ? trim((string) $r[$h]) : '';
            }

            return $n;
        }, array_values($raw));

        $irbans = [];
        foreach (Irban::all(['id', 'nama', 'kode_nomor']) as $i) {
            $irbans[mb_strtolower(trim($i->nama))] = $i;
        }
        foreach (Irban::all(['id', 'nama', 'kode_nomor']) as $i) {
            $irbans[mb_strtolower(trim((string) $i->kode_nomor))] ??= $i;
        }
        $jenis = JenisPenugasan::all(['id', 'nama'])->keyBy(fn ($j) => mb_strtolower(trim($j->nama)));
        $seen = $pkpt->items()->aktif()->get(['irban_id', 'jenis_penugasan_id', 'tanggal_mulai', 'tanggal_selesai'])
            ->mapWithKeys(fn ($i) => [$i->irban_id.'|'.$i->jenis_penugasan_id.'|'.$i->tanggal_mulai->toDateString().'|'.$i->tanggal_selesai->toDateString() => true])->all();

        $out = [];
        foreach ($raw as $r) {
            $errors = [];
            $skip = []; // fields whose own message replaces SavePkptItem's
            $irban = $irbans[mb_strtolower($r['irban'])] ?? null;
            $jp = $jenis[mb_strtolower($r['jenis_penugasan'])] ?? null;
            if (! $irban) {
                $errors[] = ['kolom' => 'irban', 'pesan' => $r['irban'] === '' ? 'Irban wajib diisi.' : "Irban \"{$r['irban']}\" tidak ditemukan."];
                $skip[] = 'irban_id';
            }
            if (! $jp) {
                $errors[] = ['kolom' => 'jenis_penugasan', 'pesan' => $r['jenis_penugasan'] === '' ? 'Jenis penugasan wajib diisi.' : "Jenis penugasan \"{$r['jenis_penugasan']}\" tidak ditemukan."];
                $skip[] = 'jenis_penugasan_id';
            }
            $dates = [];
            foreach (['tanggal_mulai', 'tanggal_selesai'] as $f) {
                $dates[$f] = $r[$f] === '' ? null : $this->tanggal($r[$f]);
                if ($r[$f] !== '' && $dates[$f] === null) {
                    $errors[] = ['kolom' => $f, 'pesan' => 'Format tanggal tidak dikenali (gunakan dd/mm/yyyy).'];
                    $skip[] = $f;
                }
            }

            $data = ['irban_id' => $irban?->id, 'jenis_penugasan_id' => $jp?->id] + $dates
                + ['keterangan' => $r['keterangan'], 'target_spt' => $r['target_spt']];
            try {
                SavePkptItem::validated($pkpt, $data);
            } catch (ValidationException $e) {
                $map = ['irban_id' => 'irban', 'jenis_penugasan_id' => 'jenis_penugasan'];
                foreach ($e->errors() as $field => $msgs) {
                    if (! in_array($field, $skip, true)) {
                        $errors[] = ['kolom' => $map[$field] ?? $field, 'pesan' => $msgs[0]];
                    }
                }
            }

            $warning = null;
            if (! $errors) {
                $key = implode('|', [$irban->id, $jp->id, $dates['tanggal_mulai'], $dates['tanggal_selesai']]);
                $warning = isset($seen[$key]) ? 'Item yang sama sudah ada.' : null;
                $seen[$key] = true;
            }

            $out[] = ['baris' => $r['baris'], 'errors' => $errors, 'warning' => $warning,
                'status' => $errors ? 'error' : ($warning ? 'warning' : 'valid'),
                'data' => $data + ['irban' => $irban?->nama ?? $r['irban'], 'jenis' => $jp?->nama ?? $r['jenis_penugasan']]];
        }

        return $out;
    }
}
