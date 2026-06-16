<?php

namespace Tests\Unit\Data;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Services\Utility;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PayloadDataTest extends TestCase
{
    private function createPayloadData(
        bool $countSwitch = true,
        array $changeovers = [],
        array $breakdowns = []
    ): PayloadData {
        $payload = new PayloadData(
            1,
            [10 => 2, 11 => 1],
            '2026-04-08 09:00:00.000000',
            $countSwitch,
            2000,
            4000,
            [
                ['startTime' => '12:00:00', 'endTime' => '13:00:00'],
            ],
            $changeovers,
            true,
        );

        $payload->count = 10;
        $payload->breakdowns = $breakdowns;

        return $payload;
    }

    public function test_カウント切替が有効なら不良品込みの合計数を返す()
    {
        $payload = $this->createPayloadData(true);

        $this->assertSame(13, $payload->totalCount());
        $this->assertSame(10, $payload->goodCount());
        $this->assertSame(3, $payload->defectiveCount());
    }

    public function test_カウント切替が無効なら生産数のみを合計として返す()
    {
        $payload = $this->createPayloadData(false);

        $this->assertSame(10, $payload->totalCount());
        $this->assertSame(7, $payload->goodCount());
        $this->assertSame(3, $payload->defectiveCount());
    }

    public function test_不良品数は存在するラインのみ更新する()
    {
        $payload = $this->createPayloadData();

        $payload->setDefectiveCount(10, 5);
        $payload->setDefectiveCount(999, 9);

        $this->assertSame(6, $payload->defectiveCount());
        $this->assertSame(10, $payload->goodCount());
    }

    public function test_addChangeover開始時は開放区間を追加し状態が段取り替えになる()
    {
        $payload = $this->createPayloadData();
        $start = Carbon::create(2026, 4, 8, 9, 10, 0);

        $payload->addChangeover($start, true);

        $this->assertTrue($payload->inPlannedOutage($start->copy()->setTime(12, 30, 0)));
        $this->assertSame(ProductionStatus::CHANGEOVER()->value, $payload->status()->value);
        $this->assertCount(1, $payload->changeovers);
        $this->assertNull($payload->changeovers[0]['to']);
    }

    public function test_addBreakdown終了のみは不正なため区間は追加されない()
    {
        $payload = $this->createPayloadData();

        $payload->addBreakdown(Carbon::create(2026, 4, 8, 9, 5, 0), false);

        $this->assertCount(0, $payload->breakdowns);
        $this->assertSame(ProductionStatus::RUNNING()->value, $payload->status()->value);
    }

    public function test_completeは完了状態へ遷移してジョブキーを空にする()
    {
        $payload = $this->createPayloadData();
        $payload->addChangeover(Carbon::create(2026, 4, 8, 9, 3, 0), true);

        $payload->complete(Carbon::create(2026, 4, 8, 9, 4, 0));

        $this->assertTrue($payload->isComplete);
        $this->assertSame('', $payload->jobKey);
        $this->assertSame(ProductionStatus::COMPLETE()->value, $payload->status()->value);
        $this->assertNotNull($payload->changeovers[0]['to']);
    }

    public function test_update後に計画値や達成率が計算できる()
    {
        $payload = $this->createPayloadData();
        $payload->update(Carbon::create(2026, 4, 8, 9, 0, 10));

        $this->assertSame(10000, $payload->workingTime);
        $this->assertSame(10000, $payload->operatingTime);
        $this->assertSame(5, $payload->planCount());
        $this->assertGreaterThan(0, $payload->achievementRate());
    }

    public function test_cycleTimeはチョコ停回数を考慮して0未満にならない()
    {
        $payload = $this->createPayloadData(
            true,
            [],
            [
                ['from' => Utility::format(Carbon::create(2026, 4, 8, 9, 0, 2)), 'to' => Utility::format(Carbon::create(2026, 4, 8, 9, 0, 4))],
                ['from' => Utility::format(Carbon::create(2026, 4, 8, 9, 0, 5)), 'to' => Utility::format(Carbon::create(2026, 4, 8, 9, 0, 6))],
            ]
        );
        $payload->autoResumeCount = 2;
        $payload->update(Carbon::create(2026, 4, 8, 9, 0, 10));

        $this->assertGreaterThanOrEqual(0, $payload->cycleTime());
    }
}
