<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GanttChartType;
use App\Http\Requests\Concerns\NormalizesBooleanInput;
use App\Models\GanttChart;
use App\Models\Process;
use App\Rules\NotExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * ガントチャート設定の更新リクエスト
 *
 * ガントチャートの表示設定（ラズベリーパイとのマッピング、ピン番号、名前、色、タイプ、トリガー設定）を検証します。
 * 同一プロセス内での重複チェック、列挙型の制約チェックを行います。
 * 管理者権限が必要です。
 */
class UpdateGanttChartRequest extends FormRequest
{
    use NormalizesBooleanInput;

    /**
     * ユーザーがこのリクエストを実行する権限があるかを判定
     *
     * 管理者のみがガントチャート設定を更新できます。
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 本リクエストに適用される検証ルール
     *
     * - raspberry_pi_id: ラズベリーパイ ID の存在確認
     * - pin_number: 0～127 のピン番号、同じラズベリーパイ内で一意
     * - chart_name: 最大 32 文字、同一プロセス内で一意
     * - chart_color: CSS カラー値
     * - chart_type: ガントチャートタイプ、BASE タイプとの重複不可
     * - trigger: boolean フラグ
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'pin_number' => "required|integer|between:0,127|unique:gantt_charts,pin_number,{$this->gantt_chart_id},gantt_chart_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'chart_name' => "required|string|max:32|unique:gantt_charts,chart_name,{$this->gantt_chart_id},gantt_chart_id,process_id,{$this->process_id}",
            'chart_color' => 'required|string|color',
            'chart_type' => [
                'required',
                Rule::in(GanttChartType::getInstances()),
                new NotExists('gantt_charts', 'chart_type', GanttChartType::BASE(), ['process_id' => $this->process_id], ['gantt_chart_id' => $this->gantt_chart_id]),
            ],
            'trigger' => 'required|boolean',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルート情報を入力へ統合し、trigger を boolean として正規化する。
     * ルートパラメータが利用不可の場合は処理をスキップして例外を防ぐ。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // ルートパラメータの取得と型チェック
        $process = $this->route('process');
        $ganttChart = $this->route('ganttChart');

        // ルートの安全性確保：パラメータが利用可能かつ正しい型であることを確認
        if (!($process instanceof Process) || !($ganttChart instanceof GanttChart)) {
            return;
        }

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $ganttChart->gantt_chart_id,
            'trigger' => $this->normalizeBooleanInput('trigger'),
        ]);
    }
}
