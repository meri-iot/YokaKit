<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\PayloadData;
use App\Models\Payload;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\PayloadRepository;
use App\Services\Utility;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayloadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはPayloadクラスを返す(): void
    {
        $repository = new PayloadRepository();

        $this->assertSame(Payload::class, $repository->model());
    }

    public function test_getPayloadは通常ラインに紐づくペイロードを返す(): void
    {
        $line = $this->createProductionLine();
        $payload = $this->createPayload($line);
        $repository = new PayloadRepository();

        $found = $repository->getPayload($line);

        $this->assertSame($payload->payload_id, $found->payload_id);
    }

    public function test_getPayloadは不良品ラインなら親ラインのペイロードを返す(): void
    {
        $parentLine = $this->createProductionLine();
        $defectiveLine = $this->createProductionLine([
            'defective' => true,
            'parent_id' => $parentLine->production_line_id,
            'line_name' => 'defective-line',
            'ip_address' => '192.168.0.20',
            'pin_number' => 11,
            'order' => 1,
        ]);
        $payload = $this->createPayload($parentLine);
        $repository = new PayloadRepository();

        $found = $repository->getPayload($defectiveLine);

        $this->assertSame($payload->payload_id, $found->payload_id);
    }

    public function test_getPayloadは存在しない場合に例外を投げる(): void
    {
        $line = $this->createProductionLine();
        $repository = new PayloadRepository();

        $this->expectException(ModelNotFoundException::class);

        $repository->getPayload($line);
    }

    public function test_updatePayloadは未完了ならコールバック結果を保存する(): void
    {
        $line = $this->createProductionLine();
        $payload = $this->createPayload($line);
        $repository = new PayloadRepository();

        $payloadData = $repository->updatePayload($line, function (PayloadData $data): void {
            $data->count = 5;
        });

        $this->assertSame(5, $payloadData->count);
        $this->assertSame(5, $payload->fresh()->getPayloadData()->count);
    }

    public function test_updatePayloadは完了済みならコールバックを実行せず変更しない(): void
    {
        $line = $this->createProductionLine();
        $payload = $this->createPayload($line, true);
        $repository = new PayloadRepository();
        $called = false;

        $payloadData = $repository->updatePayload($payload, function (PayloadData $data) use (&$called): void {
            $called = true;
            $data->count = 99;
        });

        $this->assertFalse($called);
        $this->assertTrue($payloadData->isComplete);
        $this->assertSame(0, $payload->fresh()->getPayloadData()->count);
    }

    public function test_createは指標計算用データを新規作成する(): void
    {
        $line = $this->createProductionLine();
        $date = Carbon::create(2026, 4, 9, 12, 34, 56, config('app.timezone'))->microseconds(123000);
        $repository = new PayloadRepository();

        $payload = $repository->create(
            $line->production_line_id,
            [101, 102],
            $date,
            [['startTime' => '12:00:00', 'endTime' => '12:10:00']],
            true,
            true,
            60000,
            120000,
            true,
        );

        $this->assertNotNull($payload->payload_id);
        $payloadData = $payload->fresh()->getPayloadData();
        $this->assertSame($line->production_line_id, $payloadData->lineId);
        $this->assertSame(['101' => 0, '102' => 0], $payloadData->defectiveCounts);
        $this->assertSame(Utility::format($date), $payloadData->start);
        $this->assertTrue($payloadData->countSwitch);
        $this->assertSame(60000, $payloadData->cycleTimeMs);
        $this->assertSame(120000, $payloadData->overTimeMs);
        $this->assertTrue($payloadData->indicator);
        $this->assertCount(1, $payloadData->plannedOutages);
        $this->assertCount(1, $payloadData->changeovers);
        $this->assertSame(Utility::format($date), $payloadData->changeovers[0]['from']);
        $this->assertNull($payloadData->changeovers[0]['to']);
    }

    private function createProductionLine(array $overrides = []): ProductionLine
    {
        $history = ProductionHistory::factory()->create();

        /** @var ProductionLine */
        return ProductionLine::query()->create(array_merge([
            'production_history_id' => $history->production_history_id,
            'line_name' => 'main-line',
            'chart_color' => '#112233',
            'ip_address' => '192.168.0.10',
            'pin_number' => 10,
            'defective' => false,
            'order' => 0,
            'indicator' => true,
            'count' => 0,
        ], $overrides));
    }

    private function createPayload(ProductionLine $line, bool $isComplete = false): Payload
    {
        $payloadData = new PayloadData(
            $line->production_line_id,
            [],
            Utility::format(Carbon::create(2026, 4, 9, 10, 0, 0, config('app.timezone'))),
            false,
            60000,
            120000,
            [],
            [],
            true,
        );
        $payloadData->isComplete = $isComplete;

        /** @var Payload */
        return Payload::query()->create([
            'production_line_id' => $line->production_line_id,
            'payload' => $payloadData->toJson(),
        ]);
    }
}
