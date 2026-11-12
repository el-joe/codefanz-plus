<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The brand voice form uses one textarea per list (one rule / claim / domain per line; glossary lines are
 * "English term = Arabic term"); they are split into arrays before validation.
 */
class SaveBrandProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::AiManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $lines = fn (mixed $text): array => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $text) ?: []), fn (string $line): bool => $line !== ''));

        $this->merge([
            'do_rules' => ['en' => $lines($this->input('do_rules_text.en')), 'ar' => $lines($this->input('do_rules_text.ar'))],
            'dont_rules' => ['en' => $lines($this->input('dont_rules_text.en')), 'ar' => $lines($this->input('dont_rules_text.ar'))],
            'banned_claims' => $lines($this->input('banned_claims_text')),
            'allowed_domains' => array_map('mb_strtolower', $lines($this->input('allowed_domains_text'))),
            'glossary' => array_map(function (string $line): array {
                [$en, $ar] = array_pad(array_map('trim', explode('=', $line, 2)), 2, '');

                return ['en' => $en, 'ar' => $ar];
            }, $lines($this->input('glossary_text'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'tone' => ['array'],
            'tone.*' => ['nullable', 'string', 'max:1000'],
            'audience' => ['array'],
            'audience.*' => ['nullable', 'string', 'max:1000'],
            'do_rules.*' => ['array', 'max:30'],
            'do_rules.*.*' => ['string', 'max:300'],
            'dont_rules.*' => ['array', 'max:30'],
            'dont_rules.*.*' => ['string', 'max:300'],
            'banned_claims' => ['array', 'max:100'],
            'banned_claims.*' => ['string', 'max:100'],
            'allowed_domains' => ['array', 'max:50'],
            'allowed_domains.*' => ['string', 'max:255', 'regex:/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/'],
            'glossary' => ['array', 'max:200'],
            'glossary.*.en' => ['required', 'string', 'max:120'],
            'glossary.*.ar' => ['required', 'string', 'max:120'],
            'lock_version' => ['required', 'integer'],
        ];
    }

    /**
     * Point list errors at the textarea the person typed in.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($validator->errors()->keys() as $key) {
                    $field = match (true) {
                        str_starts_with($key, 'glossary.') => 'glossary_text',
                        str_starts_with($key, 'allowed_domains.') => 'allowed_domains_text',
                        str_starts_with($key, 'banned_claims.') => 'banned_claims_text',
                        default => null,
                    };

                    if ($field !== null && ! $validator->errors()->has($field)) {
                        $validator->errors()->add($field, str_starts_with($key, 'glossary.')
                            ? __('Write each glossary line as “English term = Arabic term”.')
                            : $validator->errors()->first($key));
                    }
                }
            },
        ];
    }
}
