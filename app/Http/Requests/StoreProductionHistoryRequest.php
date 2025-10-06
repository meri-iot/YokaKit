<?php

namespace App\Http\Requests;

use App\Enums\ProductionStatus;
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
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string,mixed>
     */
    public function rules()
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
     * @return void
     */
    protected function prepareForValidation()
    {
        /** @var Process */
        $process = $this->route('process');
        $this->merge([
            'process_id' => $process->process_id,
            'status' => is_null($this->changeover) ?  ProductionStatus::RUNNING() : ProductionStatus::CHANGEOVER(),
        ]);
    }
}
