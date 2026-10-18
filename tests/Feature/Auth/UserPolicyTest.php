<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_is_allowed(): void
    {
        $target = User::factory()->irban()->create();
        foreach (['admin' => true, 'pimpinan' => false, 'irban' => false, 'bagianUmum' => false] as $state => $allowed) {
            $user = User::factory()->{$state}()->create();
            foreach (['viewAny' => User::class, 'create' => User::class, 'view' => $target, 'update' => $target, 'delete' => $target] as $ability => $arg) {
                $this->assertSame($allowed, $user->can($ability, $arg), "$state $ability");
            }
        }
    }
}
