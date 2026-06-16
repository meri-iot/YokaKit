<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * ラズパイ編集画面のページオブジェクト
 */
class RaspberryPiEditPage extends Page
{
    private int $raspberryPiId;

    public function __construct(int $raspberryPiId)
    {
        $this->raspberryPiId = $raspberryPiId;
    }

    public function url(): string
    {
        return "/yokakit/raspberry-pi/{$this->raspberryPiId}/edit";
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
