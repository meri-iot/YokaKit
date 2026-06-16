<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GanttChartType;
use App\Http\Requests\Concerns\NormalizesBooleanInput;
use App\Models\Process;
use App\Rules\NotExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * ガントチャート追加リクエスト
 *
 * ガントチャートの追加操作を検証します。
 * 管理者権限が必要です。
 *
 * @property int $raspberry_pi_id ラズパイID
 * @property int $pin_number ピン番号
 * @property string $chart_name チャート名
 * @property string $chart_color チャート色
 * @property GanttChartType $chart_type チャート種別
 * @property bool $trigger トリガー
 */
class StoreGanttChartRequest extends FormRequest
{
    use NormalizesBooleanInput;

    /**
     * 追加操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * ガントチャート追加リクエストのバリデーションルールを返す。
     *
     * 工程内のチャート名重複、ラズパイ内のピン重複、
     * そして工程内のBASE種別重複を禁止する。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'pin_number' => "required|integer|between:0,127|unique:gantt_charts,pin_number,NULL,gantt_chart_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'chart_name' => "required|string|max:32|unique:gantt_charts,chart_name,NULL,gantt_chart_id,process_id,{$this->process_id}",
            'chart_color' => 'required|string|color',
            'chart_type' => [
                'required',
                Rule::in(GanttChartType::getInstances()),
                new NotExists('gantt_charts', 'chart_type', GanttChartType::BASE(), ['process_id' => $this->process_id]),
            ],
            'trigger' => 'required|boolean',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルート情報を取り込み、trigger を boolean として正規化する。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $process = $this->route('process');
        $merged = [
            'trigger' => $this->normalizeBooleanInput('trigger'),
        ];

        if ($process instanceof Process) {
            $merged['process_id'] = $process->process_id;
        }

        // ルート情報とチェックボックス入力をバリデーション前データに正規化する。
        $this->merge($merged);
    }
}
