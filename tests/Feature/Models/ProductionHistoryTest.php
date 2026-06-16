<?php

namespace Tests\Feature\Models;

use App\Enums\ProductionStatus;
use App\Jobs\ChangeoverJob;
use App\Models\PlannedOutage;
use App\Models\CycleTime;
use App\Models\Line;
use App\Models\PartNumber;
use App\Models\Process;
use App\Models\ProcessPlannedOutage;
use App\Models\Production;
use App\Models\ProductionPlannedOutage;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Models\RaspberryPi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class ProductionHistoryTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        // 遅延再投入されるジョブの同期実行ループを避け、HTTP/DBの期待のみ検証する。
        Queue::fake();
    }

    public function test_品番切り替えページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->get(route('production.create', ['process' => $process]));
        $response->assertRedirect('login');
    }

    public function test_品番切り替えページにログインしている状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $process = Process::factory()->create();
        $this->assertOk('production.create', ['process' => $process]);
    }

    public function test_品番切り替えページにログインしている状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $this->assertOk('production.create', ['process' => $process]);
    }

    public function test_品番切り替えのログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->post(route('production.store', ['process' => $process]), [
            'part_number_id' => $cycleTime->part_number_id,
        ]);
        $response->assertRedirect('login');
    }

    public function test_品番切り替えのguestユーザーはng()
    {
        $this->createUser();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->post(route('production.store', ['process' => $process]), [
            'part_number_id' => $cycleTime->part_number_id,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]));
    }

    public function test_品番切り替えの成功()
    {
        Event::fake();
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $raspi = RaspberryPi::factory()->create([
            'ip_address' => '10.4.5.188',
        ]);
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => null,
        ]);
        $plannedOutage = PlannedOutage::factory()->create();
        ProcessPlannedOutage::factory()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $response = $this->post(route('production.store', ['process' => $process]), [
            'part_number_id' => $cycleTime->part_number_id,
            'changeover' => 'true',
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_success', '品番切り替えに成功しました。');

        $storedProductionHistory = ProductionHistory::latest('production_history_id')->first();
        $this->assertEquals($process->process_name, $storedProductionHistory->process_name);
        $this->assertEquals($partNumber->part_number_name, $storedProductionHistory->part_number_name);
        $this->assertEquals($cycleTime->cycle_time, $storedProductionHistory->cycle_time);
        $this->assertEquals($cycleTime->over_time, $storedProductionHistory->over_time);
        $this->assertNotNull($storedProductionHistory->start);
        $this->assertNull($storedProductionHistory->stop);
        $this->assertEquals(ProductionStatus::CHANGEOVER(), $storedProductionHistory->status);

        $storedLine = ProductionLine::where('production_history_id', $storedProductionHistory->production_history_id)->get();
        $this->assertCount(1, $storedLine);
        $this->assertEquals($line->line_name, $storedLine[0]->line_name);
        $this->assertEquals($line->chart_color, $storedLine[0]->chart_color);
        $this->assertEquals($line->pin_number, $storedLine[0]->pin_number);
        $this->assertEquals(true, $storedLine[0]->indicator);
        $this->assertEquals($raspi->ip_address, $storedLine[0]->ip_address);

        $storedPlannedOutage = ProductionPlannedOutage::where('production_history_id', $storedProductionHistory->production_history_id)->get();
        $this->assertCount(1, $storedPlannedOutage);
        $this->assertEquals($plannedOutage->planned_outage_name, $storedPlannedOutage[0]->planned_outage_name);
        $this->assertEquals($plannedOutage->start_time, $storedPlannedOutage[0]->start_time);
        $this->assertEquals($plannedOutage->end_time, $storedPlannedOutage[0]->end_time);

        $storedProduction = Production::where('production_line_id', $storedLine[0]->production_line_id)->get();
        $this->assertCount(1, $storedProduction);
        $this->assertNotNull($storedProduction[0]->at);
        $this->assertEquals(0, $storedProduction[0]->count);

        $updatedProcess = Process::find($process->process_id);
        $this->assertEquals($storedProductionHistory->production_history_id, $updatedProcess->production_history_id);
    }

    public function test_品番切り替えの品番idが空であるため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('production.store', ['process' => $process]), [
            // 'part_number_id' => $partNumber->part_number_id,
        ]);
        $response->assertSessionHasErrors(['part_number_id' => '選択された品番は正しくありません。']);
    }

    public function test_品番切り替えの品番idが存在しないため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('production.store', ['process' => $process]), [
            'part_number_id' => 12346,
        ]);
        $response->assertSessionHasErrors(['part_number_id' => '選択された品番は正しくありません。']);
    }

    public function test_品番切り替えの品番がすでに切り替え済みであるため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $history = ProductionHistory::factory()->create([
            'process_name' => $process->process_name,
            'part_number_name' => $partNumber->part_number_name,
            'cycle_time' => $cycleTime->cycle_time,
            'over_time' => $cycleTime->over_time,
            'status' => ProductionStatus::RUNNING(),
        ]);
        $process->production_history_id = $history->production_history_id;
        $process->update();
        $response = $this->post(route('production.store', ['process' => $process]), [
            'part_number_id' => $cycleTime->part_number_id,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_danger', '品番切り替えに失敗しました。');
    }

    public function test_品番切り替えのラズパイが存在しないため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $response = $this->post(route('production.store', ['process' => $process]), [
            'part_number_id' => $cycleTime->part_number_id,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_danger', '品番切り替えに失敗しました。');
    }

    public function test_品番切り替えのラズパイと通信できないため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $raspi = RaspberryPi::factory()->create([
            'ip_address' => '10.4.5.189',
        ]);
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => null,
        ]);
        $plannedOutage = PlannedOutage::factory()->create();
        ProcessPlannedOutage::factory()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $response = $this->post(route('production.store', ['process' => $process]), [
            'part_number_id' => $cycleTime->part_number_id,
            'changeover' => 'true',
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_success', '品番切り替えに成功しました。');
    }

    public function test_品番切り替えの指標が存在しないため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $raspi = RaspberryPi::factory()->create([
            'ip_address' => '10.4.5.189',
        ]);
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'defective' => true,
            'worker_id' => null,
        ]);
        $plannedOutage = PlannedOutage::factory()->create();
        ProcessPlannedOutage::factory()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $response = $this->post(route('production.store', ['process' => $process]), [
            'part_number_id' => $cycleTime->part_number_id,
            'changeover' => 'true',
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_danger', '指標が存在しません。');
    }

    public function test_停止のログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->put(route('production.stop', ['process' => $process]));
        $response->assertRedirect('login');
    }

    public function test_停止のguestユーザーはng()
    {
        $this->createUser();
        $process = Process::factory()->create();
        $response = $this->put(route('production.stop', ['process' => $process]));
        $response->assertRedirect(route('process.show', ['process' => $process]));
    }

    public function test_停止の成功()
    {
        Event::fake();
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $history = ProductionHistory::factory()->create([
            'process_name' => $process->process_name,
            'part_number_name' => $partNumber->part_number_name,
            'cycle_time' => $cycleTime->cycle_time,
            'over_time' => $cycleTime->over_time,
            'status' => ProductionStatus::RUNNING(),
        ]);
        $process->production_history_id = $history->production_history_id;
        $process->save();
        $response = $this->put(route('production.stop', ['process' => $process]));
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_success', '生産履歴の停止に成功しました。');

        $updatedProcess = Process::find($process->process_id);
        $this->assertNull($updatedProcess->production_history_id);

        $updatedHistory = ProductionHistory::find($history->production_history_id);
        $this->assertNotNull($updatedHistory->stop);
        $this->assertEquals(ProductionStatus::COMPLETE(), $updatedHistory->status);
    }

    public function test_停止の失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->put(route('production.stop', ['process' => $process]));
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_danger', '生産履歴の停止に失敗しました。');
    }

    public function test_段取り替えのguestユーザーはng()
    {
        $this->createUser();
        $process = Process::factory()->create();
        $response = $this->put(route('production.start_changeover', ['process' => $process]));
        $response->assertRedirect(route('process.show', ['process' => $process]));
    }

    public function test_段取り替えの成功()
    {
        Event::fake();
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $cycleTime = CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $raspi = RaspberryPi::factory()->create([
            'ip_address' => '10.4.5.188',
        ]);
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => null,
        ]);
        $plannedOutage = PlannedOutage::factory()->create();
        ProcessPlannedOutage::factory()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $history = ProductionHistory::factory()->create([
            'process_name' => $process->process_name,
            'part_number_name' => $partNumber->part_number_name,
            'cycle_time' => $cycleTime->cycle_time,
            'over_time' => $cycleTime->over_time,
            'status' => ProductionStatus::RUNNING(),
        ]);
        $process->production_history_id = $history->production_history_id;
        $process->save();
        $response = $this->put(route('production.start_changeover', ['process' => $process]));
        $response->assertRedirect(route('process.show', ['process' => $process]))
            ->assertSessionHas('toast_success', '段取り替えに成功しました。');

        $updatedProductionHistory = ProductionHistory::latest('production_history_id')->first();
        $this->assertEquals(ProductionStatus::CHANGEOVER(), $updatedProductionHistory->status);
        Queue::assertPushed(ChangeoverJob::class);
    }
}
