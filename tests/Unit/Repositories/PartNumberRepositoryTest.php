<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Models\Process;
use App\Repositories\PartNumberRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PartNumberRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_modelはPartNumberクラスを返す(): void
    {
        $repository = new PartNumberRepository();

        $this->assertSame(PartNumber::class, $repository->model());
    }

    public function test_exceptは指定したサイクルタイムに紐づく品番を除外する(): void
    {
        $included = PartNumber::factory()->create(['part_number_name' => 'PN-INCLUDED']);
        $excluded = PartNumber::factory()->create(['part_number_name' => 'PN-EXCLUDED']);
        $process = Process::factory()->create();
        $cycleTime = $this->createCycleTime($process, $excluded);
        $repository = new PartNumberRepository();

        $partNumbers = $repository->except(new Collection([$cycleTime]));

        $this->assertSame([$included->part_number_id], $partNumbers->pluck('part_number_id')->all());
        $this->assertSame([$included->part_number_name], $partNumbers->pluck('part_number_name')->all());
    }

    public function test_exceptは空コレクションなら全品番を返す(): void
    {
        $first = PartNumber::factory()->create(['part_number_name' => 'PN-001']);
        $second = PartNumber::factory()->create(['part_number_name' => 'PN-002']);
        $repository = new PartNumberRepository();

        $partNumbers = $repository->except(new Collection());

        $this->assertEqualsCanonicalizing(
            [$first->part_number_id, $second->part_number_id],
            $partNumbers->pluck('part_number_id')->all(),
        );
    }

    public function test_storeは品番を保存する(): void
    {
        $request = $this->mockRequest([
            'part_number_name' => 'PN-STORE',
            'barcode' => '1234567890',
            'remark' => 'stored remark',
        ]);
        $repository = new PartNumberRepository();

        $result = $repository->store($request);

        $this->assertTrue($result);
        $this->assertDatabaseHas('part_numbers', [
            'part_number_name' => 'PN-STORE',
            'barcode' => '1234567890',
            'remark' => 'stored remark',
        ]);
    }

    public function test_updateは既存の品番を更新する(): void
    {
        $partNumber = PartNumber::factory()->create([
            'part_number_name' => 'PN-BEFORE',
            'barcode' => '111',
        ]);
        $request = $this->mockRequest([
            'part_number_name' => 'PN-AFTER',
            'barcode' => '222',
            'remark' => 'updated remark',
        ]);
        $repository = new PartNumberRepository();

        $result = $repository->update($request, $partNumber);

        $this->assertTrue($result);
        $updated = $partNumber->fresh();
        $this->assertSame('PN-AFTER', $updated?->part_number_name);
        $this->assertSame('222', $updated?->barcode);
        $this->assertSame('updated remark', $updated?->remark);
    }

    public function test_destroyは品番を削除する(): void
    {
        $partNumber = PartNumber::factory()->create();
        $repository = new PartNumberRepository();

        $result = $repository->destroy($partNumber);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('part_numbers', [
            'part_number_id' => $partNumber->part_number_id,
        ]);
    }

    private function mockRequest(array $data): FormRequest
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('all')->once()->andReturn($data);

        return $request;
    }

    private function createCycleTime(Process $process, PartNumber $partNumber): CycleTime
    {
        /** @var CycleTime */
        return CycleTime::query()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 60.0,
            'over_time' => 120.0,
        ]);
    }
}
