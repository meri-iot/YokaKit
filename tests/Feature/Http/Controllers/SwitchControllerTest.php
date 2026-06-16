<?php

namespace Tests\Feature\Http\Controllers;

use App\Exceptions\NoIndicatorException;
use App\Models\CycleTime;
use App\Models\Line;
use App\Models\PartNumber;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use App\Models\Worker;
use App\Services\ProductionHistoryService;
use App\Services\SwitchService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SwitchControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Process $process;
    private PartNumber $partNumber;
    private RaspberryPi $raspberryPi;
    private Worker $worker;
    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->process = Process::factory()->create();
        $this->partNumber = PartNumber::factory()->create();
        $this->raspberryPi = RaspberryPi::factory()->create();
        $this->worker = Worker::factory()->create();

        $this->line = Line::query()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $this->raspberryPi->raspberry_pi_id,
            'worker_id' => null,
            'parent_id' => null,
            'line_name' => 'line-switch',
            'chart_color' => '#111111',
            'pin_number' => 1,
            'defective' => false,
            'order' => 1,
        ]);

        CycleTime::factory()->create([
            'process_id' => $this->process->process_id,
            'part_number_id' => $this->partNumber->part_number_id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_未認証ユーザーはindexでログインへリダイレクトされる(): void
    {
        $response = $this->get(route('switch.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーはindexを表示できる(): void
    {
        $this->mock(SwitchService::class, function (MockInterface $mock) {
            $mock->shouldReceive('processes')->once()->andReturn(new EloquentCollection());
            $mock->shouldReceive('workers')->once()->andReturn(new EloquentCollection());
        });

        $response = $this->actingAs($this->user)->get(route('switch.index'));

        $response->assertStatus(200);
    }

    public function test_store成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('switchPartNumberFromForm')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->user)->post(route('switch.store', $this->process), [
            'part_number_id' => $this->partNumber->part_number_id,
            'goal' => 100,
        ]);

        $response->assertRedirect(route('switch.index', ['process' => $this->process]));
        $response->assertSessionHas('toast_success');
    }

    public function test_storeでNoIndicatorException発生時はdangerトーストで遷移する(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('switchPartNumberFromForm')->once()->andThrow(new NoIndicatorException('missing'));
        });

        $response = $this->actingAs($this->user)->post(route('switch.store', $this->process), [
            'part_number_id' => $this->partNumber->part_number_id,
            'goal' => 100,
        ]);

        $response->assertRedirect(route('switch.index', ['process' => $this->process]));
        $response->assertSessionHas('toast_danger');
    }

    public function test_stop成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('stop')->once();
        });

        $response = $this->actingAs($this->user)->put(route('switch.stop', $this->process));

        $response->assertRedirect(route('switch.index', ['process' => $this->process]));
        $response->assertSessionHas('toast_success');
    }

    public function test_startChangeover失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('changeover')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->user)->put(route('switch.start_changeover', $this->process));

        $response->assertRedirect(route('switch.index', ['process' => $this->process]));
        $response->assertSessionHas('toast_danger');
    }

    public function test_stopChangeover成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('changeover')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->user)->put(route('switch.stop_changeover', $this->process));

        $response->assertRedirect(route('switch.index', ['process' => $this->process]));
        $response->assertSessionHas('toast_success');
    }

    public function test_changeWorker成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(SwitchService::class, function (MockInterface $mock) {
            $mock->shouldReceive('updateLineWorker')->once();
        });

        $response = $this->actingAs($this->user)->put(route('switch.change_worker', $this->process), [
            'lines' => [
                $this->line->line_id => [
                    'line_id' => $this->line->line_id,
                    'raspberry_pi_id' => $this->raspberryPi->raspberry_pi_id,
                    'worker_id' => $this->worker->worker_id,
                ],
            ],
        ]);

        $response->assertRedirect(route('switch.index', ['process' => $this->process]));
        $response->assertSessionHas('toast_success');
    }
}
