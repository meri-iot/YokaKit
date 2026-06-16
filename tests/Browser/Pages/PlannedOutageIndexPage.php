<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * 計画停止時間一覧画面のページオブジェクト
 */
class PlannedOutageIndexPage extends Page
{
    public function url(): string
    {
        return '/yokakit/planned-outage';
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
