<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\OnOff;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Repositories\OnOffRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OnOffRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_modelはOnOffクラスを返す(): void
    {
        $repository = new OnOffRepository();

        $this->assertSame(OnOff::class, $repository->model());
    }

    public function test_storeはONOFFメッセージを保存する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $request = $this->mockRequest([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => 'event-store',
            'on_message' => 'on-message',
            'off_message' => 'off-message',
            'pin_number' => 1,
        ]);
        $repository = new OnOffRepository();

        $result = $repository->store($request);

        $this->assertTrue($result);
        $this->assertDatabaseHas('on_offs', [
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => 'event-store',
            'on_message' => 'on-message',
            'off_message' => 'off-message',
            'pin_number' => 1,
        ]);
    }

    public function test_updateは既存のONOFFメッセージを更新する(): void
    {
        $onOff = $this->createOnOff('event-update', 2);
        $request = $this->mockRequest([
            'event_name' => 'event-updated',
            'on_message' => 'updated-on',
            'off_message' => null,
            'pin_number' => 22,
        ]);
        $repository = new OnOffRepository();

        $result = $repository->update($request, $onOff);

        $this->assertTrue($result);
        $updated = $onOff->fresh();
        $this->assertSame('event-updated', $updated?->event_name);
        $this->assertSame('updated-on', $updated?->on_message);
        $this->assertNull($updated?->off_message);
        $this->assertSame(22, $updated?->pin_number);
    }

    public function test_firstは条件に一致する最初のONOFFメッセージを返す(): void
    {
        $onOff = $this->createOnOff('event-first', 3);
        $repository = new OnOffRepository();

        $found = $repository->first([
            'raspberry_pi_id' => $onOff->raspberry_pi_id,
            'pin_number' => $onOff->pin_number,
        ]);

        $this->assertInstanceOf(OnOff::class, $found);
        $this->assertSame($onOff->on_off_id, $found?->on_off_id);
    }

    public function test_destroyはONOFFメッセージを削除する(): void
    {
        $onOff = $this->createOnOff('event-destroy', 4);
        $repository = new OnOffRepository();

        $result = $repository->destroy($onOff);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('on_offs', [
            'on_off_id' => $onOff->on_off_id,
        ]);
    }

    private function mockRequest(array $data): FormRequest
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('all')->once()->andReturn($data);

        return $request;
    }

    private function createOnOff(string $eventName, int $pinNumber): OnOff
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();

        /** @var OnOff */
        return OnOff::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => $eventName,
            'on_message' => "{$eventName}-on",
            'off_message' => "{$eventName}-off",
            'pin_number' => $pinNumber,
        ]);
    }
}
