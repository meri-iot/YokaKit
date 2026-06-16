<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Http\Requests\StoreRaspberryPiRequest;
use App\Http\Requests\UpdateRaspberryPiRequest;
use App\Models\RaspberryPi;
use App\Repositories\RaspberryPiRepository;
use App\Services\RaspberryPiService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Tests\TestCase;

class RaspberryPiServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_allはリポジトリの一覧取得を委譲する(): void
    {
        $first = new RaspberryPi(['raspberry_pi_name' => 'rp-1', 'ip_address' => '192.168.0.10']);
        $first->raspberry_pi_id = 1;
        $second = new RaspberryPi(['raspberry_pi_name' => 'rp-2', 'ip_address' => '192.168.0.11']);
        $second->raspberry_pi_id = 2;

        $repository = Mockery::mock(RaspberryPiRepository::class);
        $repository
            ->shouldReceive('all')
            ->once()
            ->withNoArgs()
            ->andReturn(new EloquentCollection([$first, $second]));

        $service = new RaspberryPiService($repository);

        $actual = $service->all();

        $this->assertSame([1, 2], $actual->pluck('raspberry_pi_id')->all());
    }

    public function test_storeはリポジトリへ保存処理を委譲する(): void
    {
        $request = Mockery::mock(StoreRaspberryPiRequest::class);

        $repository = Mockery::mock(RaspberryPiRepository::class);
        $repository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new RaspberryPiService($repository);

        $this->assertTrue($service->store($request));
    }

    public function test_updateはリポジトリへ更新処理を委譲する(): void
    {
        $request = Mockery::mock(UpdateRaspberryPiRequest::class);
        $raspberryPi = new RaspberryPi(['raspberry_pi_name' => 'target', 'ip_address' => '192.168.0.20']);
        $raspberryPi->raspberry_pi_id = 20;

        $repository = Mockery::mock(RaspberryPiRepository::class);
        $repository
            ->shouldReceive('update')
            ->once()
            ->with($request, $raspberryPi)
            ->andReturn(true);

        $service = new RaspberryPiService($repository);

        $this->assertTrue($service->update($request, $raspberryPi));
    }

    public function test_destroyはリポジトリへ削除処理を委譲する(): void
    {
        $raspberryPi = new RaspberryPi(['raspberry_pi_name' => 'target', 'ip_address' => '192.168.0.21']);
        $raspberryPi->raspberry_pi_id = 21;

        $repository = Mockery::mock(RaspberryPiRepository::class);
        $repository
            ->shouldReceive('destroy')
            ->once()
            ->with($raspberryPi)
            ->andReturn(true);

        $service = new RaspberryPiService($repository);

        $this->assertTrue($service->destroy($raspberryPi));
    }

    public function test_updateCpuInfoはIPとCPU情報更新を委譲する(): void
    {
        $repository = Mockery::mock(RaspberryPiRepository::class);
        $repository
            ->shouldReceive('updateCpuInfo')
            ->once()
            ->with('192.168.0.30', 52.1, 84.3)
            ->andReturn(true);

        $service = new RaspberryPiService($repository);

        $this->assertTrue($service->updateCpuInfo('192.168.0.30', 52.1, 84.3));
    }
}
