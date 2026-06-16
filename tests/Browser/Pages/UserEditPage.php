<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * ユーザープロフィール編集画面のページオブジェクト
 */
class UserEditPage extends Page
{
    public function url(): string
    {
        return '/yokakit/user/edit';
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
            '@submit_button' => 'button[type="submit"]',
        ];
    }
}
