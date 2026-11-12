<?php

namespace App\Actions\Ai;

use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a prompt template. A change to the prompt texts or variables bumps the version and stores an
 * immutable copy, so every generation can be traced to the exact prompt it used. Variables are compared after
 * normalising (MySQL JSON columns reorder keys, so isDirty() would report false changes).
 */
class SavePromptTemplate
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{key: string, name: string, purpose: string, locale?: string|null, description?: string|null, system_template: string, user_template: string, variables: list<array<string, mixed>>, is_active: bool, lock_version?: int|null}  $data
     */
    public function handle(?PromptTemplate $template, array $data, User $user): PromptTemplate
    {
        return DB::transaction(function () use ($template, $data, $user): PromptTemplate {
            $attributes = Arr::only($data, ['key', 'name', 'purpose', 'locale', 'description', 'system_template', 'user_template', 'variables', 'is_active']);

            if ($template === null) {
                $template = PromptTemplate::create([...$attributes, 'version' => 1, 'created_by' => $user->getKey(), 'updated_by' => $user->getKey()]);
                $this->snapshot($template, $user);
                $this->audit->record(operation: 'prompt_template.created', auditable: $template, user: $user, after: $this->auditable($template));

                return $template;
            }

            $template = PromptTemplate::query()->lockForUpdate()->findOrFail($template->getKey());

            if ((int) ($data['lock_version'] ?? 0) !== $template->version) {
                throw ValidationException::withMessages(['lock_version' => __('Someone else changed this prompt while you were editing. Reload to see their changes.')]);
            }

            $before = $this->auditable($template);
            $textChanged = $template->system_template !== $attributes['system_template']
                || $template->user_template !== $attributes['user_template']
                || $template->declaredVariables() !== (new PromptTemplate(['variables' => $attributes['variables']]))->declaredVariables();
            $template->fill([...$attributes, 'updated_by' => $user->getKey()]);

            if ($textChanged) {
                $template->version++;
            }

            $template->save();

            if ($textChanged) {
                $this->snapshot($template, $user);
            }

            $this->audit->record(operation: 'prompt_template.updated', auditable: $template, user: $user, before: $before, after: $this->auditable($template));

            return $template;
        });
    }

    private function snapshot(PromptTemplate $template, User $user): void
    {
        $template->versions()->create([
            'version' => $template->version,
            'system_template' => $template->system_template,
            'user_template' => $template->user_template,
            'variables' => $template->variables,
            'created_by' => $user->getKey(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditable(PromptTemplate $template): array
    {
        return [
            'key' => $template->key,
            'purpose' => $template->purpose->value,
            'version' => $template->version,
            'is_active' => $template->is_active,
            'locale' => $template->locale,
        ];
    }
}
