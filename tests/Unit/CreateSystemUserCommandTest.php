<?php

namespace Tests\Unit;

use App\Console\Commands\CreateSystemUserCommand;
use App\Enums\RoleType;
use App\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Validation\Rules\Password;
use Mockery;
use Tests\TestCase;

class CreateSystemUserCommandTest extends TestCase
{
    private function makeCommand(UserService $userService): CreateSystemUserCommand
    {
        // Symfony Command の初期化を壊さずに argument() だけ差し替える。
        return new class($userService) extends CreateSystemUserCommand {
            /** @var array<string,string> */
            private array $arguments = [];

            public function setArguments(array $arguments): void
            {
                $this->arguments = $arguments;
            }

            public function argument($key = null)
            {
                if (is_null($key)) {
                    return $this->arguments;
                }

                return $this->arguments[$key] ?? null;
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_handleはユーザー作成成功時に成功コードを返す(): void
    {
        $userService = Mockery::mock(UserService::class);
        $userService
            ->shouldReceive('create')
            ->once()
            ->with(
                'system-user',
                'system@example.com',
                'password123',
                Mockery::on(fn($role) => $role->is(RoleType::SYSTEM()))
            )
            ->andReturn(true);

        $command = $this->makeCommand($userService);
        $command->setArguments([
            'name' => 'system-user',
            'email' => 'system@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame(Command::SUCCESS, $command->handle());
    }

    public function test_handleはユーザー作成失敗時に失敗コードを返す(): void
    {
        $userService = Mockery::mock(UserService::class);
        $userService
            ->shouldReceive('create')
            ->once()
            ->with(
                'system-user',
                'system@example.com',
                'password123',
                Mockery::on(fn($role) => $role->is(RoleType::SYSTEM()))
            )
            ->andReturn(false);

        $command = $this->makeCommand($userService);
        $command->setArguments([
            'name' => 'system-user',
            'email' => 'system@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame(Command::FAILURE, $command->handle());
    }

    public function test_rulesは期待されたバリデーション制約を定義する(): void
    {
        $userService = Mockery::mock(UserService::class);
        $command = $this->makeCommand($userService);

        // protected メソッドの仕様を固定するために Reflection で検証する。
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('rules');
        $method->setAccessible(true);
        $rules = $method->invoke($command);

        $this->assertIsArray($rules);
        $this->assertSame('required|string|min:1|max:255', $rules['name']);
        $this->assertSame('required|string|min:3|max:255|email|unique:users,email', $rules['email']);
        $this->assertIsArray($rules['password']);
        $this->assertSame('required', $rules['password'][0]);
        $this->assertSame('string', $rules['password'][1]);
        $this->assertInstanceOf(Password::class, $rules['password'][2]);
    }
}
