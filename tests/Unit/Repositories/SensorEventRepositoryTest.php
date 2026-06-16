<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\Sensor;
use App\Models\SensorEvent;
use App\Repositories\SensorEventRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensorEventRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはSensorEventクラスを返す(): void
    {
        $repository = new SensorEventRepository();

        $this->assertSame(SensorEvent::class, $repository->model());
    }

    public function test_saveはトリガー一致時に開始イベントとして保存する(): void
    {
        $sensor = $this->createSensor(101, 'temperature-high', true);
        $repository = new SensorEventRepository();

        $result = $repository->save($sensor, '192.168.10.11', true, 35.5);

        $this->assertNotNull($result);
        $this->assertNotNull($result->sensor_event_id);
        $this->assertSame($sensor->process_id, $result->process_id);
        $this->assertSame($sensor->sensor_id, $result->sensor_id);
        $this->assertSame(101, $result->identification_number);
        $this->assertSame('temperature-high', $result->alarm_text);
        $this->assertTrue($result->trigger);
        $this->assertTrue($result->signal);
        $this->assertTrue($result->is_start);
        $this->assertDatabaseHas('sensor_events', [
            'sensor_event_id' => $result->sensor_event_id,
            'process_id' => $sensor->process_id,
            'sensor_id' => $sensor->sensor_id,
            'ip_address' => '192.168.10.11',
            'identification_number' => 101,
            'alarm_text' => 'temperature-high',
            'trigger' => 1,
            'signal' => 1,
            'value' => 35.5,
        ]);
    }

    public function test_saveはトリガー不一致時に終了イベントとして保存する(): void
    {
        $sensor = $this->createSensor(102, 'temperature-normal', true);
        $repository = new SensorEventRepository();

        $result = $repository->save($sensor, '192.168.10.12', false, 28.0);

        $this->assertNotNull($result);
        $this->assertFalse($result->signal);
        $this->assertFalse($result->is_start);
    }

    private function createSensor(int $identificationNumber, string $alarmText, bool $trigger): Sensor
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();

        /** @var Sensor */
        return Sensor::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => $identificationNumber,
            'alarm_text' => $alarmText,
            'trigger' => $trigger,
        ]);
    }
}
