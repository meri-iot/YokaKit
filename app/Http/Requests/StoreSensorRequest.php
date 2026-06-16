<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBooleanInput;
use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * センサー追加リクエスト
 *
 * @property integer $raspberry_pi_id ラズパイID
 */
class StoreSensorRequest extends FormRequest
{
    use NormalizesBooleanInput;

    /**
     * センサー追加操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * センサー追加リクエストのバリデーションルールを返す。
     *
     * 識別番号はラズパイ単位で一意に制約し、trigger は boolean を要求する。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'identification_number' => "required|integer|between:0,127|unique:sensors,identification_number,NULL,sensor_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'alarm_text' => 'required|max:128|string',
            'trigger' => 'required|boolean',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルート情報と boolean 入力を正規化して検証データに統合する。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $process = $this->route('process');
        if (!$process instanceof Process) {
            return;
        }

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'trigger' => $this->normalizeBooleanInput('trigger'),
        ]);
    }
}
