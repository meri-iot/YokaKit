<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * 計画停止時間編集画面のページオブジェクト
 */
class PlannedOutageEditPage extends Page
{
    private int $plannedOutageId;

    public function __construct(int $plannedOutageId)
    {
        $this->plannedOutageId = $plannedOutageId;
    }

    public function url(): string
    {
        return "/yokakit/planned-outage/{$this->plannedOutageId}/edit";
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
