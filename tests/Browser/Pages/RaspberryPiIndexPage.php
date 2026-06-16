<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * ラズパイ一覧画面のページオブジェクト
 */
class RaspberryPiIndexPage extends Page
{
    public function url(): string
    {
        return '/yokakit/raspberry-pi';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url());
    }

    public function elements(): array
    {
        return [
            '@create_button' => 'a.btn-primary',
            '@table' => 'table.dataTable',
        ];
    }
}
