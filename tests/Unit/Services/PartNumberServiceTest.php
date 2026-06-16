<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Http\Requests\StorePartNumberRequest;
use App\Http\Requests\UpdatePartNumberRequest;
use App\Models\PartNumber;
use App\Repositories\PartNumberRepository;
use App\Services\PartNumberService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Tests\TestCase;

class PartNumberServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_allはリポジトリ取得結果を返す(): void
    {
        $partNumberA = new PartNumber(['part_number_name' => 'PN-A']);
        $partNumberA->part_number_id = 1;
        $partNumberB = new PartNumber(['part_number_name' => 'PN-B']);
        $partNumberB->part_number_id = 2;
        $collection = new EloquentCollection([$partNumberA, $partNumberB]);

        $repository = Mockery::mock(PartNumberRepository::class);
        $repository
            ->shouldReceive('all')
            ->once()
            ->withNoArgs()
            ->andReturn($collection);

        $service = new PartNumberService($repository);

        $actual = $service->all();

        $this->assertSame([1, 2], $actual->pluck('part_number_id')->all());
        $this->assertSame(['PN-A', 'PN-B'], $actual->pluck('part_number_name')->all());
    }

    public function test_storeはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(StorePartNumberRequest::class);

        $repository = Mockery::mock(PartNumberRepository::class);
        $repository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new PartNumberService($repository);

        $this->assertTrue($service->store($request));
    }

    public function test_updateはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(UpdatePartNumberRequest::class);
        $partNumber = new PartNumber(['part_number_name' => 'PN-X']);
        $partNumber->part_number_id = 10;

        $repository = Mockery::mock(PartNumberRepository::class);
        $repository
            ->shouldReceive('update')
            ->once()
            ->with($request, $partNumber)
            ->andReturn(true);

        $service = new PartNumberService($repository);

        $this->assertTrue($service->update($request, $partNumber));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $partNumber = new PartNumber(['part_number_name' => 'PN-DEL']);
        $partNumber->part_number_id = 11;

        $repository = Mockery::mock(PartNumberRepository::class);
        $repository
            ->shouldReceive('destroy')
            ->once()
            ->with($partNumber)
            ->andReturn(true);

        $service = new PartNumberService($repository);

        $this->assertTrue($service->destroy($partNumber));
    }
}
