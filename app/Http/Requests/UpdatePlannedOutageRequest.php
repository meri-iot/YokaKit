<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PlannedOutage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 計画停止時間更新リクエスト
 *
 * 計画停止時間名と開始/終了時刻の更新入力を検証します。
 * 管理者権限が必要です。
 *
 * @property integer $planned_outage_id 計画停止時間ID
 */
class UpdatePlannedOutageRequest extends FormRequest
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
            'planned_outage_name' => "required|string|unique:planned_outages,planned_outage_name,{$this->planned_outage_id},planned_outage_id|max:32",
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|different:start_time',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * ルートから更新対象IDを補完する。
     * ルートパラメータが欠落している場合は処理をスキップする。
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $plannedOutage = $this->route('plannedOutage');

        if (!($plannedOutage instanceof PlannedOutage)) {
            return;
        }

        // パラメータをマージ
        $this->merge(['planned_outage_id' => $plannedOutage->planned_outage_id]);
    }
}
