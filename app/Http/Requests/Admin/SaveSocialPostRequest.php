<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaStatus;
use App\Enums\SocialAccountStatus;
use App\Models\Language;
use App\Models\Media;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Services\Social\SocialPostValidator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Composer (B06). Variants are keyed by account id; platform limits (length incl. hashtags and link, hashtags,
 * media count/type/aspect, first comment) are checked in after(). Text may stay empty in a draft — it is required
 * when the post is submitted for review.
 */
class SaveSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof SocialPost
            ? ($this->user()?->can('update', $post) ?? false)
            : ($this->user()?->can('create', SocialPost::class) ?? false);
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
            'utm' => ['nullable', 'array:medium,campaign,content'],
            'utm.*' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_.\-]+$/'],
            'media_ids' => ['nullable', 'array', 'max:35'],
            'media_ids.*' => ['integer', 'distinct', Rule::exists('media', 'id')->where('status', MediaStatus::Ready->value)->whereNull('deleted_at')],
            'media_alt' => ['nullable', 'array'],
            'media_alt.*' => ['nullable', 'string', 'max:1000'],
            'variants' => ['nullable', 'array'],
            'variants.*.enabled' => ['nullable', 'boolean'],
            'variants.*.text' => ['nullable', 'string', 'max:63206'],
            'variants.*.hashtags' => ['nullable', 'string', 'max:1000'],
            'variants.*.first_comment' => ['nullable', 'string', 'max:1000'],
            'variants.*.include_media' => ['nullable', 'boolean'],
            'lock_version' => [$this->route('post') ? 'required' : 'nullable', 'integer'],
            'topic' => ['nullable', 'string', 'max:1000'],
            'planned_for' => ['nullable', 'date_format:Y-m-d\\TH:i'],
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

                $enabled = collect($this->input('variants', []))->filter(fn ($variant): bool => is_array($variant) && filter_var($variant['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN));
                $accounts = SocialAccount::query()
                    ->whereKey($enabled->keys()->map(fn ($id): int => (int) $id)->all())
                    ->where('status', '!=', SocialAccountStatus::Disconnected->value)
                    ->get()
                    ->keyBy('id');

                if ($accounts->count() !== $enabled->count()) {
                    $validator->errors()->add('variants', __('One of the chosen accounts is not available.'));

                    return;
                }

                $media = Media::query()->whereKey(array_map('intval', (array) $this->input('media_ids', [])))->get();
                $variants = [];

                foreach ($accounts as $id => $account) {
                    $input = $enabled[$id] ?? $enabled[(string) $id];
                    $variants[$id] = [
                        'platform' => $account->platform,
                        'text' => (string) ($input['text'] ?? ''),
                        'hashtags' => (string) ($input['hashtags'] ?? ''),
                        'first_comment' => (string) ($input['first_comment'] ?? ''),
                        'include_media' => filter_var($input['include_media'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ];
                }

                $errors = app(SocialPostValidator::class)->errors($variants, $media, SocialPostValidator::linkBase($this->input('link_url'), (array) $this->input('utm', [])), complete: false);

                foreach ($errors as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    /**
     * The planned time entered in the staff member's own time zone, in UTC.
     */
    public function plannedFor(): ?CarbonImmutable
    {
        $value = $this->validated('planned_for');

        return $value ? CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $value, $this->user()->timezone ?: config('app.timezone'))->utc() : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Internal title'),
            'link_url' => __('Link'),
            'utm.*' => __('UTM value'),
            'media_ids.*' => __('Image'),
        ];
    }
}
