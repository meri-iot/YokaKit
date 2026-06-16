<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Line;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use App\Models\Worker;
use App\Services\LineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LineControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;
    private Process $process;
    private RaspberryPi $raspberryPi;
    private Worker $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 5]);
        $this->normalUser = User::factory()->create();
        $this->process = Process::factory()->create();
        $this->raspberryPi = RaspberryPi::factory()->create();
        $this->worker = Worker::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_未認証ユーザーはcreateでログイン画面にリダイレクトされる(): void
    {
        $response = $this->get(route('line.create', $this->process));

        $response->assertRedirect(route('login'));
    }

    public function test_一般ユーザーはcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('line.create', $this->process));

        $response->assertStatus(403);
    }

    public function test_管理者はcreateにアクセスできる(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('line.create', $this->process));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはstoreを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->post(route('line.store', $this->process), $this->storePayload());

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(LineService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->post(route('line.store', $this->process), $this->storePayload());

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'line']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはupdateを実行できない(): void
    {
        $line = $this->createLine();

        $response = $this->actingAs($this->normalUser)->put(
            route('line.update', [$this->process, $line]),
            $this->updatePayload($line)
        );

        $response->assertStatus(403);
    }

    public function test_updateでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(LineService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(false);
        });
        $line = $this->createLine();

        $response = $this->actingAs($this->adminUser)->put(
            route('line.update', [$this->process, $line]),
            $this->updatePayload($line)
        );

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'line']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはdestroyを実行できない(): void
    {
        $line = $this->createLine();

        $response = $this->actingAs($this->normalUser)->delete(route('line.destroy', [$this->process, $line]));

        $response->assertStatus(403);
    }

    public function test_sortingは管理者のみアクセスできる(): void
    {
        $userResponse = $this->actingAs($this->normalUser)->get(route('line.sorting', $this->process));
        $adminResponse = $this->actingAs($this->adminUser)->get(route('line.sorting', $this->process));

        $userResponse->assertStatus(403);
        $adminResponse->assertStatus(200);
    }

    public function test_sortでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(LineService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sort')->once()->andReturnNull();
        });
        $line = $this->createLine();

        $response = $this->actingAs($this->adminUser)->post(route('line.sort', $this->process), [
            'order' => [$line->line_id],
        ]);

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'line']));
        $response->assertSessionHas('toast_success');
    }

    private function createLine(): Line
    {
        return Line::factory()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $this->raspberryPi->raspberry_pi_id,
            'worker_id' => $this->worker->worker_id,
            'line_name' => 'line-a',
            'chart_color' => '#123456',
            'pin_number' => 1,
            'defective' => false,
            'parent_id' => null,
        ]);
    }

    private function storePayload(): array
    {
        return [
            'line_name' => 'new-line',
            'chart_color' => '#abcdef',
            'raspberry_pi_id' => $this->raspberryPi->raspberry_pi_id,
            'worker_id' => $this->worker->worker_id,
            'pin_number' => 2,
        ];
    }

    private function updatePayload(Line $line): array
    {
        return [
            'line_name' => $line->line_name,
            'chart_color' => $line->chart_color,
            'raspberry_pi_id' => $line->raspberry_pi_id,
            'worker_id' => $line->worker_id,
            'pin_number' => $line->pin_number,
        ];
    }
}
