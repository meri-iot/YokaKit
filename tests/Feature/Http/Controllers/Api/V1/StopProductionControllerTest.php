<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Process;
use App\Services\ProductionHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class StopProductionControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_guestユーザーはng()
    {
        $process = Process::factory()->create();
        $this->createUser();
        $this->expect403();
        $this->postJson('/api/v1/stop-production', [
            'processName' => $process->process_name,
        ]);
    }

    public function test_adminユーザーはok()
    {
        $process = Process::factory()->create();
        $this->createAdmin();

        $service = Mockery::mock(ProductionHistoryService::class);
        $service->shouldReceive('stopFromApi')->once();
        $this->app->instance(ProductionHistoryService::class, $service);

        $response = $this->postJson('/api/v1/stop-production', [
            'processName' => $process->process_name,
        ]);

        $response->assertOk();
    }

    public function test_停止対象が存在しない場合はbad_request()
    {
        $process = Process::factory()->create();
        $this->createAdmin();

        $service = Mockery::mock(ProductionHistoryService::class);
        $service->shouldReceive('stopFromApi')->once()->andThrow(new ModelNotFoundException());
        $this->app->instance(ProductionHistoryService::class, $service);

        $response = $this->postJson('/api/v1/stop-production', [
            'processName' => $process->process_name,
        ]);

        $response->assertStatus(400);
    }

    public function test_バリデーションエラー時はbad_request()
    {
        $this->createAdmin();

        $response = $this->postJson('/api/v1/stop-production', []);

        $response->assertStatus(400);
        $response->assertJsonStructure(['errors' => ['processName']]);
    }
}
