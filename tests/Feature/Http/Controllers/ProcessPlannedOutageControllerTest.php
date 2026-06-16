<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\PlannedOutage;
use App\Models\Process;
use App\Models\ProcessPlannedOutage;
use App\Models\User;
use App\Services\ProcessPlannedOutageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessPlannedOutageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;
    private Process $process;
    private PlannedOutage $plannedOutage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 5]);
        $this->normalUser = User::factory()->create();
        $this->process = Process::factory()->create();
        $this->plannedOutage = PlannedOutage::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_未認証ユーザーはcreateでログイン画面へリダイレクトされる(): void
    {
        $response = $this->get(route('process.planned-outage.create', $this->process));

        $response->assertRedirect(route('login'));
    }

    public function test_一般ユーザーはcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('process.planned-outage.create', $this->process));

        $response->assertStatus(403);
    }

    public function test_管理者はcreateにアクセスできる(): void
    {
        $this->mock(ProcessPlannedOutageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('unusedPlannedOutageOptions')->once()->andReturn([]);
        });

        $response = $this->actingAs($this->adminUser)->get(route('process.planned-outage.create', $this->process));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはstoreを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->post(
            route('process.planned-outage.store', $this->process),
            $this->payload()
        );

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(ProcessPlannedOutageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->post(
            route('process.planned-outage.store', $this->process),
            $this->payload()
        );

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'planned-outage']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはdestroyを実行できない(): void
    {
        $processPlannedOutage = ProcessPlannedOutage::factory()->create([
            'process_id' => $this->process->process_id,
            'planned_outage_id' => $this->plannedOutage->planned_outage_id,
        ]);

        $response = $this->actingAs($this->normalUser)->delete(
            route('process.planned-outage.destroy', [$this->process, $processPlannedOutage])
        );

        $response->assertStatus(403);
    }

    public function test_destroyでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(ProcessPlannedOutageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->once()->andReturn(true);
        });
        $processPlannedOutage = ProcessPlannedOutage::factory()->create([
            'process_id' => $this->process->process_id,
            'planned_outage_id' => $this->plannedOutage->planned_outage_id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete(
            route('process.planned-outage.destroy', [$this->process, $processPlannedOutage])
        );

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'planned-outage']));
        $response->assertSessionHas('toast_success');
    }

    private function payload(): array
    {
        return [
            'planned_outage_id' => $this->plannedOutage->planned_outage_id,
        ];
    }
}
