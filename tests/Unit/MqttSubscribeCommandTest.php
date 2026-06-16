<?php

namespace Tests\Unit;

use App\Console\Commands\MqttSubscribeCommand;
use App\Services\BarcodeHistoryService;
use App\Services\GanttChartService;
use App\Services\OnOffService;
use App\Services\ProductionHistoryService;
use App\Services\ProductionService;
use App\Services\RaspberryPiService;
use App\Services\SensorService;
use Illuminate\Console\Command;
use Mockery;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Facades\MQTT;
use RuntimeException;
use Tests\TestCase;

class MqttSubscribeCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeCommand(
        RaspberryPiService $raspberryPiService,
        ProductionService $productionService,
        BarcodeHistoryService $barcodeHistoryService,
        ProductionHistoryService $productionHistoryService,
        SensorService $sensorService,
        OnOffService $onOffService,
        GanttChartService $ganttChartService,
    ): MqttSubscribeCommand {
        return new MqttSubscribeCommand(
            $raspberryPiService,
            $productionService,
            $barcodeHistoryService,
            $productionHistoryService,
            $sensorService,
            $onOffService,
            $ganttChartService,
        );
    }

    public function test_handleは全トピックを購読して成功を返す(): void
    {
        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('subscribe')->times(6)->andReturnNull();
        $client->shouldReceive('loop')->once()->with(true)->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('production')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('heartbeat')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('barcode')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('alarm')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('onoff')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('gantt-chart')->andReturnNull();
        $client->shouldReceive('disconnect')->once()->andReturnNull();

        MQTT::shouldReceive('connection')->once()->andReturn($client);

        $command = $this->makeCommand(
            Mockery::mock(RaspberryPiService::class),
            Mockery::mock(ProductionService::class),
            Mockery::mock(BarcodeHistoryService::class),
            Mockery::mock(ProductionHistoryService::class),
            Mockery::mock(SensorService::class),
            Mockery::mock(OnOffService::class),
            Mockery::mock(GanttChartService::class),
        );

        $this->assertSame(Command::SUCCESS, $command->handle());
    }

    public function test_handleはloopで例外が発生した場合に失敗を返す(): void
    {
        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('subscribe')->times(6)->andReturnNull();
        $client->shouldReceive('loop')->once()->with(true)->andThrow(new RuntimeException('loop failed'));
        $client->shouldReceive('unsubscribe')->once()->with('production')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('heartbeat')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('barcode')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('alarm')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('onoff')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('gantt-chart')->andReturnNull();
        $client->shouldReceive('disconnect')->once()->andReturnNull();

        MQTT::shouldReceive('connection')->once()->andReturn($client);

        $command = $this->makeCommand(
            Mockery::mock(RaspberryPiService::class),
            Mockery::mock(ProductionService::class),
            Mockery::mock(BarcodeHistoryService::class),
            Mockery::mock(ProductionHistoryService::class),
            Mockery::mock(SensorService::class),
            Mockery::mock(OnOffService::class),
            Mockery::mock(GanttChartService::class),
        );

        $this->assertSame(Command::FAILURE, $command->handle());
    }

    public function test_不正JSONペイロードはサービスを呼ばずに無視される(): void
    {
        $raspberryPiService = Mockery::mock(RaspberryPiService::class);
        $raspberryPiService->shouldNotReceive('updateCpuInfo');

        $callbacks = [];
        $client = Mockery::mock(MqttClient::class);
        $client->shouldReceive('subscribe')
            ->times(6)
            ->andReturnUsing(function ($topic, $callback, $qos) use (&$callbacks) {
                $callbacks[$topic] = $callback;
                return null;
            });
        $client->shouldReceive('loop')->once()->with(true)->andReturnUsing(function () use (&$callbacks) {
            $heartbeat = $callbacks['heartbeat'];
            $heartbeat('heartbeat', '{"invalid"');
            return null;
        });
        $client->shouldReceive('unsubscribe')->once()->with('production')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('heartbeat')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('barcode')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('alarm')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('onoff')->andReturnNull();
        $client->shouldReceive('unsubscribe')->once()->with('gantt-chart')->andReturnNull();
        $client->shouldReceive('disconnect')->once()->andReturnNull();

        MQTT::shouldReceive('connection')->once()->andReturn($client);

        $command = $this->makeCommand(
            $raspberryPiService,
            Mockery::mock(ProductionService::class),
            Mockery::mock(BarcodeHistoryService::class),
            Mockery::mock(ProductionHistoryService::class),
            Mockery::mock(SensorService::class),
            Mockery::mock(OnOffService::class),
            Mockery::mock(GanttChartService::class),
        );

        $this->assertSame(Command::SUCCESS, $command->handle());
    }
}
