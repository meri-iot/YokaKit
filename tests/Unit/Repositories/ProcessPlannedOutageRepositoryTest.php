<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\PlannedOutage;
use App\Models\Process;
use App\Models\ProcessPlannedOutage;
use App\Repositories\ProcessPlannedOutageRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessPlannedOutageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_modelはProcessPlannedOutageクラスを返す(): void
    {
        $repository = new ProcessPlannedOutageRepository();

        $this->assertSame(ProcessPlannedOutage::class, $repository->model());
    }

    public function test_getは指定した工程IDに紐づく工程計画停止時間を返す(): void
    {
        $process = Process::factory()->create();
        $other = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $this->createProcessPlannedOutage($process, $plannedOutage);
        $this->createProcessPlannedOutage($other, $plannedOutage);
        $repository = new ProcessPlannedOutageRepository();

        $result = $repository->get(['process_id' => $process->process_id]);

        $this->assertCount(1, $result);
        $this->assertSame($process->process_id, $result->first()->process_id);
    }

    public function test_storeは工程計画停止時間を保存する(): void
    {
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $request = $this->mockRequest([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $repository = new ProcessPlannedOutageRepository();

        $result = $repository->store($request);

        $this->assertTrue($result);
        $this->assertDatabaseHas('process_planned_outages', [
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
    }

    public function test_destroyは工程計画停止時間を削除する(): void
    {
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $processPlannedOutage = $this->createProcessPlannedOutage($process, $plannedOutage);
        $repository = new ProcessPlannedOutageRepository();

        $result = $repository->destroy($processPlannedOutage);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('process_planned_outages', [
            'process_planned_outage_id' => $processPlannedOutage->process_planned_outage_id,
        ]);
    }

    private function createProcessPlannedOutage(Process $process, PlannedOutage $plannedOutage): ProcessPlannedOutage
    {
        /** @var ProcessPlannedOutage */
        return ProcessPlannedOutage::query()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
    }

    private function mockRequest(array $data): FormRequest
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('all')->once()->andReturn($data);

        return $request;
    }
}
