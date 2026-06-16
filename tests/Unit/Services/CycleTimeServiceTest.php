<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Http\Requests\StoreCycleTimeRequest;
use App\Http\Requests\UpdateCycleTimeRequest;
use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Repositories\CycleTimeRepository;
use App\Repositories\PartNumberRepository;
use App\Services\CycleTimeService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Tests\TestCase;

class CycleTimeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_unusedPartNumberOptionsは未登録品番をid付き選択肢へ整形する(): void
    {
        $cycleTimeRepository = Mockery::mock(CycleTimeRepository::class);
        $cycleTimeRepository
            ->shouldReceive('get')
            ->once()
            ->with(['process_id' => 10], null, ['part_number_id'])
            ->andReturn(new EloquentCollection([
                new CycleTime(['part_number_id' => 1]),
                new CycleTime(['part_number_id' => 2]),
            ]));

        $partNumberA = new PartNumber(['part_number_name' => 'PN-C']);
        $partNumberA->part_number_id = 3;
        $partNumberB = new PartNumber(['part_number_name' => 'PN-E']);
        $partNumberB->part_number_id = 5;
        $partNumbers = new EloquentCollection([$partNumberA, $partNumberB]);

        $partNumberRepository = Mockery::mock(PartNumberRepository::class);
        $partNumberRepository
            ->shouldReceive('except')
            ->once()
            ->with(Mockery::type(EloquentCollection::class))
            ->andReturn($partNumbers);

        $service = new CycleTimeService($cycleTimeRepository, $partNumberRepository);

        $this->assertSame([
            3 => 'PN-C',
            5 => 'PN-E',
        ], $service->unusedPartNumberOptions(10)->all());
    }

    public function test_storeはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(StoreCycleTimeRequest::class);
        $cycleTimeRepository = Mockery::mock(CycleTimeRepository::class);
        $cycleTimeRepository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new CycleTimeService($cycleTimeRepository, Mockery::mock(PartNumberRepository::class));

        $this->assertTrue($service->store($request));
    }

    public function test_updateはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(UpdateCycleTimeRequest::class);
        $cycleTime = new CycleTime();
        $cycleTime->cycle_time_id = 99;

        $cycleTimeRepository = Mockery::mock(CycleTimeRepository::class);
        $cycleTimeRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $cycleTime)
            ->andReturn(true);

        $service = new CycleTimeService($cycleTimeRepository, Mockery::mock(PartNumberRepository::class));

        $this->assertTrue($service->update($request, $cycleTime));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $cycleTime = new CycleTime();
        $cycleTime->cycle_time_id = 99;

        $cycleTimeRepository = Mockery::mock(CycleTimeRepository::class);
        $cycleTimeRepository
            ->shouldReceive('destroy')
            ->once()
            ->with($cycleTime)
            ->andReturn(true);

        $service = new CycleTimeService($cycleTimeRepository, Mockery::mock(PartNumberRepository::class));

        $this->assertTrue($service->destroy($cycleTime));
    }
}
