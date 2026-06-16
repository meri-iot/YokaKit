<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 作業者追加リクエスト
 */
class StoreWorkerRequest extends FormRequest
{
    /**
     * 作業者追加操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 作業者追加リクエストのバリデーションルールを返す。
     *
     * 識別番号は一意、MACアドレスは任意入力かつ形式・一意性を検証する。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'identification_number' => 'required|string|max:32|unique:workers,identification_number',
            'worker_name' => 'required|string|max:32',
            'mac_address' => 'nullable|mac_address|unique:workers,mac_address',
        ];
    }
}
