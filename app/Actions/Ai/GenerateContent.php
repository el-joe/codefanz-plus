<?php

namespace App\Actions\Ai;

use App\Contracts\Approvable;
use App\Enums\AiGenerationStatus;
use App\Enums\AiUnavailableReason;
use App\Enums\PromptPurpose;
use App\Exceptions\AiUnavailable;
use App\Models\AiGeneration;
use App\Models\BrandProfile;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\Ai\AiBudget;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiRequest;
use App\Services\Ai\ContentGuardrails;
use App\Services\Ai\PromptRenderer;
use App\Services\Ai\StudioTargets;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Runs a prompt template through AiGateway (kill switches, budget and usage caps are enforced there) and records
 * the result as a private AiGeneration with guardrail flags. Nothing public changes here: the author then turns
 * the result into a blog draft or a change request (UseGeneration). A dry run (prompt library "Test") is logged
 * as usage and kept in the history but cannot be used.
 */
class GenerateContent
{
    public function __construct(
        private AiGateway $gateway,
        private AiBudget $budget,
        private PromptRenderer $renderer,
        private StudioTargets $targets,
        private ContentGuardrails $guardrails,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $inputs  declared variable => value
     * @param  list<string>  $locales  languages to write (the target language when translating)
     */
    public function handle(
        PromptTemplate $template,
        User $user,
        array $inputs,
        array $locales,
        (Model&Approvable)|null $target = null,
        ?string $translateFrom = null,
        bool $dryRun = false,
    ): AiGeneration {
        $purpose = $template->purpose;
        $inputs = $this->validatedInputs($template, $inputs);
        $locales = array_values(array_unique($locales));

        if ($locales === []) {
            throw ValidationException::withMessages(['locales' => __('Choose at least one language.')]);
        }

        if ($purpose->needsTarget() && $target === null && ! $dryRun) {
            throw ValidationException::withMessages(['target_id' => __('Choose the website item to write for.')]);
        }

        if ($purpose === PromptPurpose::Translation && ! $dryRun) {
            $translateFrom ??= 'en';
            $locales = array_values(array_diff($locales, [$translateFrom]));

            if ($locales === []) {
                throw ValidationException::withMessages(['locales' => __('Choose a language to translate into.')]);
            }
        }

        $type = $target !== null ? $this->targets->typeOf($target) : null;
        $fields = $this->targets->fieldsFor($purpose, $type);
        $brand = BrandProfile::current();

        $current = [];

        if ($target !== null) {
            $current = $this->targets->currentContent($target, $fields, $translateFrom !== null ? [$translateFrom] : $locales);

            if ($translateFrom !== null && collect($current[$translateFrom] ?? [])->filter()->isEmpty()) {
                throw ValidationException::withMessages(['target_id' => __('This item has no content in the source language yet.')]);
            }
        }

        $request = new AiRequest(
            system: $this->renderer->system($template, $brand, $locales),
            messages: [['role' => 'user', 'content' => $this->renderer->user($template, $inputs, $locales, $fields, $current, $translateFrom)]],
            schema: $this->renderer->schema($locales, $fields),
            schemaName: 'content_studio_'.$purpose->value,
            maxTokens: $purpose === PromptPurpose::BlogDraft ? 6000 : 3000,
        );

        $generation = new AiGeneration([
            'prompt_template_id' => $template->id,
            'template_version' => $template->version,
            'purpose' => $purpose,
            'mode' => $translateFrom !== null ? 'translate' : 'write',
            'target_type' => $type,
            'target_id' => $target?->getKey(),
            'locales' => $locales,
            'inputs' => [...$inputs, ...($translateFrom !== null ? ['_translate_from' => $translateFrom] : [])],
            'is_dry_run' => $dryRun,
            'user_id' => $user->getKey(),
        ]);

        try {
            $response = $this->gateway->complete($purpose->feature(), $request, ['user_id' => $user->getKey()]);
        } catch (AiUnavailable $exception) {
            if ($exception->reason === AiUnavailableReason::Failed) {
                $generation->fill([
                    'status' => AiGenerationStatus::Failed,
                    'provider' => $this->gateway->providerName()->value,
                    'error' => mb_substr((string) $exception->getPrevious()?->getMessage(), 0, 500),
                ])->save();
            }

            throw ValidationException::withMessages(['ai' => $exception->reason->label()]);
        }

        $output = $this->cleanOutput($response->json ?? [], $locales, $fields);
        $known = implode("\n", [...array_map('strval', $inputs), (string) json_encode($current, JSON_UNESCAPED_UNICODE)]);

        $generation->fill([
            'output' => $output,
            'guardrail_flags' => $this->guardrails->check($output, $known, $brand),
            'provider' => $this->gateway->providerName()->value,
            'model' => mb_substr($response->model, 0, 100),
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'estimated_cost_minor' => $this->budget->estimateCostMinor($response->model, $response->inputTokens, $response->outputTokens),
            'status' => AiGenerationStatus::Generated,
        ])->save();

        $this->audit->record(
            operation: $dryRun ? 'ai_generation.dry_run' : 'ai_generation.created',
            auditable: $generation,
            user: $user,
            after: ['template' => $template->key, 'version' => $template->version, 'purpose' => $purpose->value, 'target' => $type !== null ? $type.':'.$target?->getKey() : null, 'locales' => $locales, 'flags' => count($generation->guardrail_flags ?? [])],
        );

        return $generation;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, string>
     */
    private function validatedInputs(PromptTemplate $template, array $inputs): array
    {
        $clean = [];
        $errors = [];

        foreach ($template->declaredVariables() as $variable) {
            $value = trim((string) (is_scalar($inputs[$variable['name']] ?? null) ? $inputs[$variable['name']] : ''));

            if ($variable['required'] && $value === '') {
                $errors['inputs.'.$variable['name']] = __(':field is required.', ['field' => $variable['label']]);
            } elseif (mb_strlen($value) > $variable['max']) {
                $errors['inputs.'.$variable['name']] = __(':field may not be longer than :max characters.', ['field' => $variable['label'], 'max' => $variable['max']]);
            }

            $clean[$variable['name']] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * Keep only requested locales and fields, as plain text (Markdown allowed, HTML stripped), within length limits.
     *
     * @param  array<string, mixed>  $json
     * @param  list<string>  $locales
     * @param  list<string>  $fields
     * @return array<string, array<string, string>>
     */
    private function cleanOutput(array $json, array $locales, array $fields): array
    {
        $output = [];

        foreach ($locales as $locale) {
            foreach ($fields as $field) {
                $value = $json[$locale][$field] ?? '';
                $value = is_string($value) ? trim(strip_tags($value)) : '';
                $output[$locale][$field] = mb_substr($value, 0, $this->targets->maxLength($field));
            }
        }

        if (collect($output)->flatten()->filter()->isEmpty()) {
            throw ValidationException::withMessages(['ai' => __('The AI reply was empty. Try again or adjust the inputs.')]);
        }

        return $output;
    }
}
