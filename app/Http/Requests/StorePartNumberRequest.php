<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 品番追加リクエスト
 */
class StorePartNumberRequest extends FormRequest
{
    /**
     * 品番追加操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 品番追加リクエストのバリデーションルールを返す。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'part_number_name' => 'required|string|max:32|unique:part_numbers,part_number_name',
            'barcode' => 'nullable|string|max:64|unique:part_numbers,barcode',
            'remark' => 'nullable|max:256',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * 任意入力の空文字を null に正規化し、
     * 未設定項目として扱えるようにする。
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'barcode' => $this->filled('barcode') ? $this->input('barcode') : null,
            'remark' => $this->filled('remark') ? $this->input('remark') : null,
        ]);
    }
}
