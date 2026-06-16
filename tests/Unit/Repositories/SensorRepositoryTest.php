<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\Sensor;
use App\Repositories\SensorRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SensorRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_modelはSensorクラスを返す(): void
    {
        $repository = new SensorRepository();

        $this->assertSame(Sensor::class, $repository->model());
    }

    public function test_storeはセンサーを保存する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $request = $this->mockRequest([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => 1001,
            'alarm_text' => 'temperature-alert',
            'trigger' => true,
        ]);
        $repository = new SensorRepository();

        $result = $repository->store($request);

        $this->assertTrue($result);
        $this->assertDatabaseHas('sensors', [
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => 1001,
            'alarm_text' => 'temperature-alert',
            'trigger' => 1,
        ]);
    }

    public function test_updateは既存センサーを更新する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $sensor = $this->createSensor($process, $raspberryPi, 1002, 'before', false);
        $request = $this->mockRequest([
            'identification_number' => 2002,
            'alarm_text' => 'after',
            'trigger' => true,
        ]);
        $repository = new SensorRepository();

        $result = $repository->update($request, $sensor);

        $this->assertTrue($result);
        $updated = $sensor->fresh();
        $this->assertSame(2002, $updated?->identification_number);
        $this->assertSame('after', $updated?->alarm_text);
        $this->assertTrue((bool) $updated?->trigger);
    }

    public function test_getは条件に一致するセンサーを返す(): void
    {
        $processA = Process::factory()->create();
        $processB = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $sensorA = $this->createSensor($processA, $raspberryPi, 1003, 'a', true);
        $this->createSensor($processB, $raspberryPi, 1004, 'b', false);
        $repository = new SensorRepository();

        $result = $repository->get(['process_id' => $processA->process_id]);

        $this->assertCount(1, $result);
        $this->assertSame($sensorA->sensor_id, $result->first()?->sensor_id);
    }

    public function test_destroyはセンサーを削除する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $sensor = $this->createSensor($process, $raspberryPi, 1005, 'to-delete', false);
        $repository = new SensorRepository();

        $result = $repository->destroy($sensor);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('sensors', [
            'sensor_id' => $sensor->sensor_id,
        ]);
    }

    private function createSensor(Process $process, RaspberryPi $raspberryPi, int $number, string $alarmText, bool $trigger): Sensor
    {
        /** @var Sensor */
        return Sensor::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => $number,
            'alarm_text' => $alarmText,
            'trigger' => $trigger,
        ]);
    }

    private function mockRequest(array $data): FormRequest
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('all')->once()->andReturn($data);

        return $request;
    }
}
