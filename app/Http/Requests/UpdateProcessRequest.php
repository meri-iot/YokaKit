<?php

namespace App\Http\Requests;

use App\Models\Process;
use App\Services\Utility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * 工程更新リクエスト
 *
 * @property integer $process_id 工程ID
 */
class UpdateProcessRequest extends FormRequest
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
            'process_name' => "required|string|max:32|unique:processes,process_name,{$this->process_id},process_id",
            'plan_color' => 'required|string|color',
            'count_switch' => 'required|boolean',
            'range' => ['required', 'integer', Rule::in(Utility::ranges())],
            'remark' => 'max:256',
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
            'count_switch' => !is_null($this->count_switch),
        ]);
    }
}
