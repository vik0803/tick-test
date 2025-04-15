<?php

namespace Modules\IntelliReply\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSettings extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'settings.ai_assistant' => 'required',
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'settings.ai_assistant.required' => __('This field is required.'),
        ];
    }
}
