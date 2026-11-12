<?php

namespace App\Http\Requests\Admin;

use App\Models\Post;
use App\Services\Blog\EditorialChecklist;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Approve / publish / schedule. Open checklist warnings must be acknowledged explicitly.
 */
class ReviewPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post instanceof Post && ($this->user()?->can('review', $post) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'then' => ['nullable', 'in:none,publish,schedule'],
            'scheduled_for' => [$this->needsSchedule() ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i'],
            'acknowledge_warnings' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Post $post */
                $post = $this->route('post');
                $post->loadMissing(['translations', 'checklistItems']);
                $checklist = app(EditorialChecklist::class);
                $results = $checklist->evaluate($post, $checklist->storedManual($post));

                if ($this->routeIs('admin.posts.approve') && $this->input('then', 'none') === 'none') {
                    return;
                }

                if (EditorialChecklist::warningCount($results) > 0 && ! $this->boolean('acknowledge_warnings')) {
                    $validator->errors()->add('acknowledge_warnings', __('Confirm that you reviewed the open checklist warnings.'));
                }
            },
        ];
    }

    public function needsSchedule(): bool
    {
        return $this->routeIs('admin.posts.schedule') || $this->input('then') === 'schedule';
    }

    /**
     * The schedule time entered in the reviewer's own time zone, in UTC.
     */
    public function scheduledFor(): ?CarbonImmutable
    {
        $value = $this->validated('scheduled_for');

        return $value ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, $this->user()->timezone ?: config('app.timezone'))->utc() : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_for.required' => __('Choose when the article should go live.'),
        ];
    }
}
