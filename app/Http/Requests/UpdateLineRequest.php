<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBooleanInput;
use App\Models\Line;
use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * 作業設定更新リクエスト
 *
 * 作業の名称・色・ピン番号・担当者・不良作業設定を更新します。
 * 管理者権限が必要です。
 *
 * @property integer $line_id 作業ID
 * @property integer $process_id 工程ID
 * @property integer $raspberry_pi_id ラズパイID
 */
class UpdateLineRequest extends FormRequest
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
        $rule = [
            'line_name' => "required|string|max:32|unique:lines,line_name,{$this->line_id},line_id,process_id,{$this->process_id}",
            'chart_color' => 'required|string|color',
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'worker_id' => "nullable|integer|exists:workers,worker_id|unique:lines,worker_id,{$this->line_id},line_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'pin_number' => "required|integer|min:0|max:127|unique:lines,pin_number,{$this->line_id},line_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'defective' => 'required|boolean',
            'parent_id' => [
                'required_if:defective,true',
                'nullable',
                'different:line_id',
                Rule::exists('lines', 'line_id')->where(function ($query) {
                    $query->where('process_id', $this->process_id);
                    $query->where('defective', false);
                })
            ]
        ];

        if ($this->defective === true) {
            $rule['defective'] .= "|unique:lines,parent_id,NULL,line_id,parent_id,{$this->line_id}";
        }

        return $rule;
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルート情報と boolean 入力を正規化し、
     * defective の値に応じて排他的な項目を整形する。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $process = $this->route('process');
        $line = $this->route('line');

        if (!($process instanceof Process) || !($line instanceof Line)) {
            return;
        }

        $defective = $this->normalizeBooleanInput('defective');
        $isDefective = $defective === true;

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'line_id' => $line->line_id,
            'defective' => $defective,
            'worker_id' => $isDefective ? null : $this->worker_id,
            'parent_id' => $isDefective ? $this->parent_id : null,
        ]);
    }
}
