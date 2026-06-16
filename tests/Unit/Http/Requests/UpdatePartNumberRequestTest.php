<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdatePartNumberRequest;
use App\Models\PartNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdatePartNumberRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($user);

        $request = new UpdatePartNumberRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdatePartNumberRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルートIDを補完し任意項目を正規化する(): void
    {
        $partNumber = PartNumber::factory()->create();
        $request = UpdatePartNumberRequest::create('/dummy', 'PUT', [
            'part_number_name' => 'pn-update',
            'barcode' => '',
            'remark' => '',
        ]);

        $request->setRouteResolver(fn() => new class($partNumber) {
            public function __construct(private readonly PartNumber $partNumber) {}

            public function parameter(string $name): mixed
            {
                return $name === 'partNumber' ? $this->partNumber : null;
            }
        });

        $this->invokePrepareForValidation($request);

        $this->assertSame($partNumber->part_number_id, $request->input('part_number_id'));
        $this->assertNull($request->input('barcode'));
        $this->assertNull($request->input('remark'));
    }

    public function test_prepareForValidationはルートパラメータ欠落時はスキップする(): void
    {
        $request = UpdatePartNumberRequest::create('/dummy', 'PUT', [
            'part_number_id' => 123,
            'barcode' => 'abc',
            'remark' => 'memo',
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame(123, $request->input('part_number_id'));
        $this->assertSame('abc', $request->input('barcode'));
        $this->assertSame('memo', $request->input('remark'));
    }

    public function test_rulesは更新対象の同一値を許可する(): void
    {
        $partNumber = PartNumber::factory()->create([
            'part_number_name' => 'pn-current',
            'barcode' => 'bc-current',
            'remark' => 'memo',
        ]);

        $request = new UpdatePartNumberRequest();
        $request->merge(['part_number_id' => $partNumber->part_number_id]);

        $validator = Validator::make([
            'part_number_name' => 'pn-current',
            'barcode' => 'bc-current',
            'remark' => 'memo',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは重複した品番名を拒否する(): void
    {
        $existing = PartNumber::factory()->create(['part_number_name' => 'pn-dup-name']);
        $target = PartNumber::factory()->create(['part_number_name' => 'pn-target-name']);

        $request = new UpdatePartNumberRequest();
        $request->merge(['part_number_id' => $target->part_number_id]);

        $validator = Validator::make([
            'part_number_name' => 'pn-dup-name',
            'barcode' => 'bc-unique',
            'remark' => 'memo',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('part_number_name', $validator->errors()->toArray());
        $this->assertNotNull($existing->part_number_id);
    }

    public function test_rulesは重複したバーコードを拒否する(): void
    {
        $existing = PartNumber::factory()->create([
            'part_number_name' => 'pn-existing-bc',
            'barcode' => 'bc-dup',
        ]);
        $target = PartNumber::factory()->create([
            'part_number_name' => 'pn-target-bc',
            'barcode' => 'bc-target',
        ]);

        $request = new UpdatePartNumberRequest();
        $request->merge(['part_number_id' => $target->part_number_id]);

        $validator = Validator::make([
            'part_number_name' => 'pn-target-bc',
            'barcode' => 'bc-dup',
            'remark' => 'memo',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('barcode', $validator->errors()->toArray());
        $this->assertNotNull($existing->part_number_id);
    }

    private function invokePrepareForValidation(UpdatePartNumberRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
