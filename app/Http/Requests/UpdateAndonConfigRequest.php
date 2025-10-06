<?php

namespace App\Http\Requests;

use App\Enums\AndonColumnSize;
use App\Enums\EasingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAndonConfigRequest extends FormRequest
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
            'row_count' => 'required|integer|between:1,100',
            'column_count' => ['required', 'integer', Rule::in(AndonColumnSize::getValues())],
            'auto_play_speed' => 'required|integer|between:0,3600000',
            'slide_speed' => 'required|integer|between:0,3600000',
            'easing' => ['required', 'string', Rule::in(EasingType::getValues())],
            'layouts' => 'nullable|array',
            'layouts.*.display' => 'nullable|integer',
            'layouts.*.process_id' => 'required|integer|exists:processes,process_id',
            'item_column_count' => ['required', 'integer', Rule::in(AndonColumnSize::getValues())],
            'is_show_part_number' => 'required|boolean',
            'is_show_start' => 'required|boolean',
            'is_show_good_count' => 'required|boolean',
            'is_show_good_rate' => 'required|boolean',
            'is_show_defective_count' => 'required|boolean',
            'is_show_defective_rate' => 'required|boolean',
            'is_show_plan_count' => 'required|boolean',
            'is_show_achievement_rate' => 'required|boolean',
            'is_show_cycle_time' => 'required|boolean',
            'is_show_time_operating_rate' => 'required|boolean',
            'is_show_performance_operating_rate' => 'required|boolean',
            'is_show_overall_equipment_effectiveness' => 'required|boolean',
            'is_show_goal' => 'required|boolean',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // パラメータをマージ
        $this->merge([
            'is_show_part_number' => !is_null($this->is_show_part_number),
            'is_show_start' => !is_null($this->is_show_start),
            'is_show_good_count' => !is_null($this->is_show_good_count),
            'is_show_good_rate' => !is_null($this->is_show_good_rate),
            'is_show_defective_count' => !is_null($this->is_show_defective_count),
            'is_show_defective_rate' => !is_null($this->is_show_defective_rate),
            'is_show_plan_count' => !is_null($this->is_show_plan_count),
            'is_show_achievement_rate' => !is_null($this->is_show_achievement_rate),
            'is_show_cycle_time' => !is_null($this->is_show_cycle_time),
            'is_show_time_operating_rate' => !is_null($this->is_show_time_operating_rate),
            'is_show_performance_operating_rate' => !is_null($this->is_show_performance_operating_rate),
            'is_show_overall_equipment_effectiveness' => !is_null($this->is_show_overall_equipment_effectiveness),
            'is_show_goal' => !is_null($this->is_show_goal),
        ]);
    }
}
