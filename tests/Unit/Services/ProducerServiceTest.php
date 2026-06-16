<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\WorkerRepository;
use App\Services\ProducerService;
use Mockery;
use Tests\TestCase;

class ProducerServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_workerOptionsは作業者選択肢をリポジトリから返す(): void
    {
        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('options')
            ->once()
            ->andReturn([
                '' => '',
                1 => 'W001 : Tanaka',
                2 => 'W002 : Suzuki',
            ]);

        $service = new ProducerService($workerRepository);

        $this->assertSame([
            '' => '',
            1 => 'W001 : Tanaka',
            2 => 'W002 : Suzuki',
        ], $service->workerOptions());
    }
}
