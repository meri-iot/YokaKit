<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * パスワード更新リクエスト
 *
 * 現在のログインユーザーが自身のパスワードを変更する際の入力を検証する。
 *
 * @property string $current_password 現在のパスワード
 * @property string $password 新しいパスワード
 * @property string $password_confirmation 新しいパスワード(確認)
 */
class UpdatePasswordRequest extends FormRequest
{
    /**
     * リクエスト実行権限の判定。
     *
     * ルート側で auth ミドルウェアが適用されていても、
     * Request 単体として未ログインを拒否できるようにしておく。
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * バリデーションルール。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ];
    }
}
