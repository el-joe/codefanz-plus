<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Language;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['clear' => ['nullable', 'array'], 'clear.*' => ['string']];

        foreach ($this->fields() as $key => $field) {
            if ($field['type'] === 'translatable') {
                $rules["settings.{$key}"] = ['nullable', 'array'];

                foreach (Language::active()->keys() as $code) {
                    $rules["settings.{$key}.{$code}"] = $field['rules'];
                }

                continue;
            }

            $rules["settings.{$key}"] = $field['rules'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->fields() as $key => $field) {
            $attributes["settings.{$key}"] = __($field['label']);

            foreach (Language::active() as $code => $language) {
                $attributes["settings.{$key}.{$code}"] = __($field['label']).' ('.$language['native_name'].')';
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, array{type: string, label: string, rules: list<string>}>
     */
    public function fields(): array
    {
        return config('codefanz.settings.'.$this->route('group'), []);
    }
}
