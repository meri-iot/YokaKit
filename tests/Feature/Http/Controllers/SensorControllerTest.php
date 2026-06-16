<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\Sensor;
use App\Models\User;
use App\Services\SensorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SensorControllerTest extends TestCase
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

    public function test_未認証ユーザーはcreateでログイン画面にリダイレクトされる(): void
    {
        $response = $this->get(route('alarm.create', $this->process));

        $response->assertRedirect(route('login'));
    }

    public function test_一般ユーザーはcreateにアクセスできない(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('alarm.create', $this->process));

        $response->assertStatus(403);
    }

    public function test_管理者はcreateにアクセスできる(): void
    {
        $this->mock(SensorService::class, function (MockInterface $mock) {
            $mock->shouldReceive('raspberryPiOptions')->once()->andReturn([]);
        });

        $response = $this->actingAs($this->adminUser)->get(route('alarm.create', $this->process));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはstoreを実行できない(): void
    {
        $response = $this->actingAs($this->normalUser)->post(route('alarm.store', $this->process), $this->payload());

        $response->assertStatus(403);
    }

    public function test_storeでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(SensorService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->once()->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->post(route('alarm.store', $this->process), $this->payload());

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'alarm']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはeditにアクセスできない(): void
    {
        $sensor = $this->createSensor();

        $response = $this->actingAs($this->normalUser)->get(route('alarm.edit', [$this->process, $sensor]));

        $response->assertStatus(403);
    }

    public function test_管理者はeditにアクセスできる(): void
    {
        $this->mock(SensorService::class, function (MockInterface $mock) {
            $mock->shouldReceive('raspberryPiOptions')->once()->andReturn([]);
        });
        $sensor = $this->createSensor();

        $response = $this->actingAs($this->adminUser)->get(route('alarm.edit', [$this->process, $sensor]));

        $response->assertStatus(200);
    }

    public function test_一般ユーザーはupdateを実行できない(): void
    {
        $sensor = $this->createSensor();

        $response = $this->actingAs($this->normalUser)->put(route('alarm.update', [$this->process, $sensor]), $this->payload());

        $response->assertStatus(403);
    }

    public function test_updateでサービス失敗時はdangerトーストで遷移する(): void
    {
        $this->mock(SensorService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->once()->andReturn(false);
        });
        $sensor = $this->createSensor();

        $response = $this->actingAs($this->adminUser)->put(route('alarm.update', [$this->process, $sensor]), $this->payload());

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'alarm']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_一般ユーザーはdestroyを実行できない(): void
    {
        $sensor = $this->createSensor();

        $response = $this->actingAs($this->normalUser)->delete(route('alarm.destroy', [$this->process, $sensor]));

        $response->assertStatus(403);
    }

    public function test_destroyでサービス成功時はsuccessトーストで遷移する(): void
    {
        $this->mock(SensorService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->once()->andReturn(true);
        });
        $sensor = $this->createSensor();

        $response = $this->actingAs($this->adminUser)->delete(route('alarm.destroy', [$this->process, $sensor]));

        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'alarm']));
        $response->assertSessionHas('toast_success');
    }

    private function createSensor(): Sensor
    {
        $sensor = new Sensor();
        $sensor->process_id = $this->process->process_id;
        $sensor->raspberry_pi_id = $this->raspberryPi->raspberry_pi_id;
        $sensor->identification_number = 1;
        $sensor->alarm_text = 'test alarm';
        $sensor->trigger = true;
        $sensor->save();

        return $sensor;
    }

    private function payload(): array
    {
        return [
            'raspberry_pi_id' => $this->raspberryPi->raspberry_pi_id,
            'identification_number' => 1,
            'alarm_text' => 'test alarm',
            'trigger' => '1',
        ];
    }
}
