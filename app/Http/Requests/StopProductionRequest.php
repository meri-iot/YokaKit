<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

/**
 * 停止APIリクエスト
 *
 * 停止APIの実行には管理者権限が必要です。
 *
 * @property string $processName 停止対象の工程名
 */
class StopProductionRequest extends FormRequest
{
    /**
     * 停止APIの実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 停止APIリクエストのバリデーションルールを返す。
     *
     * JSONボディの processName に、実在する工程名を要求する。
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'processName' => 'required|string|exists:processes,process_name',
        ];
    }

    /**
     * バリデーション失敗時に400レスポンスを返す。
     *
     * Web APIとして利用するため、標準の422ではなく400でエラーを返却する。
     *
     * @param Validator $validator
     * @return void
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = response()->json(['errors' => $validator->errors()], 400);
        throw new HttpResponseException($response);
    }
}
