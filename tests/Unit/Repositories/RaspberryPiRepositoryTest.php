<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\RaspberryPi;
use App\Repositories\RaspberryPiRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RaspberryPiRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_modelはRaspberryPiクラスを返す(): void
    {
        $repository = new RaspberryPiRepository();

        $this->assertSame(RaspberryPi::class, $repository->model());
    }

    public function test_updateCpuInfoは指定IPのCPU情報を更新する(): void
    {
        $raspi = RaspberryPi::factory()->create([
            'ip_address' => '192.168.1.10',
            'cpu_temperature' => null,
            'cpu_utilization' => null,
        ]);
        $repository = new RaspberryPiRepository();

        $result = $repository->updateCpuInfo('192.168.1.10', 55.5, 62.3);

        $this->assertTrue($result);
        $updated = $raspi->fresh();
        $this->assertSame(55.5, $updated?->cpu_temperature);
        $this->assertSame(62.3, $updated?->cpu_utilization);
    }

    public function test_updateCpuInfoは対象IPがなければfalseを返す(): void
    {
        RaspberryPi::factory()->create(['ip_address' => '192.168.1.11']);
        $repository = new RaspberryPiRepository();

        $result = $repository->updateCpuInfo('192.168.1.99', 40.0, 12.0);

        $this->assertFalse($result);
    }

    public function test_optionsはIPアドレス順の選択肢を返す(): void
    {
        $raspiB = RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'pi-b',
            'ip_address' => '192.168.1.20',
        ]);
        $raspiA = RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'pi-a',
            'ip_address' => '192.168.1.10',
        ]);
        $repository = new RaspberryPiRepository();

        $options = $repository->options();

        $this->assertSame([
            $raspiA->raspberry_pi_id => 'pi-a : 192.168.1.10',
            $raspiB->raspberry_pi_id => 'pi-b : 192.168.1.20',
        ], $options);
    }

    public function test_storeはラズパイを保存する(): void
    {
        $request = $this->mockRequest([
            'raspberry_pi_name' => 'pi-store',
            'ip_address' => '192.168.1.30',
        ]);
        $repository = new RaspberryPiRepository();

        $result = $repository->store($request);

        $this->assertTrue($result);
        $this->assertDatabaseHas('raspberry_pis', [
            'raspberry_pi_name' => 'pi-store',
            'ip_address' => '192.168.1.30',
        ]);
    }

    public function test_updateはラズパイ情報を更新する(): void
    {
        $raspi = RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'pi-old',
            'ip_address' => '192.168.1.40',
        ]);
        $request = $this->mockRequest([
            'raspberry_pi_name' => 'pi-new',
            'ip_address' => '192.168.1.41',
        ]);
        $repository = new RaspberryPiRepository();

        $result = $repository->update($request, $raspi);

        $this->assertTrue($result);
        $this->assertSame('pi-new', $raspi->fresh()?->raspberry_pi_name);
        $this->assertSame('192.168.1.41', $raspi->fresh()?->ip_address);
    }

    public function test_destroyはラズパイを削除する(): void
    {
        $raspi = RaspberryPi::factory()->create();
        $repository = new RaspberryPiRepository();

        $result = $repository->destroy($raspi);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('raspberry_pis', [
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
        ]);
    }

    private function mockRequest(array $data): FormRequest
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('all')->once()->andReturn($data);

        return $request;
    }
}
