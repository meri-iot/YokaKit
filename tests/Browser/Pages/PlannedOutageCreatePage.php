<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * 計画停止時間追加画面のページオブジェクト
 */
class PlannedOutageCreatePage extends Page
{
    public function url(): string
    {
        return '/yokakit/planned-outage/create';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url());
    }

    public function elements(): array
    {
        return [
            '@planned_outage_name' => '#planned_outage_name',
            '@start_time' => '#start_time',
            '@end_time' => '#end_time',
            '@submit_button' => 'button[type="submit"]',
        ];
    }
}
