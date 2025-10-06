<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreWorkerRequest extends FormRequest
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
            'identification_number' => 'required|string|max:32|unique:workers,identification_number',
            'worker_name' => 'required|string|max:32',
            'mac_address' => 'nullable|mac_address|unique:workers,mac_address',
        ];
    }
}
