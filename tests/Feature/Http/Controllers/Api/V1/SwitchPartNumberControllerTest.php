<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\CycleTime;
use App\Models\Line;
use App\Models\PartNumber;
use App\Models\Payload;
use App\Models\Production;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Models\Process;
use App\Models\RaspberryPi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class SwitchPartNumberControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
    }

    public function test_guestユーザーはng()
    {
        $this->createUser();
        $this->expect403();
        $this->postJson('/api/v1/switch-part-number', [
            'processName' => 'dummy-process',
            'partNumberName' => 'dummy-part-number',
            'force' => true,
            'changeover' => true,
        ]);
    }

    public function test_品番切り替えに成功()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $raspi = RaspberryPi::factory()->create([
            'ip_address' => '10.4.5.188',
        ]);
        Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => null,
        ]);
        $response = $this->postJson('/api/v1/switch-part-number', [
            'processName' => $process->process_name,
            'partNumberName' => $partNumber->part_number_name,
            'force' => true,
            'changeover' => true,
        ]);
        $response->assertStatus(200);

        // API入力から履歴・生産ライン・ペイロード・生産データまで初期化されることを確認する。
        $updatedProcess = Process::find($process->process_id);
        $this->assertNotNull($updatedProcess->production_history_id);

        $history = ProductionHistory::find($updatedProcess->production_history_id);
        $this->assertNotNull($history);
        $this->assertSame($process->process_name, $history->process_name);
        $this->assertSame($partNumber->part_number_name, $history->part_number_name);

        $productionLine = ProductionLine::where('production_history_id', $history->production_history_id)->first();
        $this->assertNotNull($productionLine);
        $this->assertSame($raspi->ip_address, $productionLine->ip_address);

        $payload = Payload::where('production_line_id', $productionLine->production_line_id)->first();
        $this->assertNotNull($payload);
        $payloadData = $payload->getPayloadData();
        $this->assertTrue($payloadData->indicator);
        $this->assertCount(1, $payloadData->changeovers);
        $this->assertNull($payloadData->changeovers[0]['to']);

        $production = Production::where('production_line_id', $productionLine->production_line_id)->first();
        $this->assertNotNull($production);
        $this->assertSame(0, $production->count);
    }

    public function test_品番切り替えの品番が存在しないため失敗()
    {
        $this->createAdmin();
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
        $raspi = RaspberryPi::factory()->create([
            'ip_address' => '10.4.5.188',
        ]);
        Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'worker_id' => null,
        ]);
        $response = $this->postJson('/api/v1/switch-part-number', [
            'processName' => $process->process_name,
            'partNumberName' => 'not-found-part-number',
            'force' => true,
            'changeover' => true,
        ]);
        $response->assertStatus(400);
    }

    public function test_バリデーションエラー時はbad_request()
    {
        $this->createAdmin();

        $response = $this->postJson('/api/v1/switch-part-number', [
            'processName' => 'dummy-process',
            // partNumberName は必須
            'force' => true,
            'changeover' => true,
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['errors' => ['partNumberName']]);
    }
}
