<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 生産時の計画停止時間追加リクエスト
 *
 * @property integer $process_id 工程ID
 */
class StoreProcessPlannedOutageRequest extends FormRequest
{
    /**
     * 生産時の計画停止時間追加操作の実行権限を判定する。
     *
     * 管理者権限を持つユーザーのみ許可する。
     */
    public function authorize(): bool
    {
        return Gate::check('admin');
    }

    /**
     * 生産時の計画停止時間追加リクエストのバリデーションルールを返す。
     *
     * 同一工程内で同じ計画停止時間の重複登録を禁止する。
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'planned_outage_id' => "required|integer|exists:planned_outages,planned_outage_id|unique:process_planned_outages,planned_outage_id,NULL,process_planned_outage_id,process_id,{$this->process_id}",
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
        $process = $this->route('process');
        if (!$process instanceof Process) {
            return;
        }

        // パラメータをマージ
        $this->merge(['process_id' => $process->process_id]);
    }
}
