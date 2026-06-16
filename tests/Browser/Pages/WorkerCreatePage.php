<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * 作業者追加画面のページオブジェクト
 */
class WorkerCreatePage extends Page
{
    public function url(): string
    {
        return '/yokakit/worker/create';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url());
    }

    public function elements(): array
    {
        return [
            '@identification_number' => '#identification_number',
            '@worker_name' => '#worker_name',
            '@mac_address' => '#mac_address',
            '@submit_button' => 'button[type="submit"]',
        ];
    }
}
