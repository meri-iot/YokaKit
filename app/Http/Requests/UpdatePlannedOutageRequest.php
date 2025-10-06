<?php

namespace App\Http\Requests;

use App\Models\PlannedOutage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 計画停止時間更新リクエスト
 *
 * @property integer $planned_outage_id 計画停止時間ID
 */
class UpdatePlannedOutageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::check('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string,mixed>
     */
    public function rules()
    {
        return [
            'planned_outage_name' => "required|string|unique:planned_outages,planned_outage_name,{$this->planned_outage_id},planned_outage_id|max:32",
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|different:start_time'
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        /** @var PlannedOutage */
        $plannedOutage = $this->route('plannedOutage');
        // パラメータをマージ
        $this->merge(['planned_outage_id' => $plannedOutage->planned_outage_id]);
    }
}
