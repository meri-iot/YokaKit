<?php

namespace Tests\Feature\Models;

use App\Models\PlannedOutage;
use App\Models\Process;
use App\Models\ProcessPlannedOutage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class ProcessPlannedOutageTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    public function test_工程計画停止時間追加ページにログインしていない状態でアクセスした場合はログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $response = $this->get(route('process.planned-outage.create', ['process' => $process]));
        $response->assertRedirect('login');
    }

    public function test_工程計画停止時間追加ページにログインしてる状態でアクセスしたguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $this->assertOk('process.planned-outage.create', ['process' => $process]);
    }

    public function test_工程計画停止時間追加ページにログインしてる状態でアクセスしたadminユーザーはok()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $this->assertOk('process.planned-outage.create', ['process' => $process]);
    }

    public function test_工程計画停止時間追加をログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->post(route('process.planned-outage.store', ['process' => $process]), [
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $response->assertRedirect('login');
    }

    public function test_工程計画停止時間追加のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $this->post(route('process.planned-outage.store', ['process' => $process]), [
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
    }

    public function test_工程計画停止時間追加の成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $response = $this->post(route('process.planned-outage.store', ['process' => $process]), [
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'planned-outage']))
            ->assertSessionHas('toast_success', '計画停止時間の登録に成功しました。');

        $storedPlannedOutage = ProcessPlannedOutage::latest()->first();
        $this->assertEquals($process->process_id, $storedPlannedOutage->process_id);
        $this->assertEquals($plannedOutage->planned_outage_id, $storedPlannedOutage->planned_outage_id);
    }

    public function test_工程計画停止時間追加の計画停止時間idが不正であるため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $response = $this->post(route('process.planned-outage.store', ['process' => $process]), [
            'planned_outage_id' => 1000,
        ]);
        $response->assertSessionHasErrors(['planned_outage_id' => '選択された計画停止時間は正しくありません。']);
    }

    public function test_工程計画停止時間追加の計画停止時間idが重複しているため追加失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $processPlannedOutage = ProcessPlannedOutage::factory()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $response = $this->post(route('process.planned-outage.store', ['process' => $process]), [
            'planned_outage_id' => $processPlannedOutage->planned_outage_id,
        ]);
        $response->assertSessionHasErrors(['planned_outage_id' => '計画停止時間の値は既に存在しています。']);
    }

    public function test_工程計画停止時間削除をログインしていない状態ではログインページへリダイレクト()
    {
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $processPlannedOutage = ProcessPlannedOutage::factory()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $response = $this->delete(route('process.planned-outage.destroy', ['process' => $process, 'processPlannedOutage' => $processPlannedOutage]));
        $response->assertRedirect('login');
    }

    public function test_工程計画停止時間削除のguestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $processPlannedOutage = ProcessPlannedOutage::factory()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $this->delete(route('process.planned-outage.destroy', ['process' => $process, 'processPlannedOutage' => $processPlannedOutage]));
    }

    public function test_工程計画停止時間削除の成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        $processPlannedOutage = ProcessPlannedOutage::factory()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);
        $response = $this->delete(route('process.planned-outage.destroy', ['process' => $process, 'processPlannedOutage' => $processPlannedOutage]));
        $response->assertRedirect(route('process.show', ['process' => $process, 'tab' => 'planned-outage']))
            ->assertSessionHas('toast_success', '計画停止時間の削除に成功しました。');

        $deletedPlannedOutage = ProcessPlannedOutage::find($processPlannedOutage->process_planned_outage_id);
        $this->assertNull($deletedPlannedOutage);
    }
}
