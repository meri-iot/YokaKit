<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * ON-OFFスイッチデータ追加リクエスト
 *
 * @property integer $process_id 工程ID
 */
class StoreOnOffRequest extends FormRequest
{
    /**
     * ON-OFFスイッチ追加操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * ON-OFFスイッチ追加リクエストのバリデーションルールを返す。
     *
     * イベント名とピン番号は工程単位で一意に制約する。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'event_name' => "required|string|max:64|unique:on_offs,event_name,NULL,on_off_id,process_id,{$this->process_id}",
            'on_message' => 'required|max:64|string',
            'off_message' => 'nullable|max:64|string',
            'pin_number' => "required|integer|min:0|max:127|unique:on_offs,pin_number,NULL,on_off_id,process_id,{$this->process_id}",
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルートパラメータの工程IDを入力へ統合し、
     * 工程単位のユニーク制約に利用できるようにする。
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        /** @var Process */
        $process = $this->route('process');

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
        ]);
    }
}
