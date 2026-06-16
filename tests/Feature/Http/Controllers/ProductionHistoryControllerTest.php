<?php

namespace Tests\Feature\Http\Controllers;

use App\Exceptions\NoIndicatorException;
use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Models\User;
use App\Services\ProductionHistoryService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductionHistoryControllerTest extends TestCase
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
        $response = $this->get(route('production.index', $this->process));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーはindexを表示できる(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('histories')->once()->andReturn(new LengthAwarePaginator([], 0, 10));
            $mock->shouldReceive('productedPartNumberOptions')->once()->andReturn([]);
        });

        $response = $this->actingAs($this->normalUser)->get(route('production.index', $this->process));

        $response->assertStatus(200);
    }

    public function test_認証ユーザーはshowを表示できる(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('productionLines')->once()->andReturn(new EloquentCollection());
        });
        $history = ProductionHistory::factory()->create([
            'process_id' => $this->process->process_id,
            'process_name' => $this->process->process_name,
        ]);

        $response = $this->actingAs($this->normalUser)->get(route('production.show', [$this->process, $history]));

        $response->assertStatus(200);
    }

    public function test_認証ユーザーはcreateを表示できる(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('partNumberOptions')->once()->andReturn([]);
        });

        $response = $this->actingAs($this->normalUser)->get(route('production.create', $this->process));

        $response->assertStatus(200);
    }

    public function test_store成功時はsuccessトーストで遷移する(): void
    {
        $partNumber = PartNumber::factory()->create();
        CycleTime::factory()->create([
            'process_id' => $this->process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);

        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('switchPartNumberFromForm')->once()->andReturn(true);
        });

        $response = $this->actingAs($this->normalUser)->post(route('production.store', $this->process), [
            'part_number_id' => $partNumber->part_number_id,
            'goal' => 100,
        ]);

        $response->assertRedirect(route('process.show', ['process' => $this->process]));
        $response->assertSessionHas('toast_success');
    }

    public function test_storeでNoIndicatorException発生時はdangerトーストで遷移する(): void
    {
        $partNumber = PartNumber::factory()->create();
        CycleTime::factory()->create([
            'process_id' => $this->process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);

        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('switchPartNumberFromForm')->once()->andThrow(new NoIndicatorException('not found'));
        });

        $response = $this->actingAs($this->normalUser)->post(route('production.store', $this->process), [
            'part_number_id' => $partNumber->part_number_id,
            'goal' => 100,
        ]);

        $response->assertRedirect(route('process.show', ['process' => $this->process]));
        $response->assertSessionHas('toast_danger');
    }

    public function test_destroyは一般ユーザーが実行すると403になる(): void
    {
        $response = $this->actingAs($this->normalUser)->delete(route('production.destroy', $this->process), [
            'checkbox' => [],
            'date-range' => '2026-01-01 ~ 2026-01-02',
        ]);

        $response->assertStatus(403);
    }

    public function test_destroyで不正なdate_rangeでもエラーにならず遷移する(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroyHistories')->once()->andReturn(1);
        });

        $response = $this->actingAs($this->adminUser)->delete(route('production.destroy', $this->process), [
            'checkbox' => [],
            'date-range' => '',
            'part_number_name' => 'A-100',
        ]);

        $response->assertRedirect(route('production.index', ['process' => $this->process, 'partNumberName' => 'A-100']));
        $response->assertSessionHas('toast_success');
    }

    public function test_stop成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('stop')->once();
        });

        $response = $this->actingAs($this->normalUser)->put(route('production.stop', $this->process));

        $response->assertRedirect(route('process.show', ['process' => $this->process]));
        $response->assertSessionHas('toast_success');
    }

    public function test_startChangeover失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('changeover')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->normalUser)->put(route('production.start_changeover', $this->process));

        $response->assertRedirect(route('process.show', ['process' => $this->process]));
        $response->assertSessionHas('toast_danger');
    }

    public function test_stopChangeover例外時はdangerトーストで遷移する(): void
    {
        $this->mock(ProductionHistoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('changeover')->once()->andThrow(new \Exception('failed'));
        });

        $response = $this->actingAs($this->normalUser)->put(route('production.stop_changeover', $this->process));

        $response->assertRedirect(route('process.show', ['process' => $this->process]));
        $response->assertSessionHas('toast_danger');
    }
}
