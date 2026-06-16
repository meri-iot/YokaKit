<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Http\Requests\StorePlannedOutageRequest;
use App\Http\Requests\UpdatePlannedOutageRequest;
use App\Models\PlannedOutage;
use App\Repositories\PlannedOutageRepository;
use App\Services\PlannedOutageService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Tests\TestCase;

class PlannedOutageServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_allはリポジトリ取得結果を返す(): void
    {
        $first = new PlannedOutage(['planned_outage_name' => 'break-a']);
        $first->planned_outage_id = 1;
        $second = new PlannedOutage(['planned_outage_name' => 'break-b']);
        $second->planned_outage_id = 2;

        $repository = Mockery::mock(PlannedOutageRepository::class);
        $repository
            ->shouldReceive('all')
            ->once()
            ->withNoArgs()
            ->andReturn(new EloquentCollection([$first, $second]));

        $service = new PlannedOutageService($repository);

        $actual = $service->all();

        $this->assertSame([1, 2], $actual->pluck('planned_outage_id')->all());
        $this->assertSame(['break-a', 'break-b'], $actual->pluck('planned_outage_name')->all());
    }

    public function test_storeはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(StorePlannedOutageRequest::class);

        $repository = Mockery::mock(PlannedOutageRepository::class);
        $repository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new PlannedOutageService($repository);

        $this->assertTrue($service->store($request));
    }

    public function test_updateはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(UpdatePlannedOutageRequest::class);
        $plannedOutage = new PlannedOutage(['planned_outage_name' => 'break-x']);
        $plannedOutage->planned_outage_id = 10;

        $repository = Mockery::mock(PlannedOutageRepository::class);
        $repository
            ->shouldReceive('update')
            ->once()
            ->with($request, $plannedOutage)
            ->andReturn(true);

        $service = new PlannedOutageService($repository);

        $this->assertTrue($service->update($request, $plannedOutage));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $plannedOutage = new PlannedOutage(['planned_outage_name' => 'break-del']);
        $plannedOutage->planned_outage_id = 11;

        $repository = Mockery::mock(PlannedOutageRepository::class);
        $repository
            ->shouldReceive('destroy')
            ->once()
            ->with($plannedOutage)
            ->andReturn(true);

        $service = new PlannedOutageService($repository);

        $this->assertTrue($service->destroy($plannedOutage));
    }
}
