<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RawContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'blueprint_id' => [
                'required',
                'integer',
                // Ownership guard: the blueprint must belong to the current user.
                Rule::exists('blueprints', 'id')->where('user_id', $this->user()->id),
            ],
            'body' => ['required', 'string', 'min:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'blueprint_id.exists' => 'The selected blueprint does not exist or does not belong to you.',
        ];
    }
}
