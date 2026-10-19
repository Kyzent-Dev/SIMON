<?php

namespace App\Actions\MasterData;

use App\Models\Objek;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteObjek
{
    public function handle(Objek $objek): void
    {
        if ($objek->children()->exists() || $objek->wilayah()->exists() || DB::table('spt_objek')->where('objek_id', $objek->id)->exists()) {
            throw ValidationException::withMessages(['delete' => 'Objek ini masih punya objek di bawahnya atau sudah dipakai pada wilayah Irban, sehingga tidak dapat dihapus.']);
        }
        $objek->delete();
    }
}
