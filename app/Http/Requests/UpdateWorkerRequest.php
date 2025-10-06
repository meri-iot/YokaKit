<?php

namespace App\Http\Requests;

use App\Models\Worker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * 作業者更新リクエスト
 *
 * @property integer $worker_id 作業者ID
 */
class UpdateWorkerRequest extends FormRequest
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
            'identification_number' => "required|string|max:32|unique:workers,identification_number,{$this->worker_id},worker_id",
            'worker_name' => 'required|string|max:32',
            'mac_address' => "nullable|mac_address|unique:workers,mac_address,{$this->worker_id},worker_id",
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        /** @var Worker */
        $worker = $this->route('worker');
        // パラメータをマージ
        $this->merge(['worker_id' => $worker->worker_id]);
    }
}
