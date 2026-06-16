<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * ラズパイ追加リクエスト
 */
class StoreRaspberryPiRequest extends FormRequest
{
    /**
     * ラズパイ追加操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * ラズパイ追加リクエストのバリデーションルールを返す。
     *
     * ラズパイ名とIPアドレスの一意性を検証する。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'raspberry_pi_name' => 'required|string|max:32|unique:raspberry_pis,raspberry_pi_name',
            'ip_address' => 'required|ip|unique:raspberry_pis,ip_address',
        ];
    }
}
