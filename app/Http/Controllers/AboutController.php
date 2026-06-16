<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * YokaKitについてのコントローラー
 */
class AboutController extends BaseController
{
    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * 受信したリクエストを処理し、About画面を返す。
     *
     * 本画面は認証済みユーザーのみ閲覧可能。
     *
     * @param Request $request
     * @return View
     */
    public function __invoke(Request $request): View
    {
        return view('about');
    }
}
