<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class SwitchPartNumberRequestFromApi extends FormRequest
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
            'processName' => 'required|string|exists:processes,process_name',
            'partNumberName' => 'required|string|exists:part_numbers,part_number_name',
            'goal' => 'nullable|integer|gte:0|lte:2147483647',
            'force' => 'required|boolean',
            'changeover' => 'required|boolean',
        ];
    }

    /**
     * バリデーションのためのデータの準備
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'force' => is_null($this->force) ? true : $this->force,
            'changeover' => is_null($this->changeover) ? true : $this->changeover,
        ]);
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json(['errors' => $validator->errors()], 400);
        throw new HttpResponseException($response);
    }
}
