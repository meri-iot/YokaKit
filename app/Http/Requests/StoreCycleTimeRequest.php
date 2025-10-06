<?php

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
            'part_number_id' => "required|exists:part_numbers,part_number_id|unique:cycle_times,part_number_id,NULL,cycle_time_id,process_id,{$this->process_id}",
            'cycle_time' => 'required|numeric|min:2.000|max:86399.999',
            'over_time' => 'required|numeric|min:2.001|max:86400|gt:cycle_time',
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
        $this->merge(['process_id' => $process->process_id]);
    }
}
