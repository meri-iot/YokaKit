<?php

namespace Tests\Feature\Http\Controllers\DataTables;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataTablesLocaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_未認証ユーザーはdatatablesでログイン画面へリダイレクトされる(): void
    {
        $response = $this->get(route('datatables'));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーは現在ロケールのdatatables言語設定を取得できる(): void
    {
        config(['app.locale' => 'ja']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('datatables'));

        $response->assertOk();
        $decoded = json_decode($response->getContent(), true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
    }

    public function test_ロケール設定が存在しない場合は404を返す(): void
    {
        config(['app.locale' => 'zz']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('datatables'));

        $response->assertNotFound();
    }
}
