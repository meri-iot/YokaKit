<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\OnOff;
use App\Models\OnOffEvent;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Repositories\OnOffEventRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnOffEventRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはOnOffEventクラスを返す(): void
    {
        $repository = new OnOffEventRepository();

        $this->assertSame(OnOffEvent::class, $repository->model());
    }

    public function test_saveはONイベントをONメッセージで保存する(): void
    {
        $onOff      = $this->createOnOff('event-on', 'ON msg', 'OFF msg', 1);
        $repository = new OnOffEventRepository();

        $result = $repository->save($onOff, true);

        $this->assertNotNull($result);
        $this->assertNotNull($result->on_off_event_id);
        $this->assertTrue($result->on_off);
        $this->assertSame('ON msg', $result->message);
        $this->assertSame($onOff->on_off_id, $result->on_off_id);
        $this->assertSame($onOff->process_id, $result->process_id);
        $this->assertSame($onOff->event_name, $result->event_name);
        $this->assertSame($onOff->pin_number, $result->pin_number);

        // DBにも永続化されていることを確認する。
        $this->assertDatabaseHas('on_off_events', [
            'on_off_event_id' => $result->on_off_event_id,
            'on_off'          => true,
            'message'         => 'ON msg',
        ]);
    }

    public function test_saveはOFFイベントをOFFメッセージで保存する(): void
    {
        $onOff      = $this->createOnOff('event-off', 'ON msg', 'OFF msg', 2);
        $repository = new OnOffEventRepository();

        $result = $repository->save($onOff, false);

        $this->assertNotNull($result);
        $this->assertFalse($result->on_off);
        $this->assertSame('OFF msg', $result->message);
    }

    public function test_saveはOFFメッセージがnullのときOFFでもイベントを保存する(): void
    {
        // off_message が nullable であっても保存自体は成功する。
        // メッセージが null かどうかの判断はサービス層の責務であるため、
        // リポジトリは null のまま保存して OnOffEvent を返す。
        $onOff      = $this->createOnOff('event-null-off', 'ON msg', null, 3);
        $repository = new OnOffEventRepository();

        $result = $repository->save($onOff, false);

        $this->assertNotNull($result);
        $this->assertFalse($result->on_off);
        $this->assertNull($result->message);
        $this->assertDatabaseHas('on_off_events', [
            'on_off_event_id' => $result->on_off_event_id,
            'on_off'          => false,
            'message'         => null,
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // ヘルパー
    // ────────────────────────────────────────────────────────────

    /**
     * テスト用 OnOff レコードを作成する。
     *
     * on_offs テーブルは (process_id, event_name) と (raspberry_pi_id, pin_number) に
     * 複合ユニーク制約があるため、テスト間で重複しない値を使用すること。
     *
     * @param string      $eventName  イベント名
     * @param string      $onMessage  ON 時のメッセージ
     * @param string|null $offMessage OFF 時のメッセージ
     * @param int         $pinNumber  ピン番号
     */
    private function createOnOff(
        string $eventName,
        string $onMessage,
        ?string $offMessage,
        int $pinNumber,
    ): OnOff {
        $process = Process::factory()->create();
        $pi      = RaspberryPi::factory()->create();

        /** @var OnOff */
        return OnOff::query()->create([
            'process_id'      => $process->process_id,
            'raspberry_pi_id' => $pi->raspberry_pi_id,
            'event_name'      => $eventName,
            'on_message'      => $onMessage,
            'off_message'     => $offMessage,
            'pin_number'      => $pinNumber,
        ]);
    }
}
