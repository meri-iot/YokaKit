<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;

/**
 * アンドン設定編集画面のページオブジェクト
 */
class AndonConfigPage extends Page
{
    /**
     * ページの URL を返す
     *
     * @return string
     */
    public function url(): string
    {
        return '/yokakit/home/edit';
    }

    /**
     * ブラウザがこのページにいることを検証する
     *
     * @param  Browser $browser
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
            '@column_count'      => '#column_count',
            '@item_column_count' => '#item_column_count',
            '@sortable'          => '#sortable',
            '@save_button'       => 'button[type="submit"]',
        ];
    }

    /**
     * プロセスコンテナのセレクターを返す
     *
     * @param  int $processId
     * @return string
     */
    public static function processContainer(int $processId): string
    {
        return "#process-{$processId}";
    }

    /**
     * プロセス表示切り替えチェックボックスのセレクターを返す
     *
     * @param  int $processId
     * @return string
     */
    public static function displayCheckbox(int $processId): string
    {
        return "input[name=\"layouts[{$processId}][display]\"]";
    }
}
