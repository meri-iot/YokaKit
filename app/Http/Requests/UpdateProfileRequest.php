<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * ユーザープロファイル更新リクエスト
 *
 * ログイン中ユーザーの表示名とメールアドレスの更新入力を検証します。
 *
 * @property integer $user_id ユーザーID
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを実行する権限があるかを判定
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * 本リクエストに適用される検証ルール
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:255',
            'email' => "required|string|min:3|max:255|email|unique:users,email,{$this->user_id},id",
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * 現在ログイン中のユーザーIDを更新対象として補完する。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // パラメータをマージ
        $this->merge(['user_id' => Auth::id()]);
    }
}
