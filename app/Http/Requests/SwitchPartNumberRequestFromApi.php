<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBooleanInput;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

/**
 * API用の品番切替リクエスト
 */
class SwitchPartNumberRequestFromApi extends FormRequest
{
    use NormalizesBooleanInput;

    /**
     * API経由の品番切替操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * API用の品番切替バリデーションルールを返す。
     *
     * 工程名・品番名の存在性、目標値範囲、切替フラグの真偽値を検証する。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'processName' => 'required|string|exists:processes,process_name',
            'partNumberName' => 'required|string|exists:part_numbers,part_number_name',
            'goal' => 'nullable|integer|gte:0|lte:2147483647',
            'force' => 'required|boolean',
            'changeover' => 'required|boolean',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * force/changeover が未指定なら true を補完し、
     * 指定時は boolean として正規化する。
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'force' => $this->has('force') ? $this->normalizeBooleanInput('force') : true,
            'changeover' => $this->has('changeover') ? $this->normalizeBooleanInput('changeover') : true,
        ]);
    }

    /**
     * バリデーション失敗時に400 JSONレスポンス例外を投げる。
     *
     * @param Validator $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = response()->json(['errors' => $validator->errors()], 400);
        throw new HttpResponseException($response);
    }
}
