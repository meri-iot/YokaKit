<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * 品番一覧画面のページオブジェクト
 */
class PartNumberIndexPage extends Page
{
    /**
     * ページの URL を返す
     *
     * @return string
     */
    public function url(): string
    {
        return '/yokakit/part-number';
    }

    /**
     * ブラウザがこのページにいることを検証する
     *
     * @param Browser $browser
     * @return void
     */
    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url());
    }

    /**
     * ページで使用する要素のショートカット一覧
     *
     * @return array<string,string>
     */
    public function elements(): array
    {
        return [
            '@create_button' => 'a.btn-primary',
            '@table'         => 'table.dataTable',
        ];
    }
}
