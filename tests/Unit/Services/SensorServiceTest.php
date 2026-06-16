<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\SensorAlarmNotification;
use App\Facades\Slack;
use App\Http\Requests\StoreSensorRequest;
use App\Http\Requests\UpdateSensorRequest;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\Sensor;
use App\Models\SensorEvent;
use App\Repositories\RaspberryPiRepository;
use App\Repositories\SensorEventRepository;
use App\Repositories\SensorRepository;
use App\Services\SensorService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class SensorServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_raspberryPiOptionsはリポジトリへ委譲する(): void
    {
        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('options')
            ->once()
            ->andReturn([1 => 'Pi-A : 192.168.0.10']);

        $service = new SensorService(
            $raspberryPiRepository,
            Mockery::mock(SensorRepository::class),
            Mockery::mock(SensorEventRepository::class),
        );

        $this->assertSame([1 => 'Pi-A : 192.168.0.10'], $service->raspberryPiOptions());
    }

    public function test_storeはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(StoreSensorRequest::class);

        $sensorRepository = Mockery::mock(SensorRepository::class);
        $sensorRepository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new SensorService(
            Mockery::mock(RaspberryPiRepository::class),
            $sensorRepository,
            Mockery::mock(SensorEventRepository::class),
        );

        $this->assertTrue($service->store($request));
    }

    public function test_updateはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(UpdateSensorRequest::class);
        $sensor = new Sensor();
        $sensor->sensor_id = 10;

        $sensorRepository = Mockery::mock(SensorRepository::class);
        $sensorRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $sensor)
            ->andReturn(true);

        $service = new SensorService(
            Mockery::mock(RaspberryPiRepository::class),
            $sensorRepository,
            Mockery::mock(SensorEventRepository::class),
        );

        $this->assertTrue($service->update($request, $sensor));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $sensor = new Sensor();
        $sensor->sensor_id = 11;

        $sensorRepository = Mockery::mock(SensorRepository::class);
        $sensorRepository
            ->shouldReceive('destroy')
            ->once()
            ->with($sensor)
            ->andReturn(true);

        $service = new SensorService(
            Mockery::mock(RaspberryPiRepository::class),
            $sensorRepository,
            Mockery::mock(SensorEventRepository::class),
        );

        $this->assertTrue($service->destroy($sensor));
    }

    public function test_insertは識別番号が非数値文字列なら何もしない(): void
    {
        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldNotReceive('first');

        $sensorRepository = Mockery::mock(SensorRepository::class);
        $sensorRepository->shouldNotReceive('first');

        $sensorEventRepository = Mockery::mock(SensorEventRepository::class);
        $sensorEventRepository->shouldNotReceive('save');

        Slack::shouldReceive('send')->never();
        Event::fake([SensorAlarmNotification::class]);

        $service = new SensorService($raspberryPiRepository, $sensorRepository, $sensorEventRepository);

        $service->insert('pin-A', '192.168.0.90', true, 12.3);

        Event::assertNotDispatched(SensorAlarmNotification::class);
    }

    public function test_insertはIPに一致するラズパイがなければ何もしない(): void
    {
        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('first')
            ->once()
            ->with(['ip_address' => '192.168.0.99'])
            ->andReturn(null);

        $sensorRepository = Mockery::mock(SensorRepository::class);
        $sensorRepository->shouldNotReceive('first');

        $sensorEventRepository = Mockery::mock(SensorEventRepository::class);
        $sensorEventRepository->shouldNotReceive('save');

        Slack::shouldReceive('send')->never();
        Event::fake([SensorAlarmNotification::class]);

        $service = new SensorService($raspberryPiRepository, $sensorRepository, $sensorEventRepository);

        $service->insert(1, '192.168.0.99', true, 45.6);

        Event::assertNotDispatched(SensorAlarmNotification::class);
    }

    public function test_insertはセンサー定義が見つからなければ何もしない(): void
    {
        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 2;

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('first')
            ->once()
            ->with(['ip_address' => '192.168.0.20'])
            ->andReturn($raspi);

        $sensorRepository = Mockery::mock(SensorRepository::class);
        $sensorRepository
            ->shouldReceive('first')
            ->once()
            ->with(
                ['raspberry_pi_id' => 2, 'identification_number' => 7],
                'process'
            )
            ->andReturn(null);

        $sensorEventRepository = Mockery::mock(SensorEventRepository::class);
        $sensorEventRepository->shouldNotReceive('save');

        Slack::shouldReceive('send')->never();
        Event::fake([SensorAlarmNotification::class]);

        $service = new SensorService($raspberryPiRepository, $sensorRepository, $sensorEventRepository);

        $service->insert('7', '192.168.0.20', false, 7);

        Event::assertNotDispatched(SensorAlarmNotification::class);
    }

    public function test_insertはイベント保存成功時にSlack通知とイベント発火を行う(): void
    {
        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 3;

        $process = new Process();
        $process->process_name = '工程A';

        $sensor = new Sensor();
        $sensor->sensor_id = 30;
        $sensor->process_id = 40;
        $sensor->alarm_text = '高温アラーム';
        $sensor->identification_number = 8;
        $sensor->trigger = true;
        $sensor->setRelation('process', $process);

        $savedEvent = new SensorEvent();
        $savedEvent->sensor_event_id = 501;
        $savedEvent->alarm_text = '高温アラーム';
        $savedEvent->trigger = true;
        $savedEvent->signal = true;

        $eventForBroadcast = new SensorEvent();
        $eventForBroadcast->sensor_event_id = 501;

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('first')
            ->once()
            ->with(['ip_address' => '192.168.0.30'])
            ->andReturn($raspi);

        $sensorRepository = Mockery::mock(SensorRepository::class);
        $sensorRepository
            ->shouldReceive('first')
            ->once()
            ->with(
                ['raspberry_pi_id' => 3, 'identification_number' => 8],
                'process'
            )
            ->andReturn($sensor);

        $sensorEventRepository = Mockery::mock(SensorEventRepository::class);
        $sensorEventRepository
            ->shouldReceive('save')
            ->once()
            ->with($sensor, '192.168.0.30', true, 60.2)
            ->andReturn($savedEvent);
        $sensorEventRepository
            ->shouldReceive('find')
            ->once()
            ->with(501)
            ->andReturn($eventForBroadcast);

        $this->app->instance(SensorEventRepository::class, $sensorEventRepository);

        Slack::shouldReceive('send')
            ->once()
            ->with(Mockery::type('string'));

        Event::fake([SensorAlarmNotification::class]);

        $service = new SensorService($raspberryPiRepository, $sensorRepository, $sensorEventRepository);

        $service->insert('8', '192.168.0.30', true, 60.2);

        Event::assertDispatched(SensorAlarmNotification::class);
    }
}
