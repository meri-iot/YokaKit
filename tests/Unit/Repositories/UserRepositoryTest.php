<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\RoleType;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはUserクラスを返す(): void
    {
        $repository = new UserRepository();

        $this->assertSame(User::class, $repository->model());
    }

    public function test_createは数値権限でユーザーを保存する(): void
    {
        $repository = new UserRepository();

        $result = $repository->create('user-a', 'user-a@example.com', 'secret-123', RoleType::USER);

        $this->assertTrue($result);

        /** @var User $user */
        $user = User::query()->where('email', 'user-a@example.com')->firstOrFail();
        $this->assertSame('user-a', $user->name);
        $this->assertTrue($user->role->is(RoleType::USER()));
        $this->assertTrue(Hash::check('secret-123', $user->password));
    }

    public function test_createはRoleTypeインスタンスでもユーザーを保存する(): void
    {
        $repository = new UserRepository();

        $result = $repository->create('system-user', 'system@example.com', 'secret-456', RoleType::SYSTEM());

        $this->assertTrue($result);

        /** @var User $user */
        $user = User::query()->where('email', 'system@example.com')->firstOrFail();
        $this->assertTrue($user->role->is(RoleType::SYSTEM()));
    }

    public function test_destroyはユーザーを削除する(): void
    {
        $user = User::factory()->create();
        $repository = new UserRepository();

        $result = $repository->destroy($user);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }
}
