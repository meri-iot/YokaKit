<?php

namespace App\Http\Requests;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * ON-OFFスイッチデータ追加リクエスト
 *
 * @property integer $process_id 工程ID
 */
class StoreOnOffRequest extends FormRequest
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
            'event_name' => "required|string|max:64|unique:on_offs,event_name,NULL,on_off_id,process_id,{$this->process_id}",
            'on_message' => 'required|max:64|string',
            'off_message' => 'nullable|max:64|string',
            'pin_number' => "required|integer|min:0|max:127|unique:on_offs,pin_number,NULL,on_off_id,process_id,{$this->process_id}",
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
        ]);
    }
}
