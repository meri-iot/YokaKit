<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\OnOff;
use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * ON-OFFスイッチ更新リクエスト
 *
 * 工程に紐づく ON-OFF イベント定義を更新するための入力を検証します。
 * 管理者権限が必要です。
 *
 * @property integer $on_off_id ON-OFFスイッチID
 * @property integer $process_id 工程ID
 */
class UpdateOnOffRequest extends FormRequest
{
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
            'event_name' => "required|string|max:64|unique:on_offs,event_name,{$this->on_off_id},on_off_id,process_id,{$this->process_id}",
            'on_message' => 'required|max:64|string',
            'off_message' => 'nullable|max:64|string',
            'pin_number' => "required|integer|min:0|max:127|unique:on_offs,pin_number,{$this->on_off_id},on_off_id,process_id,{$this->process_id}",
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルートから工程IDとON-OFFスイッチIDを補完する。
     * ルートパラメータが欠ける場合は処理をスキップする。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $process = $this->route('process');
        $onOff = $this->route('onOff');

        if (!($process instanceof Process) || !($onOff instanceof OnOff)) {
            return;
        }

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'on_off_id' => $onOff->on_off_id,
        ]);
    }
}
