<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\PlannedOutage;
use App\Models\Process;
use App\Models\ProcessPlannedOutage;
use App\Repositories\PlannedOutageRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PlannedOutageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_modelはPlannedOutageクラスを返す(): void
    {
        $repository = new PlannedOutageRepository();

        $this->assertSame(PlannedOutage::class, $repository->model());
    }

    public function test_exceptは指定した工程計画停止時間に紐づく計画停止時間を除外する(): void
    {
        $included = PlannedOutage::factory()->create(['planned_outage_name' => 'lunch']);
        $excluded = PlannedOutage::factory()->create(['planned_outage_name' => 'meeting']);
        $process = Process::factory()->create();
        $processPlannedOutage = $this->createProcessPlannedOutage($process, $excluded);
        $repository = new PlannedOutageRepository();

        $plannedOutages = $repository->except(new Collection([$processPlannedOutage]));

        $this->assertSame([$included->planned_outage_id], $plannedOutages->pluck('planned_outage_id')->all());
    }

    public function test_exceptは空コレクションなら全計画停止時間を返す(): void
    {
        $first = PlannedOutage::factory()->create(['planned_outage_name' => 'break-1']);
        $second = PlannedOutage::factory()->create(['planned_outage_name' => 'break-2']);
        $repository = new PlannedOutageRepository();

        $plannedOutages = $repository->except(new Collection());

        $this->assertEqualsCanonicalizing(
            [$first->planned_outage_id, $second->planned_outage_id],
            $plannedOutages->pluck('planned_outage_id')->all(),
        );
    }

    public function test_storeは計画停止時間を保存する(): void
    {
        $request = $this->mockRequest([
            'planned_outage_name' => 'morning-break',
            'start_time' => '10:00',
            'end_time' => '10:15',
        ]);
        $repository = new PlannedOutageRepository();

        $result = $repository->store($request);

        $this->assertTrue($result);
        $this->assertDatabaseHas('planned_outages', [
            'planned_outage_name' => 'morning-break',
            'start_time' => '10:00:00',
            'end_time' => '10:15:00',
        ]);
    }

    public function test_updateは既存の計画停止時間を更新する(): void
    {
        $plannedOutage = PlannedOutage::factory()->create([
            'planned_outage_name' => 'old-break',
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);
        $request = $this->mockRequest([
            'planned_outage_name' => 'new-break',
            'start_time' => '12:30',
            'end_time' => '13:30',
        ]);
        $repository = new PlannedOutageRepository();

        $result = $repository->update($request, $plannedOutage);

        $this->assertTrue($result);
        $updated = $plannedOutage->fresh();
        $this->assertSame('new-break', $updated?->planned_outage_name);
        $this->assertSame('12:30', $updated?->start_time->format('H:i'));
        $this->assertSame('13:30', $updated?->end_time->format('H:i'));
    }

    public function test_destroyは計画停止時間を削除する(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $repository = new PlannedOutageRepository();

        $result = $repository->destroy($plannedOutage);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('planned_outages', [
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
    }

    private function mockRequest(array $data): FormRequest
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('all')->once()->andReturn($data);

        return $request;
    }

    private function createProcessPlannedOutage(Process $process, PlannedOutage $plannedOutage): ProcessPlannedOutage
    {
        /** @var ProcessPlannedOutage */
        return ProcessPlannedOutage::query()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
    }
}
