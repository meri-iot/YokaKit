<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBooleanInput;
use App\Models\Process;
use App\Models\Sensor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * センサー更新リクエスト
 *
 * センサーの識別番号、アラーム文言、トリガー条件を更新する入力を検証します。
 * 管理者権限が必要です。
 *
 * @property integer $process_id 工程ID
 * @property integer $sensor_id センサーID
 * @property integer $raspberry_pi_id ラズパイID
 */
class UpdateSensorRequest extends FormRequest
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
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'identification_number' => "required|integer|between:0,127|unique:sensors,identification_number,{$this->sensor_id},sensor_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'alarm_text' => 'required|max:128|string',
            'trigger' => 'required|boolean',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルート情報を入力へ統合し、trigger を boolean として正規化する。
     * ルートパラメータが欠落している場合は処理をスキップする。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $process = $this->route('process');
        $sensor = $this->route('sensor');

        if (!($process instanceof Process) || !($sensor instanceof Sensor)) {
            return;
        }

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'sensor_id' => $sensor->sensor_id,
            'trigger' => $this->normalizeBooleanInput('trigger'),
        ]);
    }
}
