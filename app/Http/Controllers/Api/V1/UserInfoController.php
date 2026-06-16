<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ユーザー情報を取得するAPIコントローラークラス
 */
class UserInfoController extends BaseController
{
    /**
     * 受信したリクエストを処理し、ログイン中ユーザー情報を返す。
     *
     * 管理者権限を確認したうえで現在の認証ユーザーを返却する。
     *
     * @param Request $request
     * @return User|null
     */
    public function __invoke(Request $request): ?User
    {
        $this->authorizeAdmin();
        return Auth::user();
    }
}
