<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Utility;

/**
 * サーバー時刻コントローラー
 */
class ServerDateController extends BaseController
{
    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * 現在のサーバー時刻を文字列で返す。
     *
     * @return string
     */
    public function __invoke(): string
    {
        return Utility::format(Utility::now());
    }
}
