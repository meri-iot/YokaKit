<?php

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
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::check('system');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string,mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|min:1|max:255',
            'email' => 'required|string|min:3|max:255|email|unique:users,email',
            'role' => ['required', Rule::in(RoleType::getInstances())],
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ];
    }
}
