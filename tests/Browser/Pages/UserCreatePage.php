<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * ユーザー追加画面のページオブジェクト
 */
class UserCreatePage extends Page
{
    public function url(): string
    {
        return '/yokakit/user/create';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url());
    }

    public function elements(): array
    {
        return [
            '@name' => '#name',
            '@email' => '#email',
            '@role' => '#role',
            '@password' => '#password',
            '@password_confirmation' => '#password_confirmation',
            '@submit_button' => 'button[type="submit"]',
        ];
    }
}
