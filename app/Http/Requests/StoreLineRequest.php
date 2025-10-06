<?php

namespace App\Http\Requests;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * ライン追加リクエスト
 *
 * @property integer $process_id 工程ID
 * @property integer $raspberry_pi_id ラズパイID
 */
class StoreLineRequest extends FormRequest
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
            'line_name' => "required|string|max:32|unique:lines,line_name,NULL,line_id,process_id,{$this->process_id}",
            'chart_color' => 'required|string|color',
            'raspberry_pi_id' => 'required|integer|exists:raspberry_pis,raspberry_pi_id',
            'worker_id' => "nullable|integer|exists:workers,worker_id|unique:lines,worker_id,{$this->process_id},process_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'pin_number' => "required|integer|min:0|max:127|unique:lines,pin_number,NULL,line_id,raspberry_pi_id,{$this->raspberry_pi_id}",
            'defective' => 'required|boolean',
            'parent_id' => [
                'required_if:defective,true',
                'nullable',
                Rule::exists('lines', 'line_id')->where(function ($query) {
                    $query->where('process_id', $this->process_id);
                    $query->where('defective', false);
                })
            ]
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
        $defective = !is_null($this->defective);

        // パラメータをマージ
        $this->merge([
            'process_id' => $process->process_id,
            'defective' => $defective,
            'worker_id' => $defective ? null : $this->worker_id,
            'parent_id' => $defective ? $this->parent_id : null,
        ]);
    }
}
