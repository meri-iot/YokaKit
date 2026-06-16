<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBooleanInput;
use App\Models\Process;
use App\Services\Utility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * 工程更新リクエスト
 *
 * 工程名、計画値色、カウント切替、表示範囲、備考の更新入力を検証します。
 * 管理者権限が必要です。
 *
 * @property integer $process_id 工程ID
 */
class UpdateProcessRequest extends FormRequest
{
    use NormalizesBooleanInput;

    /**
     * ユーザーがこのリクエストを実行する権限があるかを判定
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 本リクエストに適用される検証ルール
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'process_name' => "required|string|max:32|unique:processes,process_name,{$this->process_id},process_id",
            'plan_color' => 'required|string|color',
            'count_switch' => 'required|boolean',
            'range' => ['required', 'integer', Rule::in(Utility::ganttChartDisplayRangeMinutes())],
            'remark' => 'nullable|string|max:256',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルートの工程IDを統合し、count_switch を boolean へ正規化する。
     * ルートパラメータが欠落している場合は処理をスキップする。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $process = $this->route('process');

        if (!($process instanceof Process)) {
            return;
        }

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'count_switch' => $this->normalizeBooleanInput('count_switch'),
        ]);
    }
}
