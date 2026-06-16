<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\RoleType;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_allはリポジトリへ委譲する(): void
    {
        $user1 = new User();
        $user1->user_id = 1;
        $user2 = new User();
        $user2->user_id = 2;

        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository
            ->shouldReceive('all')
            ->once()
            ->andReturn(new EloquentCollection([$user1, $user2]));

        $service = new UserService($userRepository);

        $actual = $service->all();

        $this->assertCount(2, $actual);
    }

    public function test_updateProfileは認証ユーザーでリポジトリ更新を行う(): void
    {
        $request = Mockery::mock(UpdateProfileRequest::class);

        $user = new User();
        $user->user_id = 10;

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $user)
            ->andReturn(true);

        $service = new UserService($userRepository);

        $this->assertTrue($service->updateProfile($request));
    }

    public function test_updateProfileは未認証時にAuthorizationExceptionを投げる(): void
    {
        $request = Mockery::mock(UpdateProfileRequest::class);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn(null);

        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository->shouldNotReceive('update');

        $service = new UserService($userRepository);

        $this->expectException(AuthorizationException::class);

        $service->updateProfile($request);
    }

    public function test_storeはリクエスト値をリポジトリcreateへ委譲する(): void
    {
        $request = Mockery::mock(StoreUserRequest::class);
        $request->name = 'new user';
        $request->email = 'new-user@example.com';
        $request->password = 'password123';
        $request->role = 5;

        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository
            ->shouldReceive('create')
            ->once()
            ->with('new user', 'new-user@example.com', 'password123', 5)
            ->andReturn(true);

        $service = new UserService($userRepository);

        $this->assertTrue($service->store($request));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $user = new User();
        $user->user_id = 11;

        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository
            ->shouldReceive('destroy')
            ->once()
            ->with($user)
            ->andReturn(true);

        $service = new UserService($userRepository);

        $this->assertTrue($service->destroy($user));
    }

    public function test_generateTokenは既存トークン削除後にBearerトークンを返す(): void
    {
        $tokenRelation = Mockery::mock();
        $tokenRelation
            ->shouldReceive('delete')
            ->once()
            ->andReturn(1);

        $user = Mockery::mock(User::class)->makePartial();
        $user
            ->shouldReceive('tokens')
            ->once()
            ->andReturn($tokenRelation);
        $user
            ->shouldReceive('createToken')
            ->once()
            ->with(config('app.name'))
            ->andReturn((object) ['plainTextToken' => 'plain-token-value']);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $service = new UserService(Mockery::mock(UserRepository::class));

        $this->assertSame('Bearer plain-token-value', $service->generateToken());
    }

    public function test_generateTokenは未認証時にAuthorizationExceptionを投げる(): void
    {
        Auth::shouldReceive('user')
            ->once()
            ->andReturn(null);

        $service = new UserService(Mockery::mock(UserRepository::class));

        $this->expectException(AuthorizationException::class);

        $service->generateToken();
    }

    public function test_updatePasswordはハッシュ化して保存する(): void
    {
        $request = Mockery::mock(UpdatePasswordRequest::class);
        $request->password = 'new-password-123';

        $user = Mockery::mock(User::class)->makePartial();
        $user
            ->shouldReceive('save')
            ->once()
            ->andReturn(true);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $service = new UserService(Mockery::mock(UserRepository::class));

        $result = $service->updatePassword($request);

        $this->assertTrue($result);
        $this->assertIsString($user->password);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_createは引数をリポジトリへ委譲する(): void
    {
        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository
            ->shouldReceive('create')
            ->once()
            ->with('system', 'system@example.com', 'password123', Mockery::on(fn($role) => $role instanceof RoleType && $role->is(RoleType::SYSTEM())))
            ->andReturn(true);

        $service = new UserService($userRepository);

        $this->assertTrue($service->create('system', 'system@example.com', 'password123', RoleType::SYSTEM()));
    }
}
