<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * サイクルタイム追加リクエスト
 *
 * @property integer $process_id 工程ID
 */
class StoreCycleTimeRequest extends FormRequest
{
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
     * サイクルタイム追加リクエストのバリデーションルールを返す。
     *
     * 同一工程内での品番重複を禁止し、時間項目の範囲と前後関係を検証する。
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'part_number_id' => "required|integer|exists:part_numbers,part_number_id|unique:cycle_times,part_number_id,NULL,cycle_time_id,process_id,{$this->process_id}",
            'cycle_time' => 'required|numeric|min:2.000|max:86399.999',
            'over_time' => 'required|numeric|min:2.001|max:86400|gt:cycle_time',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $process = $this->route('process');
        if ($process instanceof Process) {
            // ルートの工程IDを内部的に付与し、工程単位ユニーク制約の条件に利用する。
            $this->merge(['process_id' => $process->process_id]);
        }
    }
}
