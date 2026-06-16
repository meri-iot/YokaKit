<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Process;
use App\Models\ProductionHistory;
use App\Repositories\ProcessRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\FormRequest;
use Mockery;
use Tests\TestCase;

class ProcessRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_modelはProcessクラスを返す(): void
    {
        $repository = new ProcessRepository();

        $this->assertSame(Process::class, $repository->model());
    }

    public function test_startはproduction_history_idを設定する(): void
    {
        $history = ProductionHistory::factory()->create();
        $process = Process::factory()->create(['production_history_id' => null]);
        $repository = new ProcessRepository();

        $result = $repository->start($process, $history->production_history_id);

        $this->assertTrue($result);
        $this->assertSame($history->production_history_id, $process->fresh()->production_history_id);
    }

    public function test_stopはproduction_history_idをnullにする(): void
    {
        $history = ProductionHistory::factory()->create();
        $process = Process::factory()->create(['production_history_id' => $history->production_history_id]);
        $repository = new ProcessRepository();

        $result = $repository->stop($process);

        $this->assertTrue($result);
        $this->assertNull($process->fresh()->production_history_id);
    }

    public function test_ganttChartEventsは全工程を返す(): void
    {
        Process::factory()->create(['process_name' => '工程A']);
        Process::factory()->create(['process_name' => '工程B']);
        $repository = new ProcessRepository();

        // ガントチャートが存在しない場合でもクエリが正常に実行できることを確認する
        $result = $repository->ganttChartEvents([], []);

        $this->assertCount(2, $result);
        // 各工程に ganttCharts リレーションがロードされていること
        $result->each(function (Process $p) {
            $this->assertTrue($p->relationLoaded('ganttCharts'));
        });
    }

    public function test_ganttChartEventは指定した工程IDの工程を返す(): void
    {
        $process = Process::factory()->create(['process_name' => '対象工程']);
        $repository = new ProcessRepository();

        $result = $repository->ganttChartEvent($process->process_id, [], []);

        $this->assertSame($process->process_id, $result->process_id);
    }

    public function test_ganttChartEventは存在しない工程IDならModelNotFoundExceptionをスローする(): void
    {
        $repository = new ProcessRepository();

        $this->expectException(ModelNotFoundException::class);

        $repository->ganttChartEvent(9999, [], []);
    }

    public function test_storeは工程を保存する(): void
    {
        $request = $this->mockRequest([
            'process_name' => 'テスト工程',
            'plan_color' => '#FF0000',
            'remark' => '備考',
        ]);
        $repository = new ProcessRepository();

        $result = $repository->store($request);

        $this->assertTrue($result);
        $this->assertDatabaseHas('processes', [
            'process_name' => 'テスト工程',
            'plan_color' => '#FF0000',
        ]);
    }

    public function test_destroyは工程を削除する(): void
    {
        $process = Process::factory()->create();
        $repository = new ProcessRepository();

        $result = $repository->destroy($process);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('processes', [
            'process_id' => $process->process_id,
        ]);
    }

    private function mockRequest(array $data): FormRequest
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('all')->once()->andReturn($data);

        return $request;
    }
}
