<?php

namespace App\Livewire\Admin;

use App\Actions\Pengguna\AturUlangKataSandi;
use App\Actions\Pengguna\SimpanPengguna;
use App\Enums\Role;
use App\Models\Irban;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Pengguna')]
class Pengguna extends Component
{
    use WithPagination;

    #[Url(except: 'semua')]
    public string $peran = 'semua';

    public bool $open = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $role = 'irban';

    public string $irban_id = '';

    public string $password = '';

    public bool $resetOpen = false;

    #[Locked]
    public ?int $resetId = null;

    public string $sementara = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedPeran(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('create', User::class);
        $this->resetValidation();
        $this->reset('editingId', 'name', 'username', 'email', 'irban_id', 'password');
        $this->role = Role::Irban->value;
        $this->open = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('viewAny', User::class);
        $u = User::findOrFail($id);
        Gate::authorize('update', $u);
        $this->resetValidation();
        $this->editingId = $u->id;
        $this->name = $u->name;
        $this->username = $u->username;
        $this->email = $u->email;
        $this->role = $u->role->value;
        $this->irban_id = (string) $u->irban_id;
        $this->password = '';
        $this->open = true;
    }

    public function save(SimpanPengguna $action): void
    {
        Gate::authorize('viewAny', User::class);
        $u = $this->editingId ? User::findOrFail($this->editingId) : null;
        $u ? Gate::authorize('update', $u) : Gate::authorize('create', User::class);
        $this->username = trim($this->username);
        $this->email = trim($this->email);
        $data = $this->validate(SimpanPengguna::rules($u, $this->role), SimpanPengguna::messages(), ['name' => 'Nama', 'email' => 'Email']);
        $u = $action->handle(auth()->user(), $u, $data);

        $this->reset('password');
        $this->open = false;
        $this->dispatch('toast', type: 'success', message: "Pengguna {$u->name} disimpan.");
    }

    public function confirmReset(int $id): void
    {
        Gate::authorize('viewAny', User::class);
        $u = User::findOrFail($id);
        Gate::authorize('update', $u);
        $this->resetValidation();
        $this->resetId = $u->id;
        $this->sementara = '';
        $this->resetOpen = true;
    }

    public function aturUlang(AturUlangKataSandi $action): void
    {
        Gate::authorize('viewAny', User::class);
        $u = User::findOrFail($this->resetId);
        Gate::authorize('update', $u);
        $this->resetValidation();
        $action->handle(auth()->user(), $u, $this->sementara);

        $this->resetOpen = false;
        $this->sementara = '';
        $this->dispatch('toast', type: 'success', message: "Kata sandi {$u->name} diatur ulang. Pengguna wajib menggantinya saat masuk berikutnya.");
    }

    public function render()
    {
        Gate::authorize('viewAny', User::class);
        $rows = User::query()->with('irban')
            ->when(Role::tryFrom($this->peran), fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')->paginate(25);

        return view('livewire.admin.pengguna', [
            'rows' => $rows,
            'roles' => collect(Role::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all(),
            'irbanOptions' => Irban::orderBy('nama')->pluck('nama', 'id')->all(),
            'resetting' => $this->resetId ? User::find($this->resetId) : null,
        ]);
    }
}
