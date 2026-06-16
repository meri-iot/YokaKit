<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AbstractController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class AbstractControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirectWithStoreは成功時にsuccessトーストを設定する(): void
    {
        $controller = new TestableAbstractController();

        $response = $controller->callRedirectWithStore(true, 'home');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('home'), $response->getTargetUrl());
        $this->assertNotNull($response->getSession()->get('toast_success'));
        $this->assertNull($response->getSession()->get('toast_danger'));
    }

    public function test_redirectWithUpdateは失敗時にdangerトーストを設定する(): void
    {
        $controller = new TestableAbstractController();

        $response = $controller->callRedirectWithUpdate(false, 'home');

        $this->assertSame(route('home'), $response->getTargetUrl());
        $this->assertNotNull($response->getSession()->get('toast_danger'));
        $this->assertNull($response->getSession()->get('toast_success'));
    }

    public function test_redirectWithDestroyはルートパラメータを引き継いで遷移する(): void
    {
        $controller = new TestableAbstractController();

        $response = $controller->callRedirectWithDestroy(true, 'process.show', ['process' => 123]);

        $this->assertSame(route('process.show', ['process' => 123]), $response->getTargetUrl());
        $this->assertNotNull($response->getSession()->get('toast_success'));
    }
}

class TestableAbstractController extends AbstractController
{
    public function name(): string
    {
        return 'テスト対象';
    }

    public function callRedirectWithStore(bool $result, string $path, array $parameters = []): RedirectResponse
    {
        return $this->redirectWithStore($result, $path, $parameters);
    }

    public function callRedirectWithUpdate(bool $result, string $path, array $parameters = []): RedirectResponse
    {
        return $this->redirectWithUpdate($result, $path, $parameters);
    }

    public function callRedirectWithDestroy(bool $result, string $path, array $parameters = []): RedirectResponse
    {
        return $this->redirectWithDestroy($result, $path, $parameters);
    }
}
