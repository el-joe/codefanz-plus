<?php

namespace App\Http\Requests\Api\Mcp;

use App\Enums\SocialAccountStatus;
use App\Models\Language;
use App\Models\SocialAccount;
use App\Services\Social\SocialPostValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * create_social_draft: one variant per account. Per-platform limits are the composer's (SocialPostValidator).
 * Images are not attached through MCP; staff add them in the composer.
 */
class CreateSocialDraftRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:150'],
            'locale' => ['required', 'string', Rule::in(Language::active()->keys()->all())],
            'campaign' => ['nullable', 'string', 'max:80'],
            'link_url' => ['nullable', 'url:https', 'max:500'],
            'variants' => ['required', 'array', 'min:1', 'max:10'],
            'variants.*.account_id' => ['required', 'integer', 'distinct'],
            'variants.*.text' => ['required', 'string', 'max:63206'],
            'variants.*.hashtags' => ['nullable', 'string', 'max:1000'],
            'variants.*.first_comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $accounts = $this->accounts();

                if ($accounts->count() !== count($this->input('variants', []))) {
                    $validator->errors()->add('variants', __('One of the chosen accounts is not available.'));

                    return;
                }

                $variants = [];

                foreach ($this->input('variants') as $input) {
                    $account = $accounts[(int) $input['account_id']];
                    $variants[$account->id] = [
                        'platform' => $account->platform,
                        'text' => (string) $input['text'],
                        'hashtags' => (string) ($input['hashtags'] ?? ''),
                        'first_comment' => (string) ($input['first_comment'] ?? ''),
                        'include_media' => false,
                    ];
                }

                $errors = app(SocialPostValidator::class)->errors($variants, collect(), SocialPostValidator::linkBase($this->input('link_url'), []), complete: false);

                foreach ($errors as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    public function accounts(): Collection
    {
        return SocialAccount::query()
            ->whereKey(collect($this->input('variants', []))->pluck('account_id')->map(fn ($id): int => (int) $id)->all())
            ->where('status', '!=', SocialAccountStatus::Disconnected->value)
            ->get()
            ->keyBy('id');
    }
}
