<?php

namespace App\Http\Requests;

use App\Enums\GanttChartType;
use App\Models\Process;
use App\Rules\NotExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreGanttChartRequest extends FormRequest
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
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'pin_number' => "required|integer|between:0,127|unique:gantt_charts,pin_number,NULL,gantt_chart_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'chart_name' => "required|string|max:32|unique:gantt_charts,chart_name,NULL,gantt_chart_id,process_id,{$this->process_id}",
            'chart_color' => 'required|string|color',
            'chart_type' => [
                'required',
                Rule::in(GanttChartType::getInstances()),
                new NotExists('gantt_charts', 'chart_type', GanttChartType::BASE(), ['process_id' => $this->process_id]),
            ],
            'trigger' => 'required|boolean',
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

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'trigger' => !is_null($this->trigger),
        ]);
    }
}
