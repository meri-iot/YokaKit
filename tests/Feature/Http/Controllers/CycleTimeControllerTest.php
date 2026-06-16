<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Requests\StoreCycleTimeRequest;
use App\Http\Requests\UpdateCycleTimeRequest;
use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Models\Process;
use App\Services\CycleTimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class CycleTimeControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_adminユーザーはサイクルタイム追加画面を表示できる(): void
    {
        $this->createAdmin();
        $process = Process::factory()->create();

        $service = Mockery::mock(CycleTimeService::class);
        $service->shouldReceive('unusedPartNumberOptions')
            ->once()
            ->with($process->process_id)
            ->andReturn(new Collection());
        $this->app->instance(CycleTimeService::class, $service);

        $response = $this->get(route('cycle-time.create', ['process' => $process]));

        $response->assertOk();
        $response->assertViewIs('process.cycle-time.create');
        $response->assertViewHasAll(['process', 'partNumbers']);
    }

    public function test_追加処理でサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();

        $service = Mockery::mock(CycleTimeService::class);
        $service->shouldReceive('store')
            ->once()
            ->with(Mockery::type(StoreCycleTimeRequest::class))
            ->andReturn(false);
        $this->app->instance(CycleTimeService::class, $service);

        $response = $this->post(route('cycle-time.store', ['process' => $process]), [
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 2,
            'over_time' => 3,
        ]);

        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'part-number']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_更新処理でサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);

        $service = Mockery::mock(CycleTimeService::class);
        $service->shouldReceive('update')
            ->once()
            ->with(Mockery::type(UpdateCycleTimeRequest::class), Mockery::on(fn($arg) => $arg->cycle_time_id === $cycleTime->cycle_time_id))
            ->andReturn(false);
        $this->app->instance(CycleTimeService::class, $service);

        $response = $this->put(route('cycle-time.update', ['process' => $process, 'cycleTime' => $cycleTime]), [
            'cycle_time' => 2,
            'over_time' => 3,
        ]);

        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'part-number']));
        $response->assertSessionHas('toast_danger');
    }
}
