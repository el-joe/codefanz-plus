<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTranslations;
use App\Models\Statistic;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveStatisticRequest extends FormRequest
{
    use ValidatesTranslations;

    public function authorize(): bool
    {
        $statistic = $this->route('statistic');

        return $statistic instanceof Statistic
            ? ($this->user()?->can('update', $statistic) ?? false)
            : ($this->user()?->can('create', Statistic::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            ...$this->translationRules([
                'label' => ['string', 'max:80'],
                'suffix' => ['string', 'max:10'],
            ], 'label'),
        ];
    }
}
