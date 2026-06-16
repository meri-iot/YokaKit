<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Models\Process;
use Illuminate\Auth\Access\AuthorizationException;
use Mockery;
use Tests\TestCase;

class BaseControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_authorizeAdminはadmin権限を検証する(): void
    {
        $controller = new TestableBaseController();

        $controller->callAuthorizeAdmin();

        $this->assertSame('admin', $controller->lastAbility);
    }

    public function test_authorizeSystemはsystem権限を検証する(): void
    {
        $controller = new TestableBaseController();

        $controller->callAuthorizeSystem();

        $this->assertSame('system', $controller->lastAbility);
    }

    public function test_throwExceptionIfRunningは稼働中なら例外を投げる(): void
    {
        $controller = new TestableBaseController();
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('isRunning')->once()->andReturn(true);

        $this->expectException(AuthorizationException::class);

        $controller->callThrowExceptionIfRunning($process);
    }

    public function test_throwExceptionIfRunningは停止中なら例外を投げない(): void
    {
        $controller = new TestableBaseController();
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('isRunning')->once()->andReturn(false);

        $controller->callThrowExceptionIfRunning($process);

        $this->assertTrue(true);
    }
}

class TestableBaseController extends BaseController
{
    public ?string $lastAbility = null;

    public function callAuthorizeAdmin(): void
    {
        $this->authorizeAdmin();
    }

    public function callAuthorizeSystem(): void
    {
        $this->authorizeSystem();
    }

    public function callThrowExceptionIfRunning(Process $process): void
    {
        $this->throwExceptionIfRunning($process);
    }

    public function authorize($ability, $arguments = []): void
    {
        $this->lastAbility = $ability;
    }
}
