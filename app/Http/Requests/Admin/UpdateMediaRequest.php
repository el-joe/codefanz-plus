<?php

namespace App\Http\Requests\Admin;

use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'alt' => ['required', 'array'],
            'folder' => ['nullable', 'string', 'max:60', 'alpha_dash'],
        ];

        foreach (Language::active()->keys() as $code) {
            $rules["alt.{$code}"] = ['nullable', 'string', 'max:255'];
        }

        $rules['alt.'.Language::defaultCode()] = ['required', 'string', 'max:255'];

        return $rules;
    }
}
