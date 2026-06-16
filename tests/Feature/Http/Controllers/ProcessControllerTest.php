<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Process;
use App\Models\User;
use App\Services\ProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;
    private Process $process;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 5]);
        $this->normalUser = User::factory()->create();
        $this->process = Process::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_未認証ユーザーはindexでログイン画面へリダイレクトされる(): void
    {
        $response = $this->get(route('process.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーはindexを表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('process.index'));

        $response->assertStatus(200);
    }

    public function test_認証ユーザーはshowを表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('process.show', $this->process));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('process.create'));

        $response->assertStatus(403);
    }

    public function test_管理者はcreateにアクセスできる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('process.create'));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはstoreを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->post(route('process.store'), $this->storePayload());

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(ProcessService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->post(route('process.store'), $this->storePayload());

        $response->assertRedirect(route('process.index'));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはupdateを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->put(route('process.update', $this->process), $this->updatePayload());

        $response->assertStatus(403);
    }

    public function test_updateでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(ProcessService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->put(route('process.update', $this->process), $this->updatePayload());

        $response->assertRedirect(route('process.show', $this->process));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはdestroyを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->delete(route('process.destroy', $this->process));

        $response->assertStatus(403);
    }

    public function test_destroyでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(ProcessService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->adminUser)->delete(route('process.destroy', $this->process));

        $response->assertRedirect(route('process.index'));
        $response->assertSessionHas('toast_success');
    }

    private function storePayload(): array
    {
        return [
            'process_name' => 'new-process',
            'plan_color' => '#ffffff',
            'count_switch' => '1',
            'range' => 60,
            'remark' => 'test remark',
        ];
    }

    private function updatePayload(): array
    {
        return [
            'process_name' => $this->process->process_name,
            'plan_color' => '#ffffff',
            'count_switch' => '1',
            'range' => 60,
            'remark' => $this->process->remark,
        ];
    }
}
