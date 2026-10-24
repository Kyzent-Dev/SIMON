<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function irban()
    {
        return $this->belongsTo(Irban::class);
    }

    /**
     * Irban: the irban's primary address (fallback: own email) plus its tembusan (PRD §5); others: own email.
     *
     * @return list<string>|null null when there is no address, so the mail channel is skipped
     */
    public function routeNotificationForMail(): ?array
    {
        $alamat = $this->role === Role::Irban && $this->irban
            ? [$this->irban->email_notifikasi ?: $this->email, ...($this->irban->email_tembusan ?? [])]
            : [$this->email];

        $unik = collect($alamat)->filter()->unique(fn ($a) => mb_strtolower($a))->values()->all();

        return $unik ?: null;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }

    public function fotoUrl(): ?string
    {
        return $this->foto_profil ? Storage::disk('public')->url($this->foto_profil) : null;
    }

    public function latarUrl(): ?string
    {
        return $this->latar_profil ? Storage::disk('public')->url($this->latar_profil) : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'wajib_ganti_password' => 'boolean',
        ];
    }
}
