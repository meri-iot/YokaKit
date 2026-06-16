<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * ユーザー追加リクエスト
 *
 * @property string $name 氏名
 * @property string $email メールアドレス
 * @property string $password パスワード
 * @property string|integer $role 権限
 */
class StoreUserRequest extends FormRequest
{
    /**
     * ユーザー追加操作の実行権限を判定する。
     *
     * システム管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('system');
    }

    /**
     * ユーザー追加リクエストのバリデーションルールを返す。
     *
     * メールアドレス一意性、権限値、パスワード確認一致を検証する。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:255',
            'email' => 'required|string|min:3|max:255|email|unique:users,email',
            'role' => ['required', 'integer', Rule::in(RoleType::getValues())],
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ];
    }
}
