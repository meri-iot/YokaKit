<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\OnOffNotificationEvent;
use App\Http\Requests\StoreOnOffRequest;
use App\Http\Requests\UpdateOnOffRequest;
use App\Models\OnOff;
use App\Models\OnOffEvent;
use App\Models\RaspberryPi;
use App\Repositories\OnOffEventRepository;
use App\Repositories\OnOffRepository;
use App\Repositories\RaspberryPiRepository;
use App\Services\OnOffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class OnOffServiceTest extends TestCase
{
    use RefreshDatabase;

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

        $service = new OnOffService(
            Mockery::mock(OnOffRepository::class),
            Mockery::mock(OnOffEventRepository::class),
            $raspberryPiRepository,
        );

        $this->assertSame([1 => 'Pi-A : 192.168.0.10'], $service->raspberryPiOptions());
    }

    public function test_storeはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(StoreOnOffRequest::class);

        $onOffRepository = Mockery::mock(OnOffRepository::class);
        $onOffRepository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new OnOffService(
            $onOffRepository,
            Mockery::mock(OnOffEventRepository::class),
            Mockery::mock(RaspberryPiRepository::class),
        );

        $this->assertTrue($service->store($request));
    }

    public function test_updateはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(UpdateOnOffRequest::class);
        $onOff = new OnOff();
        $onOff->on_off_id = 11;

        $onOffRepository = Mockery::mock(OnOffRepository::class);
        $onOffRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $onOff)
            ->andReturn(true);

        $service = new OnOffService(
            $onOffRepository,
            Mockery::mock(OnOffEventRepository::class),
            Mockery::mock(RaspberryPiRepository::class),
        );

        $this->assertTrue($service->update($request, $onOff));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $onOff = new OnOff();
        $onOff->on_off_id = 12;

        $onOffRepository = Mockery::mock(OnOffRepository::class);
        $onOffRepository
            ->shouldReceive('destroy')
            ->once()
            ->with($onOff)
            ->andReturn(true);

        $service = new OnOffService(
            $onOffRepository,
            Mockery::mock(OnOffEventRepository::class),
            Mockery::mock(RaspberryPiRepository::class),
        );

        $this->assertTrue($service->destroy($onOff));
    }

    public function test_insertはIPに一致するラズパイがなければfalseを返す(): void
    {
        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('first')
            ->once()
            ->with(['ip_address' => '192.168.0.99'])
            ->andReturn(null);

        $onOffRepository = Mockery::mock(OnOffRepository::class);
        $onOffRepository->shouldNotReceive('first');

        $service = new OnOffService(
            $onOffRepository,
            Mockery::mock(OnOffEventRepository::class),
            $raspberryPiRepository,
        );

        $this->assertFalse($service->insert(true, 3, '192.168.0.99'));
    }

    public function test_insertは設定が見つからなければfalseを返す(): void
    {
        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 2;

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('first')
            ->once()
            ->with(['ip_address' => '192.168.0.20'])
            ->andReturn($raspi);

        $onOffRepository = Mockery::mock(OnOffRepository::class);
        $onOffRepository
            ->shouldReceive('first')
            ->once()
            ->with(['raspberry_pi_id' => 2, 'pin_number' => 7])
            ->andReturn(null);

        $onOffEventRepository = Mockery::mock(OnOffEventRepository::class);
        $onOffEventRepository->shouldNotReceive('save');

        $service = new OnOffService(
            $onOffRepository,
            $onOffEventRepository,
            $raspberryPiRepository,
        );

        $this->assertFalse($service->insert(false, 7, '192.168.0.20'));
    }

    public function test_insertはイベント保存失敗時にfalseを返す(): void
    {
        $onOff = new OnOff();
        $onOff->on_off_id = 5;
        $onOff->raspberry_pi_id = 2;
        $onOff->pin_number = 8;

        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 2;

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldReceive('first')->andReturn($raspi);

        $onOffRepository = Mockery::mock(OnOffRepository::class);
        $onOffRepository->shouldReceive('first')->once()->andReturn($onOff);

        $onOffEventRepository = Mockery::mock(OnOffEventRepository::class);
        $onOffEventRepository
            ->shouldReceive('save')
            ->once()
            ->with($onOff, true)
            ->andReturn(null);

        Event::fake([OnOffNotificationEvent::class]);

        $service = new OnOffService(
            $onOffRepository,
            $onOffEventRepository,
            $raspberryPiRepository,
        );

        $this->assertFalse($service->insert(true, 8, '192.168.0.20'));
        Event::assertNotDispatched(OnOffNotificationEvent::class);
    }

    public function test_insertはmessageがnullのイベントを通知せずfalseを返す(): void
    {
        $onOff = new OnOff();
        $onOff->on_off_id = 6;
        $onOff->raspberry_pi_id = 2;
        $onOff->pin_number = 9;

        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 2;

        $event = new OnOffEvent();
        $event->on_off_event_id = 100;
        $event->message = null;

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldReceive('first')->andReturn($raspi);

        $onOffRepository = Mockery::mock(OnOffRepository::class);
        $onOffRepository->shouldReceive('first')->once()->andReturn($onOff);

        $onOffEventRepository = Mockery::mock(OnOffEventRepository::class);
        $onOffEventRepository->shouldReceive('save')->once()->with($onOff, false)->andReturn($event);

        Event::fake([OnOffNotificationEvent::class]);

        $service = new OnOffService(
            $onOffRepository,
            $onOffEventRepository,
            $raspberryPiRepository,
        );

        $this->assertFalse($service->insert(false, 9, '192.168.0.20'));
        Event::assertNotDispatched(OnOffNotificationEvent::class);
    }

    public function test_insertはmessageありイベントを通知してtrueを返す(): void
    {
        $onOff = new OnOff();
        $onOff->on_off_id = 7;
        $onOff->raspberry_pi_id = 2;
        $onOff->pin_number = 10;

        $raspi = new RaspberryPi();
        $raspi->raspberry_pi_id = 2;

        $event = new OnOffEvent();
        $event->on_off_event_id = 101;
        $event->message = 'machine started';

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository->shouldReceive('first')->andReturn($raspi);

        $onOffRepository = Mockery::mock(OnOffRepository::class);
        $onOffRepository->shouldReceive('first')->once()->andReturn($onOff);

        $onOffEventRepository = Mockery::mock(OnOffEventRepository::class);
        $onOffEventRepository->shouldReceive('save')->once()->with($onOff, true)->andReturn($event);

        Event::fake([OnOffNotificationEvent::class]);

        $service = new OnOffService(
            $onOffRepository,
            $onOffEventRepository,
            $raspberryPiRepository,
        );

        $this->assertTrue($service->insert(true, 10, '192.168.0.20'));
        Event::assertDispatched(OnOffNotificationEvent::class);
    }
}
