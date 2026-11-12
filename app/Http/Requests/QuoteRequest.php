<?php

namespace App\Http\Requests;

use App\Enums\BudgetRange;
use App\Enums\ContactMethod;
use App\Enums\ProjectPlatform;
use App\Enums\ProjectTimeline;
use App\Http\Requests\Concerns\GuardsAgainstSpam;
use App\Http\Requests\Concerns\ValidatesUploadedFiles;
use App\Models\Language;
use App\Models\Service;
use App\Services\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Request a Quote (four steps in the browser, validated as one form here). Same bot checks as the contact form.
 */
class QuoteRequest extends FormRequest
{
    use GuardsAgainstSpam;
    use ValidatesUploadedFiles;

    /**
     * Fields per step, so the page can reopen the first step that has an error.
     *
     * @var array<int, list<string>>
     */
    public const STEPS = [
        1 => ['name', 'email', 'phone', 'country', 'company'],
        2 => ['services', 'platforms', 'description', 'features', 'goals', 'technologies', 'existing_url'],
        3 => ['budget_range', 'timeline', 'deadline'],
        4 => ['attachments', 'preferred_contact', 'preferred_locale', 'consent', 'form'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $attachments = config('codefanz.leads.attachments');
        $servicesOffered = Service::query()->published()->exists();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'country' => ['required', 'string', Rule::in(Countries::CODES)],
            'company' => ['nullable', 'string', 'max:160'],

            'services' => [$servicesOffered ? 'required' : 'nullable', 'array', 'max:10'],
            'services.*' => ['integer', 'distinct', Rule::exists('services', 'id')->where('status', 'published')->whereNull('deleted_at')],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['distinct', Rule::enum(ProjectPlatform::class)],
            'description' => ['required', 'string', 'min:30', 'max:2000'],
            'features' => ['required', 'string', 'min:5', 'max:2000'],
            'goals' => ['nullable', 'string', 'max:2000'],
            'technologies' => ['nullable', 'array', 'max:20'],
            'technologies.*' => ['integer', 'distinct', Rule::exists('technologies', 'id')->where('is_visible', true)->whereNull('deleted_at')],
            'existing_url' => ['nullable', 'string', 'url:http,https', 'max:500'],

            'budget_range' => ['nullable', Rule::enum(BudgetRange::class)],
            'timeline' => ['nullable', Rule::enum(ProjectTimeline::class)],
            'deadline' => ['nullable', 'date_format:Y-m-d', 'after:today'],

            'attachments' => ['nullable', 'array', 'max:'.$attachments['max_files']],
            'attachments.*' => $this->uploadedFileRules(),
            'preferred_contact' => ['required', Rule::enum(ContactMethod::class)],
            'preferred_locale' => ['required', Rule::in(Language::active()->keys()->all())],
            'consent' => ['accepted'],

            'reference' => ['nullable', 'string', 'max:120'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'referrer' => ['nullable', 'string', 'max:500'],
            self::TIMESTAMP => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectExpiredForm($validator)];
    }

    /**
     * Lead attributes from the validated input.
     *
     * @return array<string, mixed>
     */
    public function leadData(): array
    {
        $data = $this->validated();
        $clean = fn (?string $value): ?string => filled($value) ? trim($value) : null;

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => trim($data['phone']),
            'company' => $clean($data['company'] ?? null),
            'country' => $data['country'],
            'services' => $data['services'] ?? [],
            'platforms' => array_values($data['platforms']),
            'description' => trim($data['description']),
            'features' => trim($data['features']),
            'goals' => $clean($data['goals'] ?? null),
            'technologies' => $data['technologies'] ?? [],
            'existing_url' => $clean($data['existing_url'] ?? null),
            'budget_range' => $data['budget_range'] ?? null,
            'timeline' => $data['timeline'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'preferred_contact' => $data['preferred_contact'],
            'preferred_locale' => $data['preferred_locale'],
            'brief' => array_filter(['reference' => $clean($data['reference'] ?? null)]) ?: null,
            'utm_source' => $clean($data['utm_source'] ?? null),
            'utm_medium' => $clean($data['utm_medium'] ?? null),
            'utm_campaign' => $clean($data['utm_campaign'] ?? null),
            'referrer' => $clean($data['referrer'] ?? null),
            'locale' => app()->getLocale(),
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent() !== null ? mb_substr($this->userAgent(), 0, 255) : null,
            'consent_at' => now(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Full name'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'country' => __('Country'),
            'company' => __('Company name'),
            'services' => __('Services'),
            'services.*' => __('Service'),
            'platforms' => __('Required platforms'),
            'platforms.*' => __('Platform'),
            'description' => __('Project description'),
            'features' => __('Main features'),
            'goals' => __('Business goals'),
            'technologies' => __('Preferred technologies'),
            'technologies.*' => __('Technology'),
            'existing_url' => __('Existing website'),
            'budget_range' => __('Estimated budget'),
            'timeline' => __('Timeline'),
            'deadline' => __('Expected deadline'),
            'attachments' => __('Attachments'),
            'attachments.*' => __('Attachment'),
            'preferred_contact' => __('Preferred contact method'),
            'preferred_locale' => __('Preferred language'),
            'consent' => __('Consent'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => __('Please agree to the Privacy Policy so we can reply to you.'),
            'services.required' => __('Choose at least one service.'),
            'platforms.required' => __('Choose at least one platform.'),
            self::TIMESTAMP.'.required' => __('This form has expired. Please review your message and send it again.'),
        ];
    }
}
