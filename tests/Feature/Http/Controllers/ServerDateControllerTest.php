<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerDateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_未認証ユーザーはdateでログイン画面にリダイレクトされる(): void
    {
        $response = $this->get(route('date'));

        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーはサーバー時刻文字列を取得できる(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('date'));

        $response->assertStatus(200);
        $response->assertSeeText(' ');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/', $response->getContent());
    }
}
