<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\BarcodeHistory;
use App\Repositories\BarcodeHistoryRepository;
use App\Services\BarcodeHistoryService;
use Mockery;
use Tests\TestCase;

class BarcodeHistoryServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_storeはリポジトリ保存成功時にtrueを返す(): void
    {
        $repository = Mockery::mock(BarcodeHistoryRepository::class);
        $repository
            ->shouldReceive('storeBarcode')
            ->once()
            ->with('192.168.0.10', 'AA:BB:CC:DD:EE:FF', 'CODE-001')
            ->andReturn(new BarcodeHistory());

        $service = new BarcodeHistoryService($repository);

        $this->assertTrue($service->store('192.168.0.10', 'AA:BB:CC:DD:EE:FF', 'CODE-001'));
    }

    public function test_storeはリポジトリ保存失敗時にfalseを返す(): void
    {
        $repository = Mockery::mock(BarcodeHistoryRepository::class);
        $repository
            ->shouldReceive('storeBarcode')
            ->once()
            ->with('192.168.0.10', 'AA:BB:CC:DD:EE:FF', 'CODE-001')
            ->andReturn(null);

        $service = new BarcodeHistoryService($repository);

        $this->assertFalse($service->store('192.168.0.10', 'AA:BB:CC:DD:EE:FF', 'CODE-001'));
    }
}
