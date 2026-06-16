<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBooleanInput;
use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * 作業追加リクエスト
 *
 * @property integer $process_id 工程ID
 * @property integer $raspberry_pi_id ラズパイID
 */
class StoreLineRequest extends FormRequest
{
    use NormalizesBooleanInput;

    /**
     * 作業作成操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 作業作成リクエストのバリデーションルールを返す。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'line_name' => "required|string|max:32|unique:lines,line_name,NULL,line_id,process_id,{$this->process_id}",
            'chart_color' => 'required|string|color',
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'worker_id' => "nullable|integer|exists:workers,worker_id|unique:lines,worker_id,{$this->process_id},process_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'pin_number' => "required|integer|min:0|max:127|unique:lines,pin_number,NULL,line_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'defective' => 'required|boolean',
            'parent_id' => [
                'required_if:defective,true',
                'nullable',
                Rule::exists('lines', 'line_id')->where(function ($query) {
                    $query->where('process_id', $this->process_id);
                    $query->where('defective', false);
                })
            ]
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルートパラメータの工程IDを入力へ統合し、
     * `defective` の真偽値に応じて排他的な項目を正規化する。
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        /** @var Process */
        $process = $this->route('process');
        $defective = $this->normalizeBooleanInput('defective');
        $isDefective = $defective === true;

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'defective' => $defective,
            'worker_id' => $isDefective ? null : $this->worker_id,
            'parent_id' => $isDefective ? $this->parent_id : null,
        ]);
    }
}
