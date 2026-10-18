<?php

namespace App\Models;

use Database\Factories\IrbanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'kode_nomor', 'email_notifikasi', 'email_tembusan'])]
class Irban extends Model
{
    /** @use HasFactory<IrbanFactory> */
    use HasFactory;

    protected $table = 'irban';

    protected function casts(): array
    {
        return ['email_tembusan' => 'array'];
    }

    public function tahunan()
    {
        return $this->hasMany(IrbanTahunan::class);
    }

    /** True when users or irban_tahunan point at this irban, so it cannot be deleted. */
    public function isReferenced(): bool
    {
        // shortcut: tim and irban_wilayah checks are added with Tasks 7/8 (those tables do not exist yet).
        return User::where('irban_id', $this->id)->exists() || $this->tahunan()->exists();
    }
}
