<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreRaspberryPiRequest;
use App\Models\RaspberryPi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreRaspberryPiRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreRaspberryPiRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StoreRaspberryPiRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_rulesは正常な入力を許可する(): void
    {
        $request = new StoreRaspberryPiRequest();

        $validator = Validator::make([
            'raspberry_pi_name' => 'raspi-test-01',
            'ip_address' => '192.168.10.10',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはraspberry_pi_nameの重複を拒否する(): void
    {
        RaspberryPi::factory()->create(['raspberry_pi_name' => 'dup-raspi-name']);
        $request = new StoreRaspberryPiRequest();

        $validator = Validator::make([
            'raspberry_pi_name' => 'dup-raspi-name',
            'ip_address' => '192.168.10.11',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('raspberry_pi_name', $validator->errors()->toArray());
    }

    public function test_rulesはip_addressの重複を拒否する(): void
    {
        RaspberryPi::factory()->create(['ip_address' => '192.168.10.12']);
        $request = new StoreRaspberryPiRequest();

        $validator = Validator::make([
            'raspberry_pi_name' => 'raspi-test-dup-ip',
            'ip_address' => '192.168.10.12',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ip_address', $validator->errors()->toArray());
    }

    public function test_rulesはip_address形式が不正なら失敗する(): void
    {
        $request = new StoreRaspberryPiRequest();

        $validator = Validator::make([
            'raspberry_pi_name' => 'raspi-invalid-ip',
            'ip_address' => 'not-an-ip',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ip_address', $validator->errors()->toArray());
    }
}
