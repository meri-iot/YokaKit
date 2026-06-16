<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Http\Requests\StoreProcessPlannedOutageRequest;
use App\Models\PlannedOutage;
use App\Models\ProcessPlannedOutage;
use App\Repositories\PlannedOutageRepository;
use App\Repositories\ProcessPlannedOutageRepository;
use App\Services\ProcessPlannedOutageService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Tests\TestCase;

class ProcessPlannedOutageServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_unusedPlannedOutageOptionsは未割り当て候補を整形して返す(): void
    {
        $processPlannedOutageRepository = Mockery::mock(ProcessPlannedOutageRepository::class);
        $processPlannedOutageRepository
            ->shouldReceive('get')
            ->once()
            ->with(['process_id' => 5], null, ['planned_outage_id'])
            ->andReturn(new EloquentCollection([
                new ProcessPlannedOutage(['planned_outage_id' => 1]),
            ]));

        $first = new PlannedOutage(['planned_outage_name' => 'break-a']);
        $first->planned_outage_id = 10;
        $first->start_time = '09:00:00';
        $first->end_time = '09:15:00';

        $second = new PlannedOutage(['planned_outage_name' => 'break-b']);
        $second->planned_outage_id = 11;
        $second->start_time = '12:00:00';
        $second->end_time = '12:45:00';

        $plannedOutageRepository = Mockery::mock(PlannedOutageRepository::class);
        $plannedOutageRepository
            ->shouldReceive('except')
            ->once()
            ->with(Mockery::type(EloquentCollection::class))
            ->andReturn(new EloquentCollection([$first, $second]));

        $service = new ProcessPlannedOutageService(
            $plannedOutageRepository,
            $processPlannedOutageRepository,
        );

        $this->assertSame([
            10 => 'break-a : 09:00 ~ 09:15',
            11 => 'break-b : 12:00 ~ 12:45',
        ], $service->unusedPlannedOutageOptions(5));
    }

    public function test_storeはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(StoreProcessPlannedOutageRequest::class);

        $processPlannedOutageRepository = Mockery::mock(ProcessPlannedOutageRepository::class);
        $processPlannedOutageRepository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new ProcessPlannedOutageService(
            Mockery::mock(PlannedOutageRepository::class),
            $processPlannedOutageRepository,
        );

        $this->assertTrue($service->store($request));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $processPlannedOutage = new ProcessPlannedOutage();
        $processPlannedOutage->process_planned_outage_id = 99;

        $processPlannedOutageRepository = Mockery::mock(ProcessPlannedOutageRepository::class);
        $processPlannedOutageRepository
            ->shouldReceive('destroy')
            ->once()
            ->with($processPlannedOutage)
            ->andReturn(true);

        $service = new ProcessPlannedOutageService(
            Mockery::mock(PlannedOutageRepository::class),
            $processPlannedOutageRepository,
        );

        $this->assertTrue($service->destroy($processPlannedOutage));
    }
}
