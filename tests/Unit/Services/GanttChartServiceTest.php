<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\FromTo;
use App\Events\GanttChartNotification;
use App\Http\Requests\SortGanttChartRequest;
use App\Http\Requests\StoreGanttChartRequest;
use App\Http\Requests\UpdateGanttChartRequest;
use App\Models\GanttChart;
use App\Models\GanttChartEvent;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Repositories\GanttChartEventRepository;
use App\Repositories\GanttChartRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\RaspberryPiRepository;
use App\Services\GanttChartService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class GanttChartServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * 全リポジトリをモックでサービスを生成するヘルパー
     */
    private function makeService(
        ?GanttChartRepository $ganttChart = null,
        ?GanttChartEventRepository $ganttChartEvent = null,
        ?ProcessRepository $process = null,
        ?RaspberryPiRepository $raspberryPi = null,
    ): GanttChartService {
        return new GanttChartService(
            $process         ?? Mockery::mock(ProcessRepository::class),
            $ganttChart      ?? Mockery::mock(GanttChartRepository::class),
            $ganttChartEvent ?? Mockery::mock(GanttChartEventRepository::class),
            $raspberryPi     ?? Mockery::mock(RaspberryPiRepository::class),
        );
    }

    // ---------------------------------------------------------------------
    // raspberryPiOptions
    // ---------------------------------------------------------------------

    public function test_raspberryPiOptionsはリポジトリへ委譲する(): void
    {
        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('options')
            ->once()
            ->andReturn([1 => 'Pi1 : 192.168.1.1']);

        $service = $this->makeService(raspberryPi: $raspberryPiRepository);

        $this->assertSame([1 => 'Pi1 : 192.168.1.1'], $service->raspberryPiOptions());
    }

    // ---------------------------------------------------------------------
    // store
    // ---------------------------------------------------------------------

    public function test_storeは保存成功時にイベントを登録してtrueを返す(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 12:00:00'));

        $process = new Process();
        $process->process_id = 10;

        // 保存直後のガントチャートをリポジトリが返す想定
        $storedChart = Mockery::mock(GanttChart::class)->makePartial();
        $storedChart->gantt_chart_id = 99;

        $request = Mockery::mock(StoreGanttChartRequest::class);

        // store() 内で $request['chart_name'] (= offsetGet) が使われる
        $request->shouldReceive('offsetGet')->with('chart_name')->andReturn('TestChart');

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository->shouldReceive('store')->once()->with($request)->andReturn(true);
        // first() は $request['chart_name'] を条件に使うが、引数の検証はリポジトリ層のテストで行う
        $ganttChartRepository->shouldReceive('first')->once()->andReturn($storedChart);

        $ganttChartEventRepository = Mockery::mock(GanttChartEventRepository::class);
        $ganttChartEventRepository
            ->shouldReceive('storeEvent')
            ->once()
            ->with(10, 99, false, Mockery::type(Carbon::class))
            ->andReturn(new GanttChartEvent());

        $service = $this->makeService(
            ganttChart: $ganttChartRepository,
            ganttChartEvent: $ganttChartEventRepository,
        );

        $this->assertTrue($service->store($request, $process));
    }

    public function test_storeは保存失敗時にイベントを登録せずfalseを返す(): void
    {
        $process = new Process();
        $process->process_id = 10;

        $request = Mockery::mock(StoreGanttChartRequest::class);

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository->shouldReceive('store')->once()->with($request)->andReturn(false);
        $ganttChartRepository->shouldNotReceive('first');

        $ganttChartEventRepository = Mockery::mock(GanttChartEventRepository::class);
        $ganttChartEventRepository->shouldNotReceive('storeEvent');

        $service = $this->makeService(
            ganttChart: $ganttChartRepository,
            ganttChartEvent: $ganttChartEventRepository,
        );

        $this->assertFalse($service->store($request, $process));
    }

    // ---------------------------------------------------------------------
    // update / destroy / sort
    // ---------------------------------------------------------------------

    public function test_updateはリポジトリへ委譲する(): void
    {
        $request    = Mockery::mock(UpdateGanttChartRequest::class);
        $ganttChart = new GanttChart();
        $ganttChart->gantt_chart_id = 1;

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $ganttChart)
            ->andReturn(true);

        $this->assertTrue($this->makeService(ganttChart: $ganttChartRepository)->update($request, $ganttChart));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $ganttChart = new GanttChart();
        $ganttChart->gantt_chart_id = 1;

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository
            ->shouldReceive('destroy')
            ->once()
            ->with($ganttChart)
            ->andReturn(true);

        $this->assertTrue($this->makeService(ganttChart: $ganttChartRepository)->destroy($ganttChart));
    }

    public function test_sortはリポジトリへ委譲する(): void
    {
        $process = new Process();
        $process->process_id = 5;

        $request        = Mockery::mock(SortGanttChartRequest::class);
        $request->order = [3, 1, 2];

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository
            ->shouldReceive('sort')
            ->once()
            ->with(5, [3, 1, 2]);

        $this->makeService(ganttChart: $ganttChartRepository)->sort($request, $process);
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------------
    // insert
    // ---------------------------------------------------------------------

    public function test_insertでラズパイが見つからない場合は早期リターンする(): void
    {
        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldReceive('first')->once()->andReturn(null);

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository->shouldNotReceive('get');

        $service = $this->makeService(
            ganttChart: $ganttChartRepository,
            raspberryPi: $raspberryPiRepository,
        );

        $service->insert(1, '192.168.0.99', true);
        $this->assertTrue(true);
    }

    public function test_insertでガントチャートが見つからない場合は早期リターンする(): void
    {
        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 1;

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldReceive('first')->once()->andReturn($raspi);

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository->shouldReceive('get')->once()->andReturn(new EloquentCollection());

        $ganttChartEventRepository = Mockery::mock(GanttChartEventRepository::class);
        $ganttChartEventRepository->shouldNotReceive('storeEvent');

        $service = $this->makeService(
            ganttChart: $ganttChartRepository,
            ganttChartEvent: $ganttChartEventRepository,
            raspberryPi: $raspberryPiRepository,
        );

        $service->insert(7, '192.168.0.1', true);
        $this->assertTrue(true);
    }

    public function test_insertで前回がnullかつtriggerと一致する場合はイベントを作成して通知する(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 10:00:00'));
        Event::fake([GanttChartNotification::class]);

        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 2;

        // previous = null, trigger = true, signal = true → トリガー成立 → イベント作成
        $ganttChart = Mockery::mock(GanttChart::class)->makePartial();
        $ganttChart->shouldReceive('save')->once()->andReturn(true);
        $ganttChart->process_id      = 10;
        $ganttChart->gantt_chart_id  = 5;
        $ganttChart->signal          = null;  // 初期状態 (previous = null)
        $ganttChart->trigger         = true;

        $storedEvent = new GanttChartEvent([
            'process_id'      => 10,
            'gantt_chart_id'  => 5,
            'signal'          => true,
        ]);

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldReceive('first')->andReturn($raspi);

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository->shouldReceive('get')->andReturn(new EloquentCollection([$ganttChart]));
        $ganttChartRepository->shouldReceive('getWithLockByIds')->andReturn(new EloquentCollection([$ganttChart]));

        $ganttChartEventRepository = Mockery::mock(GanttChartEventRepository::class);
        $ganttChartEventRepository
            ->shouldReceive('storeEvent')
            ->once()
            ->with(10, 5, true, Mockery::type(Carbon::class))
            ->andReturn($storedEvent);

        $service = $this->makeService(
            ganttChart: $ganttChartRepository,
            ganttChartEvent: $ganttChartEventRepository,
            raspberryPi: $raspberryPiRepository,
        );

        $service->insert(1, '192.168.0.1', true);

        Event::assertDispatched(GanttChartNotification::class);
    }

    public function test_insertでイベント保存がnullなら通知しない(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 10:00:00'));
        Event::fake([GanttChartNotification::class]);

        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 2;

        $ganttChart = Mockery::mock(GanttChart::class)->makePartial();
        $ganttChart->shouldReceive('save')->once()->andReturn(true);
        $ganttChart->process_id = 10;
        $ganttChart->gantt_chart_id = 5;
        $ganttChart->signal = null;
        $ganttChart->trigger = true;

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldReceive('first')->andReturn($raspi);

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository->shouldReceive('get')->andReturn(new EloquentCollection([$ganttChart]));
        $ganttChartRepository->shouldReceive('getWithLockByIds')->andReturn(new EloquentCollection([$ganttChart]));

        $ganttChartEventRepository = Mockery::mock(GanttChartEventRepository::class);
        $ganttChartEventRepository
            ->shouldReceive('storeEvent')
            ->once()
            ->with(10, 5, true, Mockery::type(Carbon::class))
            ->andReturn(null);

        $service = $this->makeService(
            ganttChart: $ganttChartRepository,
            ganttChartEvent: $ganttChartEventRepository,
            raspberryPi: $raspberryPiRepository,
        );

        $service->insert(1, '192.168.0.1', true);

        Event::assertNotDispatched(GanttChartNotification::class);
    }

    public function test_insertで信号が前回から変化した場合はイベントを作成して通知する(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 10:00:00'));
        Event::fake([GanttChartNotification::class]);

        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 2;

        // previous = false, signal = true → 変化 → イベント作成
        $ganttChart = Mockery::mock(GanttChart::class)->makePartial();
        $ganttChart->shouldReceive('save')->once()->andReturn(true);
        $ganttChart->process_id     = 10;
        $ganttChart->gantt_chart_id = 6;
        $ganttChart->signal         = false; // previous
        $ganttChart->trigger        = true;

        $storedEvent = new GanttChartEvent([
            'process_id'     => 10,
            'gantt_chart_id' => 6,
            'signal'         => true,
        ]);

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldReceive('first')->andReturn($raspi);

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository->shouldReceive('get')->andReturn(new EloquentCollection([$ganttChart]));
        $ganttChartRepository->shouldReceive('getWithLockByIds')->andReturn(new EloquentCollection([$ganttChart]));

        $ganttChartEventRepository = Mockery::mock(GanttChartEventRepository::class);
        $ganttChartEventRepository
            ->shouldReceive('storeEvent')
            ->once()
            ->andReturn($storedEvent);

        $service = $this->makeService(
            ganttChart: $ganttChartRepository,
            ganttChartEvent: $ganttChartEventRepository,
            raspberryPi: $raspberryPiRepository,
        );

        $service->insert(1, '192.168.0.1', true);

        Event::assertDispatched(GanttChartNotification::class);
    }

    public function test_insertで信号が前回と同じ場合はイベントを作成しない(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 10:00:00'));
        Event::fake([GanttChartNotification::class]);

        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 2;

        // previous = true, signal = true → 同じ → イベントなし
        $ganttChart = Mockery::mock(GanttChart::class)->makePartial();
        $ganttChart->shouldReceive('save')->once()->andReturn(true);
        $ganttChart->process_id     = 10;
        $ganttChart->gantt_chart_id = 7;
        $ganttChart->signal         = true; // same as incoming signal
        $ganttChart->trigger        = true;

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldReceive('first')->andReturn($raspi);

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository->shouldReceive('get')->andReturn(new EloquentCollection([$ganttChart]));
        $ganttChartRepository->shouldReceive('getWithLockByIds')->andReturn(new EloquentCollection([$ganttChart]));

        $ganttChartEventRepository = Mockery::mock(GanttChartEventRepository::class);
        $ganttChartEventRepository->shouldNotReceive('storeEvent');

        $service = $this->makeService(
            ganttChart: $ganttChartRepository,
            ganttChartEvent: $ganttChartEventRepository,
            raspberryPi: $raspberryPiRepository,
        );

        $service->insert(1, '192.168.0.1', true);

        Event::assertNotDispatched(GanttChartNotification::class);
    }

    // ---------------------------------------------------------------------
    // getSections (private)
    // ---------------------------------------------------------------------

    public function test_getSectionsはON区間を時間範囲内でクランプして返す(): void
    {
        $start = Carbon::parse('2026-04-09 08:00:00');
        $end   = Carbon::parse('2026-04-09 17:00:00');
        $now   = Carbon::parse('2026-04-09 12:30:00');

        // ON: 07:00→10:00 (開始は $start でクランプされ 08:00)
        // ON: 11:00→13:00
        $events = new EloquentCollection([
            new GanttChartEvent(['signal' => true,  'at' => Carbon::parse('2026-04-09 07:00:00')]),
            new GanttChartEvent(['signal' => false, 'at' => Carbon::parse('2026-04-09 10:00:00')]),
            new GanttChartEvent(['signal' => true,  'at' => Carbon::parse('2026-04-09 11:00:00')]),
            new GanttChartEvent(['signal' => false, 'at' => Carbon::parse('2026-04-09 13:00:00')]),
        ]);

        $method = new ReflectionMethod(GanttChartService::class, 'getSections');
        $method->setAccessible(true);

        /** @var \Illuminate\Support\Collection<int, FromTo> $result */
        $result = $method->invoke($this->makeService(), $events, $start, $end, $now);

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]->from->eq(Carbon::parse('2026-04-09 08:00:00')));
        $this->assertTrue($result[0]->to->eq(Carbon::parse('2026-04-09 10:00:00')));
        $this->assertTrue($result[1]->from->eq(Carbon::parse('2026-04-09 11:00:00')));
        $this->assertTrue($result[1]->to->eq(Carbon::parse('2026-04-09 13:00:00')));
    }

    public function test_getSectionsで末尾がONの場合はnowを終端とする(): void
    {
        $start = Carbon::parse('2026-04-09 08:00:00');
        $end   = Carbon::parse('2026-04-09 17:00:00');
        $now   = Carbon::parse('2026-04-09 12:30:00');

        // 最後のイベントが ON のまま終わる場合、$now が終端になる
        $events = new EloquentCollection([
            new GanttChartEvent(['signal' => true, 'at' => Carbon::parse('2026-04-09 11:00:00')]),
        ]);

        $method = new ReflectionMethod(GanttChartService::class, 'getSections');
        $method->setAccessible(true);

        $result = $method->invoke($this->makeService(), $events, $start, $end, $now);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->from->eq(Carbon::parse('2026-04-09 11:00:00')));
        $this->assertTrue($result[0]->to->eq(Carbon::parse('2026-04-09 12:30:00')));
    }

    // ---------------------------------------------------------------------
    // getOverlappings (private)
    // ---------------------------------------------------------------------

    public function test_getOverlappingsは基準区間と稼働区間の重複を返す(): void
    {
        // base: [09:00, 11:00], [13:00, 15:00]
        // work: [10:00, 14:00]
        // 重複: [10:00, 11:00], [13:00, 14:00]
        $base = collect([
            new FromTo(Carbon::parse('2026-04-09 09:00:00'), Carbon::parse('2026-04-09 11:00:00')),
            new FromTo(Carbon::parse('2026-04-09 13:00:00'), Carbon::parse('2026-04-09 15:00:00')),
        ]);
        $work = collect([
            new FromTo(Carbon::parse('2026-04-09 10:00:00'), Carbon::parse('2026-04-09 14:00:00')),
        ]);

        $method = new ReflectionMethod(GanttChartService::class, 'getOverlappings');
        $method->setAccessible(true);

        /** @var \Illuminate\Support\Collection<int, FromTo> $result */
        $result = $method->invoke($this->makeService(), $base, $work);

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]->from->eq(Carbon::parse('2026-04-09 10:00:00')));
        $this->assertTrue($result[0]->to->eq(Carbon::parse('2026-04-09 11:00:00')));
        $this->assertTrue($result[1]->from->eq(Carbon::parse('2026-04-09 13:00:00')));
        $this->assertTrue($result[1]->to->eq(Carbon::parse('2026-04-09 14:00:00')));
    }

    public function test_getOverlappingsは重複がない場合は空コレクションを返す(): void
    {
        $base = collect([
            new FromTo(Carbon::parse('2026-04-09 09:00:00'), Carbon::parse('2026-04-09 10:00:00')),
        ]);
        $work = collect([
            new FromTo(Carbon::parse('2026-04-09 11:00:00'), Carbon::parse('2026-04-09 12:00:00')),
        ]);

        $method = new ReflectionMethod(GanttChartService::class, 'getOverlappings');
        $method->setAccessible(true);

        $result = $method->invoke($this->makeService(), $base, $work);

        $this->assertCount(0, $result);
    }
}
