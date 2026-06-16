<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * ラズパイ追加画面のページオブジェクト
 */
class RaspberryPiCreatePage extends Page
{
    public function url(): string
    {
        return '/yokakit/raspberry-pi/create';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url());
    }

    public function elements(): array
    {
        return [
            '@raspberry_pi_name' => '#raspberry_pi_name',
            '@ip_address' => '#ip_address',
            '@submit_button' => 'button[type="submit"]',
        ];
    }
}
