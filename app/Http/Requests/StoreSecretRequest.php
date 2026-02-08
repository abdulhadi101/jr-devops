<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSecretRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:604800'], // 1 min to 7 days
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Secret content is required',
            'content.max' => 'Secret content cannot exceed 10,000 characters',
            'ttl.min' => 'TTL must be at least 60 seconds',
            'ttl.max' => 'TTL cannot exceed 7 days (604800 seconds)',
        ];
    }
}
