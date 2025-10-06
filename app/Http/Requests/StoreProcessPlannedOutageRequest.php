<?php

namespace App\Http\Requests;

use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 生産時の計画停止時間追加リクエスト
 *
 * @property integer $process_id 工程ID
 */
class StoreProcessPlannedOutageRequest extends FormRequest
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
            'planned_outage_id' => "required|integer|exists:planned_outages,planned_outage_id|unique:process_planned_outages,planned_outage_id,NULL,process_planned_outage_id,process_id,{$this->process_id}",
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
