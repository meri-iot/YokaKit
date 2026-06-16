<?php

declare(strict_types=1);

namespace App\Http\Controllers\DataTables;

use App\Http\Controllers\BaseController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * データテーブル用言語取得コントローラー
 */
class DataTablesLocaleController extends BaseController
{
    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * DataTablesの言語設定を現在ロケールに合わせて返す。
     *
     * resources/lang/{locale}/datatables.php 相当の配列から app.locale のキーを参照し、
     * 対応するDataTables文言配列を返却する。未定義ロケールの場合は404を返す。
     *
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $config = __('datatables');
        $locale = config('app.locale');
        if (array_key_exists($locale, $config)) {
            return $config[$locale];
        } else {
            throw new NotFoundHttpException();
        }
    }
}
