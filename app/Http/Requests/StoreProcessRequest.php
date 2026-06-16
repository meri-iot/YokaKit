<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBooleanInput;
use App\Services\Utility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * 工程追加リクエスト
 */
class StoreProcessRequest extends FormRequest
{
    use NormalizesBooleanInput;

    /**
     * 工程追加操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 工程追加リクエストのバリデーションルールを返す。
     *
     * 工程名の一意性、色指定、集計レンジ、備考文字数を検証する。
     * 備考は未入力を許可しつつ、文字列のみを受け付ける。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'process_name' => 'required|string|max:32|unique:processes,process_name',
            'plan_color' => 'required|string|color',
            'count_switch' => 'required|boolean',
            'range' => ['required', 'integer', Rule::in(Utility::ganttChartDisplayRangeMinutes())],
            'remark' => 'nullable|string|max:256',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * count_switch を boolean として正規化し、
     * 変換不能値はそのまま残してバリデーションで検出する。
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // パラメータをマージ
        $this->merge([
            'count_switch' => $this->normalizeBooleanInput('count_switch'),
        ]);
    }
}
