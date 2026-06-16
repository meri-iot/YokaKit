<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StorePartNumberRequest;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StorePartNumberRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StorePartNumberRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StorePartNumberRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationは空文字の任意項目をnullへ正規化する(): void
    {
        $request = StorePartNumberRequest::create('/dummy', 'POST', [
            'part_number_name' => 'pn-empty-optional',
            'barcode' => '',
            'remark' => '',
        ]);

        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertNull($request->input('barcode'));
        $this->assertNull($request->input('remark'));
    }

    public function test_rulesは同一part_number_nameを拒否する(): void
    {
        PartNumber::factory()->create(['part_number_name' => 'pn-dup-name']);
        $request = new StorePartNumberRequest();

        $validator = Validator::make([
            'part_number_name' => 'pn-dup-name',
            'barcode' => 'bc-001',
            'remark' => 'memo',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('part_number_name', $validator->errors()->toArray());
    }

    public function test_rulesは同一barcodeを拒否する(): void
    {
        PartNumber::factory()->create([
            'part_number_name' => 'pn-barcode-base',
            'barcode' => 'bc-dup',
        ]);
        $request = new StorePartNumberRequest();

        $validator = Validator::make([
            'part_number_name' => 'pn-barcode-new',
            'barcode' => 'bc-dup',
            'remark' => 'memo',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('barcode', $validator->errors()->toArray());
    }

    public function test_rulesは空文字barcodeをnullへ正規化すれば重複せず成功する(): void
    {
        PartNumber::factory()->create([
            'part_number_name' => 'pn-existing-null-barcode',
            'barcode' => null,
        ]);
        $request = StorePartNumberRequest::create('/dummy', 'POST', [
            'part_number_name' => 'pn-new-null-barcode',
            'barcode' => '',
            'remark' => '',
        ]);

        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }
}
