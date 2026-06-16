<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\OnOff;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use App\Services\OnOffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OnOffControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;
    private Process $process;
    private RaspberryPi $raspberryPi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 5]);
        $this->normalUser = User::factory()->create();
        $this->process = Process::factory()->create();
        $this->raspberryPi = RaspberryPi::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_未認証ユーザーはindexでログイン画面へリダイレクトされる(): void
    {
        $response = $this->get(route('onoff.index', $this->process));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーはindexを表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('onoff.index', $this->process));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('onoff.create', $this->process));

        $response->assertStatus(403);
    }

    public function test_管理者はcreateにアクセスできる(): void
    {
        $this->mock(OnOffService::class, function (MockInterface $mock) {
            $mock->shouldReceive('raspberryPiOptions')->once()->andReturn([]);
        });

        $response = $this->actingAs($this->adminUser)->get(route('onoff.create', $this->process));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはstoreを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->post(route('onoff.store', $this->process), $this->storePayload());

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(OnOffService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->post(route('onoff.store', $this->process), $this->storePayload());

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'on-off']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはupdateを実行できない(): void
    {
        $onOff = $this->createOnOff();

        $response = $this->actingAs($this->normalUser)->put(
            route('onoff.update', [$this->process, $onOff]),
            $this->updatePayload($onOff)
        );

        $response->assertStatus(403);
    }

    public function test_updateでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(OnOffService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(false);
        });
        $onOff = $this->createOnOff();

        $response = $this->actingAs($this->adminUser)->put(
            route('onoff.update', [$this->process, $onOff]),
            $this->updatePayload($onOff)
        );

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'on-off']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはdestroyを実行できない(): void
    {
        $onOff = $this->createOnOff();

        $response = $this->actingAs($this->normalUser)->delete(route('onoff.destroy', [$this->process, $onOff]));

        $response->assertStatus(403);
    }

    public function test_destroyでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(OnOffService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->once()->andReturn(true);
        });
        $onOff = $this->createOnOff();

        $response = $this->actingAs($this->adminUser)->delete(route('onoff.destroy', [$this->process, $onOff]));

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'on-off']));
        $response->assertSessionHas('toast_success');
    }

    private function createOnOff(): OnOff
    {
        $onOff = new OnOff();
        $onOff->process_id = $this->process->process_id;
        $onOff->raspberry_pi_id = $this->raspberryPi->raspberry_pi_id;
        $onOff->event_name = 'event-a';
        $onOff->on_message = 'on';
        $onOff->off_message = 'off';
        $onOff->pin_number = 1;
        $onOff->save();

        return $onOff;
    }

    private function storePayload(): array
    {
        return [
            'raspberry_pi_id' => $this->raspberryPi->raspberry_pi_id,
            'event_name' => 'new-event',
            'on_message' => 'on-message',
            'off_message' => 'off-message',
            'pin_number' => 2,
        ];
    }

    private function updatePayload(OnOff $onOff): array
    {
        return [
            'raspberry_pi_id' => $onOff->raspberry_pi_id,
            'event_name' => $onOff->event_name,
            'on_message' => $onOff->on_message,
            'off_message' => $onOff->off_message,
            'pin_number' => $onOff->pin_number,
        ];
    }
}
