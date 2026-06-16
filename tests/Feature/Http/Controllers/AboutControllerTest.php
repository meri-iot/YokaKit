<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_未認証ユーザーはログイン画面へリダイレクトされる(): void
    {
        $response = $this->get('/about');

        $response->assertRedirect('login');
    }

    public function test_認証済みユーザーはabout画面を表示できる(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertViewIs('about');
    }
}
