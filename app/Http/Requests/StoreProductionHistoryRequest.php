<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProductionStatus;
use App\Http\Requests\Concerns\NormalizesBooleanInput;
use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 生産履歴データ保存リクエスト
 *
 * @property integer $part_number_id 品番ID
 * @property ProductionStatus $status ステータス
 * @property integer $goal 目標値
 */
class StoreProductionHistoryRequest extends FormRequest
{
    use NormalizesBooleanInput;

    /**
     * 生産履歴データ保存の実行可否を判定する。
     *
     * API経由の受付を想定し、認可は上位層で制御する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 生産履歴データ保存のバリデーションルールを返す。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'part_number_id' => [
                'required',
                'integer',
                Rule::exists('cycle_times', 'part_number_id')->where(function ($query) {
                    $query->where('process_id', $this->process_id);
                })
            ],
            'status' =>  [
                'required',
                Rule::in([ProductionStatus::RUNNING(), ProductionStatus::CHANGEOVER()]),
            ],
            'goal' => 'nullable|integer|gte:0|lte:2147483647',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルートの工程IDを入力へ統合し、changeover の真偽値に応じて
     * status を RUNNING / CHANGEOVER に正規化する。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $process = $this->route('process');
        if (!$process instanceof Process) {
            return;
        }

        $isChangeover = $this->normalizeBooleanInput('changeover') === true;
        $goal = $this->input('goal');

        $this->merge([
            'process_id' => $process->process_id,
            'part_number_id' => (int) $this->part_number_id,
            'goal' => is_null($goal) ? null : (int) $goal,
            'status' => $isChangeover ? ProductionStatus::CHANGEOVER() : ProductionStatus::RUNNING(),
        ]);
    }
}
