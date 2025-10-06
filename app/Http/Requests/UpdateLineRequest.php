<?php

namespace App\Http\Requests;

use App\Models\Line;
use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * ライン更新リクエスト
 *
 * @property integer $line_id ラインID
 * @property integer $process_id 工程ID
 * @property integer $raspberry_pi_id ラズパイID
 */
class UpdateLineRequest extends FormRequest
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
        $rule = [
            'line_name' => "required|string|max:32|unique:lines,line_name,{$this->line_id},line_id,process_id,{$this->process_id}",
            'chart_color' => 'required|string|color',
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'worker_id' => "nullable|integer|exists:workers,worker_id|unique:lines,worker_id,{$this->process_id},process_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'pin_number' => "required|integer|min:0|max:127|unique:lines,pin_number,{$this->line_id},line_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'defective' => 'required|boolean',
            'parent_id' => [
                'required_if:defective,true',
                'nullable',
                'different:line_id',
                Rule::exists('lines', 'line_id')->where(function ($query) {
                    $query->where('process_id', $this->process_id);
                    $query->where('defective', false);
                })
            ]
        ];

        if ($this->defective === true) {
            $rule['defective'] .= "|unique:lines,parent_id,NULL,line_id,parent_id,{$this->line_id}";
        }

        return $rule;
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
        /** @var Line */
        $line =  $this->route('line');
        $defective = !is_null($this->defective);

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'line_id' => (string) $line->line_id,
            'defective' => $defective,
            'worker_id' => $defective ? null : $this->worker_id,
            'parent_id' => $defective ? $this->parent_id : null,
        ]);
    }
}
